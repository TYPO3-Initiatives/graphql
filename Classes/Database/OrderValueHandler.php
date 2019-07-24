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

use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\GraphQL\Event\AfterValueResolvingEvent;

/**
 * @internal
 */
class OrderValueHandler
{
    /**
     * @todo Check the wanted behaviour for unormalized relationships without an order expression
     */
    public function __invoke(AfterValueResolvingEvent $event): void
    {
        $value = $event->getValue();

        if (empty($value)) {
            return;
        }

        $type = $event->getResolver()->getType();

        if (!isset($type->config['meta'])) {
            return;
        }
        
        $meta = $type->config['meta'];
        
        if (!$meta instanceof PropertyDefinition) {
            return;
        }

        $arguments = $event->getArguments();
        $expression = $arguments[OrderArgumentProvider::ARGUMENT_NAME] ?? null;

        // sort only when more than one table were fetched
        if (count($meta->getRelationTableNames()) === 1) {
            return;
        }

        // do not sort when no expression is given and its an unormalized relationship
        if ($expression === null && $event->getResolver() instanceof ActiveRelationshipResolver) {
            return;
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

        $event->setValue(array_pop($arguments));
    }
}