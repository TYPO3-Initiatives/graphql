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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
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
abstract class AbstractPassiveRelationshipResolver extends AbstractRelationshipResolver
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

    protected function getBufferIndexes(array $row): array
    {
        return [
            $row['__' . $this->getForeignKeyField()],
        ];
    }

    protected function getBuilder(ResolveInfo $info, string $table, array $keys): QueryBuilder
    {
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->selectLiteral(...$this->getColumns($info, $builder, $table))
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
            $this->getTable() . '.' . $this->getForeignKeyField(),
            $builder->createNamedParameter($keys, Connection::PARAM_INT_ARRAY)
        );

        return $condition;
    }

    protected function getColumns(ResolveInfo $info, QueryBuilder $builder, string $table)
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

        $columns[] = $builder->quote($table, ParameterType::STRING)
            . ' AS ' . $builder->quoteIdentifier(EntitySchemaFactory::ENTITY_TYPE_FIELD);

        return array_values($columns);
    }
}