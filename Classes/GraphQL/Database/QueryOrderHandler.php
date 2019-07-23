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
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\AbstractResolverHandler;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\GraphQL\ResolverInterface;
use TYPO3\CMS\Core\GraphQL\Type\OrderExpressionType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class QueryOrderHandler extends AbstractResolverHandler
{
    /**
     * @var string
     */
    const ARGUMENT_NAME = 'order';

    /**
     * @inheritdoc
     */
    public static function canHandle(ResolverInterface $resolver): bool
    {
        $type = $resolver->getType();

        if (!isset($type->config['meta'])) {
            return false;
        }
        
        if (!$type->config['meta'] instanceof PropertyDefinition
            && !$type->config['meta'] instanceof EntityDefinition
        ) {
            return false;
        }

        return true;
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

    /**
     * @inheritdoc
     * @todo Only use an expression item in SQL if its type conditon matches.
     * @todo Log a warning if an order item can not added.
     */
    public function onResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        Assert::keyExists($context, 'builder');
        Assert::isInstanceOf($context['builder'], QueryBuilder::class);

        $builder = $context['builder'];
        $tables = array_flip(QueryHelper::getQueriedTables($builder));
        $type = $this->resolver->getType();
        $meta = $type->config['meta'];
        $expression = $arguments[self::ARGUMENT_NAME] ?? null;

        GeneralUtility::makeInstance(OrderExpressionValidator::class, $type, $expression, $info)->validate();

        $items = GeneralUtility::makeInstance(OrderExpressionTraversable::class, $type, $expression);

        foreach ($items as $item) {
            if (!isset($tables[$item['constraint']])) {
                continue;
            }
            
            if ($meta instanceof PropertyDefinition && count($meta->getRelationTableNames()) > 1) {
                $builder->addSelect($item['constraint'] . '.' . $item['field'] . ' AS __' . $item['field']);
            } else {
                $builder->addOrderBy(
                    $item['constraint'] . '.' . $item['field'],
                    $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? 'ASC' : 'DESC'
                );
            }
        }
    }

    /**
     * @inheritdoc
     * @todo Check the wanted behaviour for unormalized relationships without an order expression
     */
    public function onResolved($value, $source, array $arguments, array $context, ResolveInfo $info)
    {
        if (empty($value)) {
            return $value;
        }

        $type = $this->resolver->getType();
        $meta = $type->config['meta'];
        $expression = $arguments[self::ARGUMENT_NAME] ?? null;

        // sort only when more than one table were fetched
        if ($meta instanceof EntityDefinition || $meta instanceof PropertyDefinition
            && count($meta->getRelationTableNames()) === 1
        ) {
            return $value;
        }

        // do not sort when no expression is given and its an unormalized relationship
        if ($expression === null && $this->resolver instanceof ActiveRelationshipResolver) {
            return $value;
        }

        $items = GeneralUtility::makeInstance(
            OrderExpressionTraversable::class,
            $type,
            $expression,
            OrderExpressionTraversable::MODE_GQL
        );
        $arguments = [];

        foreach ($items as $item) {
            array_push($arguments, array_map(function ($row) use ($item) {
                return !$item['constraint'] || $row[EntitySchemaFactory::ENTITY_TYPE_FIELD] === $item['constraint']
                    ? $row['__' . $item['field']] : null;
            }, $value), $item['order'] === OrderExpressionTraversable::ORDER_ASCENDING ? SORT_ASC : SORT_DESC);
        }

        array_push($arguments, $value);
        array_multisort(...$arguments);

        return array_pop($arguments);
    }
}