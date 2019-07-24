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

use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;

/**
 * @internal
 */
class OrderQueryHandler
{
    /**
     * @todo Only use an expression item in SQL if its type conditon matches.
     * @todo Log a warning if an order item can not added.
     */
    public function __invoke(BeforeValueResolvingEvent $event): void
    {
        $context = $event->getContext();

        if (!isset($context['builder']) || !$context['builder'] instanceof QueryBuilder) {
            return;
        }

        $type = $event->getResolver()->getType();

        if (!isset($type->config['meta'])) {
            return;
        }
        
        $meta = $type->config['meta'];
        
        if (!$meta instanceof PropertyDefinition && !$meta instanceof EntityDefinition) {
            return;
        }

        $arguments = $event->getArguments();
        $builder = $context['builder'];
        $tables = array_flip(QueryHelper::getQueriedTables($builder));
        $expression = $arguments[OrderArgumentProvider::ARGUMENT_NAME] ?? null;

        GeneralUtility::makeInstance(
            OrderExpressionValidator::class,
            $type,
            $expression,
            $event->getInfo()
        )->validate();

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
}