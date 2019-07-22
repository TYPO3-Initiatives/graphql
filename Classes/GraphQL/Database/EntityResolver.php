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
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal
 */
class EntityResolver extends AbstractResolver
{
    /**
     * @var EntityDefinition
     */
    protected $entityDefinition;

    /**
     * @inheritdoc
     */
    public static function canResolve(Type $type): bool
    {
        if (!isset($type->config['meta']) || !$type->config['meta'] instanceof EntityDefinition) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function __construct(Type $type)
    {
        parent::__construct($type);

        $this->entityDefinition = $type->config['meta'];
    }

    /**
     * @inheritdoc
     */
    public function resolve($source, array $arguments, array $context, ResolveInfo $info): ?array
    {
        $builder = $this->getBuilder($info);

        $this->onResolve($source, $arguments, array_merge($context, [
            'builder' => $builder,
        ]), $info);

        $value = $builder->execute()->fetchAll();

        $value = $this->onResolved($value, $source, $arguments, $context, $info);

        return $value;
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
        return $this->entityDefinition->getName();
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