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
use TYPO3\CMS\GraphQL\Event\BeforeFieldArgumentsInitializationEvent;
use TYPO3\CMS\GraphQL\Type\OrderExpressionType;

/**
 * @internal
 */
class OrderArgumentProvider
{
    /**
     * @var string
     */
    const ARGUMENT_NAME = 'order';

    public function __invoke(BeforeFieldArgumentsInitializationEvent $event): void
    {
        $type = $event->getType();

        if (!isset($type->config['meta'])) {
            return;
        }
        
        $meta = $type->config['meta'];
        
        if (!$meta instanceof PropertyDefinition && !$meta instanceof EntityDefinition) {
            return;
        }

        $event->addArgument(self::ARGUMENT_NAME, OrderExpressionType::instance());
    }
}