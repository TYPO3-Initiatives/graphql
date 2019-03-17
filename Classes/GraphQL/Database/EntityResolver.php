<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Database;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\GraphQL\AbstractEntityResolver;
use TYPO3\CMS\Core\GraphQL\Database\FilterProcessor;
use TYPO3\CMS\Core\GraphQL\Type\SortClauseType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

class EntityResolver extends AbstractEntityResolver
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

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        return $this->getBuilder($arguments, $context, $info)
            ->execute()
            ->fetchAll();
    }

    protected function getBuilder(array $arguments, array $context, ResolveInfo $info): QueryBuilder
    {
        $table = $this->getTable($info);

        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($builder, $info))
            ->from($table);

        $condition = $this->getCondition($builder, $info);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        $order = $this->getOrder((array)$arguments['sort']);

        foreach ($order as $item) {
            $builder->addOrderBy($item[0], $item[1]);
        }

        return $builder;
    }

    protected function getCondition(QueryBuilder $builder, ResolveInfo $info): array
    {
        $condition = GeneralUtility::makeInstance(FilterProcessor::class, $info, $builder)->process();
        return $condition !== null ? [$condition] : [];
    }

    protected function getTable(ResolveInfo $info): string
    {
        return $this->getEntityDefinition()->getName();
    }

    protected function getColumns(QueryBuilder $builder, ResolveInfo $info): array
    {
        $columns = ['uid'];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field' && $selection->name->value !== 'uid') {
                $columns[] = $selection->name->value;
            }
        }

        return $columns;
    }

    /**
     * @todo Use the meta model.
     */
    protected function getOrder(array $items = []): array
    {
        if (empty($items)) {
            $configuration = $GLOBALS['TCA'][$this->getEntityDefinition()->getName()];
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