<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Database;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\ActiveEntityRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\ActivePropertyRelation;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\GraphQL\AbstractEntityRelationResolver;
use TYPO3\CMS\Core\GraphQL\Type\SortClauseType;
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

abstract class AbstractPassiveEntityRelationResolver extends AbstractEntityRelationResolver
{
    public function getArguments(): array
    {
        return [
            [
                'name' => 'filter',
                'type' => Type::string(),
            ],
            [
                'name' => 'sort',
                'type' => SortClauseType::instance(),
            ]
        ];
    }

    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($cacheIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, 'uid');
            $keys[] = $source['uid'];
        }

        $context['cache']->set($cacheIdentifier, $keys);
    }

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $cacheIdentifier = $this->getCacheIdentifier('data');
        $data = $context['cache']->get($cacheIdentifier) ?: [];

        if (!$context['cache']->has($cacheIdentifier)) {
            $builder = $this->getBuilder($arguments, $context, $info);
            $statement = $builder->execute();

            while ($row = $statement->fetch()) {
                $row['__table'] = $this->getType($row);

                $data = $this->fetchData($row, $data);
            }

            $context['cache']->set($cacheIdentifier, $data);
        }

        return $data[$source['uid']] ?? [];
    }

    protected abstract function getType(array $source): string;

    protected abstract function getTable(): string;

    protected abstract function getForeignKeyField(): string;

    protected function getCacheIdentifier($identifier): string
    {
        return \spl_object_hash($this) . '_' . $identifier;
    }

    protected function fetchData(array $row, array $data): array
    {
        $data[$row[$this->getForeignKeyField()]][] = $row;
        return $data;
    }

    protected function getBuilder(array $arguments, array $context, ResolveInfo $info)
    {
        $table = $this->getTable();
        $keys = $context['cache']->get($this->getCacheIdentifier('keys')) ?? [];
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($builder, $info))
            ->from($table);

        $condition = $this->getCondition($keys, $builder, $info);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        $order = $this->getOrder((array)$arguments['sort']);

        foreach ($order as $item) {
            $builder->addOrderBy($item[0], $item[1]);
        }

        return $builder;
    }

    protected function getCondition(array $keys, QueryBuilder $builder, ResolveInfo $info)
    {
        $condition = GeneralUtility::makeInstance(FilterProcessor::class, $info, $builder)->process();
        $condition = $condition !== null ? [$condition] : [];

        $propertyConfiguration = $this->getPropertyDefinition()->getConfiguration();

        $condition[] = $builder->expr()->in(
            $this->getForeignKeyField(),
            $builder->createNamedParameter($keys, Connection::PARAM_INT_ARRAY)
        );

        return $condition;
    }

    protected function getColumns(QueryBuilder $builder, ResolveInfo $info)
    {
        $columns = [];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field') {
                $columns[] = $selection->name->value;
            }

            if ($selection->kind === 'InlineFragment') {
                foreach ($selection->selectionSet->selections as $inlineSelection) {
                    if ($inlineSelection->kind === 'Field') {
                        $columns[] = $selection->typeCondition->name->value . '.' . $inlineSelection->name->value;
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

    /**
     * @todo Use the meta model.
     */
    protected function getOrder(array $items = []): array
    {
        if (empty($items)) {
            $configuration = $GLOBALS['TCA'][$this->getTable()];
            $sortBy = $configuration['ctrl']['sortby'] ?: $configuration['ctrl']['default_sortby'];
            $items = QueryHelper::parseOrderBy($sortBy ?? '');
        } else {
            $items = array_map(function($item) {
                return [
                    $item['field'],
                    $item['order'] === 'descending' ? 'DESC' : 'ASC'
                ];
            }, $items);
        }

        return $items;
    }
}