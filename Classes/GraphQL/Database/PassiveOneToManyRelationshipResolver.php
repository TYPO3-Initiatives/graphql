<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\GraphQL\Database;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\ActivePropertyRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class PassiveOneToManyRelationshipResolver extends AbstractPassiveRelationshipResolver
{
    /**
     * @inheritdoc
     */
    public static function canResolve(Type $type): bool
    {
        if (!isset($type->config['meta']) || !$type->config['meta'] instanceof PropertyDefinition) {
            return false;
        }

        $propertyDefinition = $type->config['meta'];

        if ($propertyDefinition->isManyToManyRelationProperty()) {
            return false;
        }

        foreach ($propertyDefinition->getActiveRelations() as $activeRelation) {
            if (!($activeRelation instanceof ActivePropertyRelation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     * @todo Prevent reaching maximum length of a generated SQL statement.
     * @see https://www.sqlite.org/limits.html#max_sql_length
     * @see https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_allowed_packet
     * @see https://mariadb.com/kb/en/library/server-system-variables/#max_allowed_packet
     * @see https://www.postgresql.org/docs/9.1/runtime-config-resource.html#GUC-MAX-STACK-DEPTH
     */
    public function resolve($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $bufferIdentifier = $this->getCacheIdentifier('buffer');
        $buffer = $context['cache']->get($bufferIdentifier) ?: [];

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?? [];

        if (!$context['cache']->has($bufferIdentifier)) {
            $table =$this->getTable();
            $builder = $this->getBuilder($info, $table, $keys);

            $this->onResolve($source, $arguments, array_merge($context, [
                'builder' => $builder,
            ]), $info);

            $statement = $builder->execute();

            while ($row = $statement->fetch()) {
                foreach ($this->getBufferIndexes($row) as $index) {
                    $buffer[$index][] = $this->onResolved($row, $source, $arguments, $context, $info);
                }
            }

            $context['cache']->set($bufferIdentifier, $buffer);
        }

        return $this->getValue($buffer[$source['uid']]);
    }

    protected function getBufferIndexes(array $row): array
    {
        $indexes = parent::getBufferIndexes($row);

        $configuration = $this->getPropertyDefinition()->getConfiguration();

        if (isset($configuration['config']['symmetric_field'])) {
            $indexes[] = $row['__' . $configuration['config']['symmetric_field']];
        }

        return $indexes;
    }

    protected function getTable(): string
    {
        return reset($this->getPropertyDefinition()->getRelationTableNames());
    }

    protected function getForeignKeyField(): string
    {
        Assert::count($this->getPropertyDefinition()->getActiveRelations(), 1);

        $activeRelation = reset($this->getPropertyDefinition()->getActiveRelations());

        Assert::isInstanceOf($activeRelation, ActivePropertyRelation::class);

        return $activeRelation->getTo()->getName();
    }

    protected function getColumns(ResolveInfo $info, QueryBuilder $builder, string $table)
    {
        $columns = parent::getColumns($info, $builder, $table);

        $columns[] = $builder->quoteIdentifier($table . '.' . $this->getForeignKeyField())
            . ' AS ' . $builder->quoteIdentifier('__' . $this->getForeignKeyField());

        $configuration = $this->getPropertyDefinition()->getConfiguration();

        if (isset($configuration['config']['symmetric_field'])) {
            $columns[] = $builder->quoteIdentifier($table . '.' . $configuration['config']['symmetric_field'])
                . ' AS ' . $builder->quoteIdentifier('__' . $configuration['config']['symmetric_field']);
        }

        return $columns;
    }

    protected function getCondition(QueryBuilder $builder, array $keys): array
    {
        $condition = parent::getCondition($builder, $keys);

        $configuration = $this->getPropertyDefinition()->getConfiguration();
        $table = $this->getPropertyDefinition()->getEntityDefinition()->getName();

        if (isset($configuration['config']['foreign_table_field'])) {
            $condition[] = $builder->expr()->eq(
                $this->getTable() . '.' . $configuration['config']['foreign_table_field'],
                $builder->createNamedParameter($table)
            );

            if (isset($configuration['config']['symmetric_field'])) {
                $condition[] = $builder->expr()->andX(
                    array_pop($condition),
                    $builder->expr()->eq(
                        $this->getTable() . '.' . $configuration['config']['symmetric_field'],
                        $builder->createNamedParameter($table)
                    )
                );
            }
        }

        foreach ($configuration['config']['foreign_match_fields'] ?? [] as $field => $match) {
            $condition[] = $builder->expr()->eq(
                $this->getTable() . '.' . $field,
                $builder->createNamedParameter($match)
            );
        }

        return $condition;
    }
}