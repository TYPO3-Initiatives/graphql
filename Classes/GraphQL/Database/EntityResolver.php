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
use Traversable;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractEntityResolver;
use TYPO3\CMS\Core\GraphQL\Database\FilterExpressionProcessor;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EntityResolver extends AbstractEntityResolver
{
    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        return $this->getBuilder($arguments, $info)
            ->execute()
            ->fetchAll();
    }

    protected function getBuilder(array $arguments, ResolveInfo $info): QueryBuilder
    {
        $table = $this->getTable();

        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($info))
            ->from($table);

        $condition = $this->getCondition($arguments, $info, $builder);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        foreach ($this->getOrderBy($arguments, $info, $table) as $item) {
            $builder->addSelect($item['field']);
            $builder->addOrderBy(
                $item['field'],
                $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC'
            );
        }

        return $builder;
    }

    protected function getCondition(array $arguments, ResolveInfo $info, QueryBuilder $builder): array
    {
        $expression = $arguments[EntitySchemaFactory::FILTER_ARGUMENT_NAME] ?? null;
        $processor = GeneralUtility::makeInstance(FilterExpressionProcessor::class, $info, $expression, $builder);

        $condition = $processor->process();

        return $condition !== null ? [$condition] : [];
    }

    protected function getTable(): string
    {
        return $this->getEntityDefinition()->getName();
    }

    protected function getColumns(ResolveInfo $info): array
    {
        $columns = ['uid'];

        foreach ($info->fieldNodes[0]->selectionSet->selections as $selection) {
            if ($selection->kind === 'Field' && $selection->name->value !== 'uid') {
                $columns[] = $selection->name->value;
            }
        }

        return $columns;
    }

    protected function getOrderBy(array $arguments, ResolveInfo $info, string $table): Traversable
    {
        $expression = $arguments[EntitySchemaFactory::ORDER_ARGUMENT_NAME] ?? null;

        OrderExpressionValidator::validate($info, $expression, $table);

        return GeneralUtility::makeInstance(OrderExpressionTraversable::class, $info, $expression, $table);
    }
}