<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Database;

use GraphQL\Type\Definition\ResolveInfo;
use Traversable;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\ActiveEntityRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractEntityRelationResolver;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;

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

class ActiveEntityRelationResolver extends AbstractEntityRelationResolver
{
    public static function canResolve(PropertyDefinition $propertyDefinition)
    {
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

    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($cacheIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, $this->getPropertyDefinition()->getName());

            foreach ($this->getForeignKeys((string)$source[$this->getPropertyDefinition()->getName()]) as $table => $identifier) {
                $keys[$table][] = $identifier;
            }

            foreach ($keys as $table => $identifiers) {
                $keys[$table] = array_keys(array_flip($identifiers));
            }
        }

        $context['cache']->set($cacheIdentifier, $keys);
    }

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        Assert::keyExists($source, $this->getPropertyDefinition()->getName());
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('buffer');
        $buffer = $context['cache']->get($cacheIdentifier) ?: [];
        $result = [];
        $tables = [];

        if (!$context['cache']->has($cacheIdentifier)) {
            $foreignKeyField = $this->getForeignKeyField();

            foreach ($this->getPropertyDefinition()->getRelationTableNames() as $table) {
                $builder = $this->getBuilder($table, $arguments, $context, $info);
                $statement = $builder->execute();

                while ($row = $statement->fetch()) {
                    $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] = $table;
                    $buffer[$table][$row[$foreignKeyField]] = $row;
                }
            }

            $context['cache']->set($cacheIdentifier, $buffer);
        }

        foreach ($this->getForeignKeys((string)$source[$this->getPropertyDefinition()->getName()]) as $table => $identifier) {
            $tables[$table] = true;
            $result[] = $buffer[$table][$identifier];
        }

        $result = $this->orderResult($arguments, $result, $info, array_keys($tables));

        return $result;
    }

    protected function getCacheIdentifier($identifier): string
    {
        return \spl_object_hash($this) . '_' . $identifier;
    }

    protected function getBuilder(string $table, array $arguments, array $context, ResolveInfo $info): QueryBuilder
    {
        $keys = $context['cache']->get($this->getCacheIdentifier('keys')) ?? [];
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($table, $info))
            ->from($table);

        $condition = $this->getCondition($table, $keys, $builder, $info);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        foreach ($this->getOrderBy($arguments, $info, $table) as $item) {
            $builder->addSelect($item['field']);
            $builder->addOrderBy($item['field'], $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC');
        }

        return $builder;
    }

    protected function getCondition(string $table, array $keys, QueryBuilder $builder, ResolveInfo $info): array
    {
        $condition = GeneralUtility::makeInstance(FilterArgumentProcessor::class, $info, $builder)->process();
        $condition = $condition !== null ? [$condition] : [];

        $propertyConfiguration = $this->getPropertyDefinition()->getConfiguration();

        $condition[] = $builder->expr()->in(
            $this->getForeignKeyField(),
            $builder->createNamedParameter($keys[$table], Connection::PARAM_INT_ARRAY)
        );

        if (isset($propertyConfiguration['config']['foreign_table_field'])) {
            $condition[] = $builder->expr()->eq(
                $propertyConfiguration['config']['foreign_table_field'],
                $builder->createNamedParameter($this->getPropertyDefinition()->getEntityDefinition()->getName())
            );
        }

        foreach ($propertyConfiguration['config']['foreign_match_fields'] ?? [] as $field => $match) {
            $condition[] = $builder->expr()->eq($field, $builder->createNamedParameter($match));
        }

        return $condition;
    }

    /**
     * @todo GraphQL standard compliance.
     */
    protected function getColumns(string $table, ResolveInfo $info): array
    {
        $columns = [];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field') {
                $columns[] = $selection->name->value;
            } else if ($selection->kind === 'InlineFragment' && $selection->typeCondition->name->value === $table) {
                foreach ($selection->selectionSet->selections as $selection) {
                    if ($selection->kind === 'Field') {
                        $columns[] = $selection->name->value;
                    }
                }
            }
        }

        $foreignKeyField = $this->getForeignKeyField();

        if ($foreignKeyField) {
            $columns[] = $foreignKeyField;
        }

        return $columns;
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

    protected function getOrderBy(array $arguments, ResolveInfo $info, string $table): Traversable
    {
        $expression = $arguments[EntitySchemaFactory::ORDER_ARGUMENT_NAME] ?? null;

        OrderExpressionValidator::validate($info, $expression, $table);

        return GeneralUtility::makeInstance(OrderExpressionTraversable::class, $info, $expression, $table);
    }

    protected function orderResult(array $arguments, array $rows, ResolveInfo $info, array $tables): array
    {
        $expression = $arguments[EntitySchemaFactory::ORDER_ARGUMENT_NAME] ?? null;

        if ($expression === null) {
            return $rows;
        }

        $traversable = GeneralUtility::makeInstance(OrderExpressionTraversable::class, $info, $expression, ...$tables);
        $arguments = [];

        foreach ($traversable as $item) {
            array_push($arguments, array_map(function ($row) use ($item) {
                return !$item['constraint'] || $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] === $item['constraint']
                    ? $row[$item['field']] : null;
            }, $rows), $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? SORT_ASC : SORT_DESC);
        }

        array_push($arguments, $rows);
        array_multisort(...$arguments);

        return array_pop($arguments);
    }
}