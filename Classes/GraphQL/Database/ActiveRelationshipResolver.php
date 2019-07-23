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

use Doctrine\DBAL\ParameterType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\ActiveEntityRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractRelationshipResolver;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\GraphQL\ResolverHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class ActiveRelationshipResolver extends AbstractRelationshipResolver
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
            if (!($activeRelation instanceof ActiveEntityRelation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $column = $this->getPropertyDefinition()->getName();

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, $column);

            foreach ($this->getForeignKeys((string) $source[$column]) as $table => $identifier) {
                $keys[$table][] = $identifier;
            }

            foreach ($keys as $table => $identifiers) {
                $keys[$table] = array_keys(array_flip($identifiers));
            }
        }

        $context['cache']->set($keysIdentifier, $keys);
    }

    /**
     * @inheritdoc
     */
    public function resolve($source, array $arguments, array $context, ResolveInfo $info): ?array
    {
        Assert::keyExists($source, $this->getPropertyDefinition()->getName());
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $bufferIdentifier = $this->getCacheIdentifier('buffer');
        $buffer = $context['cache']->get($bufferIdentifier) ?: [];

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        $column = $this->getPropertyDefinition()->getName();

        $value = [];
        $tables = [];

        if (!$context['cache']->has($bufferIdentifier)) {
            $foreignKeyField = $this->getForeignKeyField();

            foreach ($this->getPropertyDefinition()->getRelationTableNames() as $table) {
                $builder = $this->getBuilder($info, $table, $keys);

                $this->onResolve($source, $arguments, array_merge($context, [
                    'builder' => $builder,
                ]), $info);

                $statement = $builder->execute();

                while ($row = $statement->fetch()) {
                    $buffer[$row[EntitySchemaFactory::ENTITY_TYPE_FIELD]][$row[$foreignKeyField]] = $row;
                }
            }

            $context['cache']->set($bufferIdentifier, $buffer);
        }

        foreach ($this->getForeignKeys((string) $source[$column]) as $table => $identifier) {
            $tables[$table] = true;
            $value[] = $buffer[$table][$identifier];
        }

        $context = array_merge($context, [
            'builder' => $builder,
        ]);

        $value = $this->onResolved($value, $source, $arguments, $context, $info);

        return $this->getValue($value);
    }

    protected function getValue(?array $value): ?array
    {
        if (empty($value)) {
            return $this->getMultiplicityConstraint()->getMinimum() > 0
                || $this->getMultiplicityConstraint()->getMaximum() > 1 ? [] : null;
        }

        return $this->getMultiplicityConstraint()->getMaximum() > 1 ? $value : $value[0];
    }

    protected function getCacheIdentifier($identifier): string
    {
        return \spl_object_hash($this) . '_' . $identifier;
    }

    protected function getBuilder(ResolveInfo $info, string $table, array $keys): QueryBuilder
    {
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->selectLiteral(...$this->getColumns($info, $builder, $table))
            ->from($table);

        $condition = $this->getCondition($builder, $table, $keys);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        return $builder;
    }

    protected function getCondition(QueryBuilder $builder, string $table, array $keys): array
    {
        $condition = [];

        $propertyConfiguration = $this->getPropertyDefinition()->getConfiguration();

        $condition[] = $builder->expr()->in(
            $table . '.' . $this->getForeignKeyField(),
            $builder->createNamedParameter($keys[$table], Connection::PARAM_INT_ARRAY)
        );

        if (isset($propertyConfiguration['config']['foreign_table_field'])) {
            $condition[] = $builder->expr()->eq(
                $table . '.' . $propertyConfiguration['config']['foreign_table_field'],
                $builder->createNamedParameter($this->getPropertyDefinition()->getEntityDefinition()->getName())
            );
        }

        foreach ($propertyConfiguration['config']['foreign_match_fields'] ?? [] as $field => $match) {
            $condition[] = $builder->expr()->eq(
                $table . '.' . $field, 
                $builder->createNamedParameter($match)
            );
        }

        return $condition;
    }

    protected function getColumns(ResolveInfo $info, QueryBuilder $builder, string $table): array
    {
        $columns = [];

        foreach (ResolverHelper::getFields($info, $table) as $field) {
            $columns[$field->name->value] = $builder->quoteIdentifier($table . '.' . $field->name->value);
        }

        foreach (ResolverHelper::getFields($info) as $field) {
            if (isset($columns[$field->name->value])) {
                continue;
            }

            $columns[] = 'NULL AS ' . $builder->quoteIdentifier($field->name->value);
        }

        $foreignKeyField = $this->getForeignKeyField();

        if ($foreignKeyField) {
            $columns[] = $builder->quoteIdentifier($foreignKeyField);
        }

        $columns[] = $builder->quote($table, ParameterType::STRING)
            . ' AS ' . $builder->quoteIdentifier(EntitySchemaFactory::ENTITY_TYPE_FIELD);

        return array_values($columns);
    }

    protected function getForeignKeyField(): string
    {
        return 'uid';
    }

    protected function getForeignKeys(string $commaSeparatedValues)
    {
        $defaultTable = reset($this->getPropertyDefinition()->getRelationTableNames());
        $commaSeparatedValues = array_unique($commaSeparatedValues ? explode(',', $commaSeparatedValues) : []);

        foreach ($commaSeparatedValues as $commaSeparatedValue) {
            $separatorPosition = strrpos($commaSeparatedValue, '_');
            $table = $separatorPosition ? substr($commaSeparatedValue, 0, $separatorPosition) : $defaultTable;
            $identifier = substr($commaSeparatedValue, ($separatorPosition ?: -1) + 1);

            yield $table => $identifier;
        }
    }
}