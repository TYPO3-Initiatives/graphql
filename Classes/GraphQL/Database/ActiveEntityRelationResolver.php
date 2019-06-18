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

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        if ($source !== null) {
            Assert::keyExists($source, $this->getPropertyDefinition()->getName());

            foreach ($this->getForeignKeys((string)$source[$this->getPropertyDefinition()->getName()]) as $table => $identifier) {
                $keys[$table][] = $identifier;
            }

            foreach ($keys as $table => $identifiers) {
                $keys[$table] = array_keys(array_flip($identifiers));
            }
        }

        $context['cache']->set($keysIdentifier, $keys);
    }

    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array
    {
        Assert::keyExists($source, $this->getPropertyDefinition()->getName());
        Assert::keyExists($context, 'cache');
        Assert::isInstanceOf($context['cache'], FrontendInterface::class);

        $bufferIdentifier = $this->getCacheIdentifier('buffer');
        $buffer = $context['cache']->get($bufferIdentifier) ?: [];

        $keysIdentifier = $this->getCacheIdentifier('keys');
        $keys = $context['cache']->get($keysIdentifier) ?: [];

        $result = [];
        $tables = [];

        if (!$context['cache']->has($bufferIdentifier)) {
            $foreignKeyField = $this->getForeignKeyField();

            foreach ($this->getPropertyDefinition()->getRelationTableNames() as $table) {
                $builder = $this->getBuilder($arguments, $info, $table, $keys);
                $statement = $builder->execute();

                while ($row = $statement->fetch()) {
                    $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] = $table;
                    $buffer[$table][$row[$foreignKeyField]] = $row;
                }
            }

            $context['cache']->set($bufferIdentifier, $buffer);
        }

        foreach ($this->getForeignKeys((string)$source[$this->getPropertyDefinition()->getName()]) as $table => $identifier) {
            $tables[$table] = true;
            $result[] = $buffer[$table][$identifier];
        }

        $result = $this->orderResult($arguments, $info, array_keys($tables), $result);

        return $result;
    }

    protected function getCacheIdentifier($identifier): string
    {
        return \spl_object_hash($this) . '_' . $identifier;
    }

    protected function getBuilder(array $arguments, ResolveInfo $info, string $table, array $keys): QueryBuilder
    {
        $builder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);

        $builder->getRestrictions()
            ->removeAll();

        $builder->select(...$this->getColumns($info, $table))
            ->from($table);

        $condition = $this->getCondition($arguments, $info, $builder, $table, $keys);

        if (!empty($condition)) {
            $builder->where(...$condition);
        }

        foreach ($this->getOrderBy($arguments, $info, $table) as $item) {
            $builder->addSelect($item['field']);
            $builder->addOrderBy($item['field'], $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC');
        }

        return $builder;
    }

    protected function getCondition(array $arguments, ResolveInfo $info, QueryBuilder $builder, string $table, array $keys): array
    {
        $expression = $arguments[EntitySchemaFactory::FILTER_ARGUMENT_NAME] ?? null;

        $condition = GeneralUtility::makeInstance(FilterExpressionProcessor::class, $info, $expression, $builder)->process();
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
    protected function getColumns(ResolveInfo $info, string $table): array
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

    protected function orderResult(array $arguments, ResolveInfo $info, array $tables, array $rows): array
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