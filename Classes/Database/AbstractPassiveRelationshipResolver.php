<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Database;

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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\AbstractRelationshipResolver;
use TYPO3\CMS\GraphQL\Database\Query\ContextAwareQueryBuilder;
use TYPO3\CMS\GraphQL\EntitySchemaFactory;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
abstract class AbstractPassiveRelationshipResolver extends AbstractRelationshipResolver
{
    /**
     * @inheritdoc
     */
    public function collect($source, array $arguments, array $context, ResolveInfo $info)
    {
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $keysIdentifier = $this->getCacheIdentifier($info['query'], 'keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, '__uid');
            $keys[] = $source['__uid'];
        }

        $context['cache']->set($keysIdentifier, $keys);
    }

    protected abstract function getTable(): string;

    protected abstract function getForeignKeyField(): string;

    protected function getValue(?array $value): ?array
    {
        $minimum = $this->getMultiplicityConstraint()->getMinimum();
        $maximum = $this->getMultiplicityConstraint()->getMaximum();

        if (empty($value)) {
            return $minimum > 0 || $maximum > 1 || $maximum === null ? [] : null;
        }

        return $maximum > 1 || $maximum === null ? $value : $value[0];
    }

    protected function getCacheIdentifier(string $query, string $identifier): string
    {
        return $query . '_' . $identifier;
    }

    protected function getBufferIndexes(array $row): array
    {
        return [
            $row['__' . $this->getForeignKeyField()],
        ];
    }

    protected function getBuilder(ResolveInfo $info, ?Context $context, string $table, array $keys): QueryBuilder
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $builder = $context === null ? GeneralUtility::makeInstance(
            QueryBuilder::class,
            $connection
        ) : GeneralUtility::makeInstance(
            ContextAwareQueryBuilder::class,
            $connection,
            $context
        );

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

        $columns[] = $builder->quote($table, ParameterType::STRING)
            . ' AS ' . $builder->quoteIdentifier(EntitySchemaFactory::ENTITY_TYPE_FIELD);

        return array_values($columns);
    }
}