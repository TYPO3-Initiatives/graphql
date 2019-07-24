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
class FilterQueryHandler
{
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
        $expression = $arguments[FilterArgumentProvider::ARGUMENT_NAME] ?? null;
        
        $processor = GeneralUtility::makeInstance(
            FilterExpressionProcessor::class,
            $event->getInfo(),
            $expression,
            $builder
        );

        $condition = $processor->process();

        if ($condition !== null) {
            $builder->andWhere($condition);
        }
    }
}