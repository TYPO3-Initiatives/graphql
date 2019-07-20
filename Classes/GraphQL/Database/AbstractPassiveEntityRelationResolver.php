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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractEntityRelationResolver;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;

abstract class AbstractPassiveEntityRelationResolver extends AbstractEntityRelationResolver
{
    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, 'uid');
            $keys[] = $source['uid'];
        }

        $context['cache']->set($keysIdentifier, $keys);
    }

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): ?array
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $bufferIdentifier = $this->getCacheIdentifier('buffer');
        $buffer = $context['cache']->get($bufferIdentifier) ?: [];

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?? [];

        if (!$context['cache']->has($bufferIdentifier)) {
            $builder = $this->getBuilder($arguments, $info, $keys);

            $context = array_merge($context, [
                'builder' => $builder,
                'tables' => $this->getPropertyDefinition()->getRelationTableNames(),
                'meta' => $this->getPropertyDefinition(),
            ]);

            $this->handlers->beforeResolve($source, $arguments, $context, $info);

            $statement = $builder->execute();

            while ($row = $statement->fetch()) {
                $row = $this->transformRow($row);

                foreach ($this->getBufferIndexes($row) as $index) {
                    $buffer[$index][] = $this->handlers->afterResolve($source, $arguments, $context, $info, $row);
                }
            }

            $context['cache']->set($bufferIdentifier, $buffer);
        }

        return $this->getValue($buffer[$source['uid']]);
    }

    protected abstract function getType(array $source): string;

    protected abstract function getTable(): string;

    protected abstract function getForeignKeyField(): string;

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

    protected function getColumnIdentifier(string $table, string $column): string
    {
        return sprintf('%s.%s', $table, $column);
    }

    protected function getColumnAlias(string $table, string $column): string
    {
        return sprintf('%s_%s', $table, $column);
    }

    protected function getColumnIdentifierForSelect(string $table, string $column): string
    {
        return sprintf(
            '%s AS %s',
            $this->getColumnIdentifier($table, $column),
            $this->getColumnAlias($table, $column)
        );
    }

    protected function transformRow(array $row): array
    {
        $type = $this->getType($row);

        foreach ($row as $field => $value) {
            if (strpos($field, $type) === 0) {
                $row[substr($field, strlen($type) + 1)] = $value;
            }
        }

        $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] = $type;

        return $row;
    }

    protected function getBufferIndexes(array $row): array
    {
        $alias = $this->getColumnAlias(
            $this->getTable(),
            $this->getForeignKeyField()
        );

        return [
            $row[$alias],
        ];
    }

    protected function getBuilder(array $arguments, ResolveInfo $info, array $keys)
    {
        $table = $this->getTable();
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($info))
            ->from($table);

        $condition = $this->getCondition($builder, $keys);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        return $builder;
    }

    protected function getCondition(QueryBuilder $builder, array $keys)
    {
        $condition = [];

        $condition[] = $builder->expr()->in(
            $this->getColumnIdentifier(
                $this->getTable(),
                $this->getForeignKeyField()
            ),
            $builder->createNamedParameter($keys, Connection::PARAM_INT_ARRAY)
        );

        return $condition;
    }

    protected function getColumns(ResolveInfo $info)
    {
        $activeRelations = $info->returnType->config['meta']->getActiveRelations();
        $columns = [];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field') {
                foreach ($activeRelations as $activeRelation) {
                    $columns[] = $this->getColumnIdentifierForSelect(
                        $activeRelation->getTo() instanceof EntityDefinition ? $activeRelation->getTo()->getName()
                            : $activeRelation->getTo()->getEntityDefinition()->getName(),
                        $selection->name->value
                    );
                }
            } else if ($selection->kind === 'InlineFragment') {
                foreach ($selection->selectionSet->selections as $inlineSelection) {
                    if ($inlineSelection->kind !== 'Field') {
                        continue;
                    }

                    $columns[] = $this->getColumnIdentifierForSelect(
                        $selection->typeCondition->name->value,
                        $inlineSelection->name->value
                    );
                }
            }
        }

        $columns[] = $this->getColumnIdentifierForSelect(
            $this->getTable(),
            $this->getForeignKeyField()
        );

        return $columns;
    }
}