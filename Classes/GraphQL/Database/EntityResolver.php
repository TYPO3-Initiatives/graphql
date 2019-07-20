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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractEntityResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EntityResolver extends AbstractEntityResolver
{
    public function resolve($source, array $arguments, array $context, ResolveInfo $info): ?array
    {
        $builder = $this->getBuilder($info);

        $context = array_merge($context, [
            'builder' => $builder,
            'tables' => [$this->getTable()],
            'meta' => $this->getEntityDefinition(),
        ]);

        $this->handlers->beforeResolve($source, $arguments, $context, $info);

        $value = $builder->execute()->fetchAll();

        return $this->handlers->afterResolve($source, $arguments, $context, $info, $value);
    }

    protected function getBuilder(ResolveInfo $info): QueryBuilder
    {
        $table = $this->getTable();

        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($info))
            ->from($table);

        return $builder;
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
}