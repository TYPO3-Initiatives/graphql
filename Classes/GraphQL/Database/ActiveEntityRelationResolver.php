<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Database;

use GraphQL\Type\Definition\ResolveInfo;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\ActiveEntityRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\ActivePropertyRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\GraphQL\AbstractEntityRelationResolver;
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

    public function getArguments(): array
    {
        return [];
    }

    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($cacheIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, $this->getPropertyDefinition()->getName());
            $keys = array_merge_recursive($keys, $this->getForeignKeys($source[$this->getPropertyDefinition()->getName()]));
        }

        $context['cache']->set($cacheIdentifier, $keys);
    }

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        Assert::keyExists($source, $this->getPropertyDefinition()->getName());
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('data');
        $data = $context['cache']->get($cacheIdentifier) ?: [];
        $result = [];

        if (!$context['cache']->has($cacheIdentifier)) {
            $foreignKeyField = $this->getForeignKeyField();

            foreach ($this->getPropertyDefinition()->getRelationTableNames() as $table) {
                $builder = $this->getBuilder($table, $arguments, $context, $info);
                $statement = $builder->execute();

                while ($row = $statement->fetch()) {
                    $row['__table'] = $table;
                    $data[$table][$row[$foreignKeyField]] = $row;
                }
            }

            $context['cache']->set($cacheIdentifier, $data);
        }

        foreach ($this->getForeignKeys($source[$this->getPropertyDefinition()->getName()]) as $table => $foreignKeys) {
            foreach ($foreignKeys as $foreignKey) {
                $result[] = $data[$table][$foreignKey];
            }
        }

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

        $builder->select(...$this->getColumns($table, $builder, $info))
            ->from($table);

        $condition = $this->getCondition($table, $keys, $builder, $info);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        $order = $this->getOrder($table, (array)$arguments['sort']);

        foreach ($order as $item) {
            $builder->addOrderBy($item[0], $item[1]);
        }

        return $builder;
    }

    protected function getCondition(string $table, array $keys, QueryBuilder $builder, ResolveInfo $info): array
    {
        $condition = GeneralUtility::makeInstance(FilterProcessor::class, $info, $builder)->process();
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
    protected function getColumns(string $table, QueryBuilder $builder, ResolveInfo $info): array
    {
        $columns = [];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field') {
                $columns[] = $selection->name->value;
            }

            if ($selection->kind === 'InlineFragment' && $selection->typeCondition->name->value === $table) {
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

    protected function getForeignKeys($commaSeparatedValues): array
    {
        $foreignKeys = [];
        $defaultTable = reset($this->getPropertyDefinition()->getRelationTableNames());
        $commaSeparatedValues = array_unique($commaSeparatedValues ? explode(',', (string)$commaSeparatedValues) : []);

        foreach ($commaSeparatedValues as $commaSeparatedValue) {
            $separatorPosition = strrpos($commaSeparatedValue, '_');
            $table = $separatorPosition ? substr($commaSeparatedValue, 0, $separatorPosition) : $defaultTable;
            $foreignKeys[$table][] = substr($commaSeparatedValue, ($separatorPosition ?: -1) + 1);
        }

        return $foreignKeys;
    }

    /**
     * @todo Use the meta model.
     */
    protected function getOrder(string $table, array $items = []): array
    {
        if (empty($items)) {
            $configuration = $GLOBALS['TCA'][$table];
            $sortBy = $configuration['ctrl']['sortby'] ?: $configuration['ctrl']['default_sortby'];
            $items = QueryHelper::parseOrderBy($sortBy ?? '');
        } else {
            $items = array_map(function($item) {
                return [
                    $table . '.' . $item['field'],
                    $item['order'] === 'descending' ? 'DESC' : 'ASC'
                ];
            }, $items);
        }

        return $items;
    }
}