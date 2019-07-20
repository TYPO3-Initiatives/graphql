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
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\GraphQL\ResolverHandlerInterface;
use TYPO3\CMS\Core\GraphQL\Type\OrderExpressionType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\ActivePropertyRelation;

/**
 * @internal
 */
class EntityOrderHandler implements ResolverHandlerInterface
{
    /**
     * @var string
     */
    const ARGUMENT_NAME = 'order';

    /**
     * @inheritdoc
     */
    public function beforeResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        Assert::keyExists($context, 'builder');
        Assert::keyExists($context, 'tables');
        Assert::keyExists($context, 'meta');
        Assert::isInstanceOf($context['builder'], QueryBuilder::class);

        $builder = $context['builder'];
        $tables = $context['tables'];
        $meta = $context['meta'];
        $expression = $arguments[self::ARGUMENT_NAME] ?? null;

        if ($meta instanceof PropertyDefinition && $meta->isManyToManyRelationProperty() && $expression === null) {
            $builder->orderBy(
                $this->getColumnIdentifier(
                    $meta->getManyToManyTableName(),
                    'sorting'
                )
            );
        } else {
            OrderExpressionValidator::validate($info, $expression, ...$tables);

            $items = GeneralUtility::makeInstance(OrderExpressionTraversable::class, $info, $expression, ...$tables);

            foreach ($items as $item) {
                foreach (empty($item['constraint']) ? $tables : [$item['constraint']] as $table) {
                    $builder->addSelect(
                        count($tables) > 1 ? $this->getColumnIdentifierForSelect($table, $item['field']) : $item['field']
                    );
                    $builder->addOrderBy(
                        count($tables) > 1 ? $this->getColumnIdentifier($table, $item['field']) : $item['field'],
                        $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC'
                    );
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function afterResolve($source, array $arguments, array $context, ResolveInfo $info, ?array $data): ?array
    {
        Assert::keyExists($context, 'tables');
        Assert::keyExists($context, 'meta');

        $tables = $context['tables'];
        $meta = $context['meta'];
        $expression = $arguments[self::ARGUMENT_NAME] ?? null;

        if ($expression !== null && $meta instanceof PropertyDefinition 
            && !$meta->isManyToManyRelationProperty() 
            && empty(array_filter($meta->getActiveRelations(), function($relation) {
                return $relation instanceof ActivePropertyRelation;
            }))
        ) {
            $items = GeneralUtility::makeInstance(OrderExpressionTraversable::class, $info, $expression, ...$tables);
            $arguments = [];

            foreach ($items as $item) {
                array_push($arguments, array_map(function ($row) use ($item) {
                    return !$item['constraint'] || $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] === $item['constraint']
                        ? $row[$item['field']] : null;
                }, $data), $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? SORT_ASC : SORT_DESC);
            }

            array_push($arguments, $data);
            array_multisort(...$arguments);
    
            return array_pop($arguments);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        return [
            [
                'name' => self::ARGUMENT_NAME,
                'type' => OrderExpressionType::instance(),
            ],
        ];
    }

    protected function getColumnAlias(string $table, string $column): string
    {
        return sprintf('%s_%s', $table, $column);
    }

    protected function getColumnIdentifier(string $table, string $column): string
    {
        return sprintf('%s.%s', $table, $column);
    }

    protected function getColumnIdentifierForSelect(string $table, string $column): string
    {
        return sprintf(
            '%s AS %s',
            $this->getColumnIdentifier($table, $column),
            $this->getColumnAlias($table, $column)
        );
    }
}