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
use TYPO3\CMS\Core\Database\Query\Restriction\DefaultRestrictionContainer;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\EndTimeRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\StartTimeRestriction;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;

/**
 * @internal
 */
class RestrictionsQueryHandler
{
    public function __invoke(BeforeValueResolvingEvent $event): void
    {
        $context = $event->getContext();

        if (!isset($context['builder']) || !$context['builder'] instanceof QueryBuilder) {
            return;
        }

        $meta = $event->getResolver()->getElement();

        if (!$meta instanceof PropertyDefinition && !$meta instanceof EntityDefinition) {
            return;
        }

        $arguments = $event->getArguments();
        $builder = $context['builder'];

        if (isset($arguments[RestrictionsArgumentProvider::ARGUMENT_NAME])
            && $arguments[RestrictionsArgumentProvider::ARGUMENT_NAME] !== null
        ) {
            $restrictionsContainer = $arguments[RestrictionsArgumentProvider::ARGUMENT_NAME];

            foreach ($restrictionsContainer as $restrictionName => $restrictionArguments) {
                if ($restrictionName === 'deleted' && $restrictionArguments !== null) {
                    $builder->getRestrictions()->add(new DeletedRestriction());
                } elseif ($restrictionName === 'end_time' && $restrictionArguments !== null) {
                    $builder->getRestrictions()->add(new EndTimeRestriction(
                        $restrictionArguments['access_time_stamp'] ?? null
                    ));
                } elseif ($restrictionName === 'hidden' && $restrictionArguments !== null) {
                    $builder->getRestrictions()->add(new HiddenRestriction());
                } elseif ($restrictionName === 'start_time' && $restrictionArguments !== null) {
                    $builder->getRestrictions()->add(new StartTimeRestriction(
                        $restrictionArguments['access_time_stamp'] ?? null
                    ));
                } 
            }
        } elseif (!isset($arguments[RestrictionsArgumentProvider::ARGUMENT_NAME])) {
            $builder->setRestrictions(new DefaultRestrictionContainer());
        }
    }
}