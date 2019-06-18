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

use EmptyIterator;
use GraphQL\Type\Definition\ResolveInfo;
use Traversable;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use Webmozart\Assert\Assert;

class PassiveManyToManyEntityRelationResolver extends AbstractPassiveEntityRelationResolver
{
    public static function canResolve(PropertyDefinition $propertyDefinition)
    {
        return $propertyDefinition->isManyToManyRelationProperty();
    }

    protected function getTable(): string
    {
        return $this->getPropertyDefinition()->getManyToManyTableName();
    }

    protected function getType(array $source): string
    {
        Assert::keyExists($source, $this->getTable() . '_tablenames');

        return $source[$this->getTable() . '_tablenames'];
    }

    protected function getForeignKeyField(): string
    {
        return 'uid_local';
    }

    protected function getBuilder(array $arguments, array $context, ResolveInfo $info): QueryBuilder
    {
        $builder = parent::getBuilder($arguments, $context, $info);
        $tables = $this->getPropertyDefinition()->getRelationTableNames();
        $associativeTable = $this->getTable();

        foreach ($tables as $table) {
            $builder->leftJoin(
                $associativeTable,
                $table,
                $table,
                (string)$builder->expr()->andX(
                    $builder->expr()->eq(
                        $this->getColumnIdentifier(
                            $associativeTable,
                            'uid_foreign'
                        ),
                        $builder->quoteIdentifier($table . '.uid')
                    ),
                    $builder->expr()->eq(
                        $this->getColumnIdentifier(
                            $associativeTable,
                            'tablenames'
                        ),
                        $builder->createNamedParameter($table)
                    )
                )
            );
        }

        if (empty($arguments[EntitySchemaFactory::ORDER_ARGUMENT_NAME])) {
            $builder->orderBy(
                $this->getColumnIdentifier(
                    $associativeTable,
                    'sorting'
                )
            );
        } else {
            foreach ($this->getOrderBy($arguments, $info, ...$tables) as $item) {
                foreach (empty($item['constraint']) ? $tables : [$item['constraint']] as $table) {
                    $builder->addSelect(
                        $this->getColumnIdentifierForSelect($table, $item['field'])
                    );
                    $builder->addOrderBy(
                        $this->getColumnIdentifier($table, $item['field']),
                        $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC'
                    );
                }
            }
        }

        return $builder;
    }

    protected function getCondition(array $keys, QueryBuilder $builder, ResolveInfo $info): array
    {
        $condition = parent::getCondition($keys, $builder, $info);

        $propertyConfiguration = $this->getPropertyDefinition()->getConfiguration();
        $table = $this->getTable();

        foreach ($propertyConfiguration['config']['MM_match_fields'] as $field => $match) {
            $condition[] = $builder->expr()->eq(
                $this->getColumnIdentifier($table, $field),
                $builder->createNamedParameter($match)
            );
        }

        $condition[] = $builder->expr()->eq(
            $this->getColumnIdentifier($table, 'fieldname'),
            $builder->createNamedParameter($this->getPropertyDefinition()->getName())
        );

        return $condition;
    }

    protected function getColumns(ResolveInfo $info): array
    {
        $columns = parent::getColumns($info);

        $columns[] = $this->getColumnIdentifierForSelect($this->getTable(), 'tablenames');

        return $columns;
    }

    protected function getOrderBy(array $arguments, ResolveInfo $info, string $table): Traversable
    {
        // do not apply order on the association table
        return $table === $this->getTable() ? new EmptyIterator() : parent::getOrderBy($arguments, $info, $table);
    }
}