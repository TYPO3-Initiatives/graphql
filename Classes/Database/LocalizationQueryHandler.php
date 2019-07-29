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

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;
use Doctrine\DBAL\Connection;

/**
 * @internal
 */
class LocalizationQueryHandler
{
    public function __invoke(BeforeValueResolvingEvent $event): void
    {
        $builder = $this->getQueryBuilder($event);

        if ($builder === null) {
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

        $tables = QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_FROM);

        if (count($tables) > 1) {
            return;
        }

        $table = array_pop($tables);

        if (!isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
            return;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationSource = $GLOBALS['TCA'][$table]['ctrl']['translationSource'];
        $languageAspect = $this->getLanguageAspect($event);
        $languages = [-1];

        if ($languageAspect !== null && $languageAspect->getContentId() > 0) {
            switch ($languageAspect->getOverlayType()) {
                case LanguageAspect::OVERLAYS_OFF:
                    $builder->andWhere(
                        $builder->expr()->eq(
                            $table . '.' . $translationSource,
                            $builder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT
                            )
                        )
                    );
                    $languages[] = $languageAspect->getContentId();
                    break;
                case LanguageAspect::OVERLAYS_MIXED:
                    $builder->leftJoin(
                        $table,
                        $table,
                        'language_overlay',
                        (string)$builder->expr()->eq(
                            $table . '.uid',
                            $builder->quoteIdentifier('language_overlay.' . $translationSource)
                        )
                    );
                    $languages[] = 0;
                    break;
                case LanguageAspect::OVERLAYS_ON:
                    $builder->innerJoin(
                        $table,
                        $table,
                        'language_overlay',
                        (string)$builder->expr()->eq(
                            $table . '.uid',
                            $builder->quoteIdentifier('language_overlay.' . $translationSource)
                        )
                    );
                    $languages[] = 0;
                    break;
                case LanguageAspect::OVERLAYS_ON_WITH_FLOATING:
                    $builder->leftJoin(
                        $table,
                        $table,
                        'language_overlay',
                        (string)$builder->expr()->eq(
                            $table . '.uid',
                            $builder->quoteIdentifier('language_overlay.' . $translationSource)
                        )
                    );
                    $builder->andWhere(
                        $builder->expr()->isNull('language_overlay.uid')
                    );
                    $languages[] = $languageAspect->getContentId();
                    break;
            }
        } elseif ($languageAspect !== null && $languageAspect->getContentId() === 0) {
            $languages[] = 0;
        }

        if ($languageAspect !== null) {
            $builder->andWhere(
                $builder->expr()->in(
                    $table . '.' . $languageField,
                    $builder->createNamedParameter(
                        $languages,
                        Connection::PARAM_INT_ARRAY
                    )
                )
            );
        }
    }

    protected function getLanguageAspect($event): ?LanguageAspect
    {
        $context = $event->getContext();

        if (isset($context['context']) && $context['context'] instanceof Context) {
            return $context['context']->getAspect('language');
        }

        return null;
    }

    protected function getQueryBuilder($event): ?QueryBuilder
    {
        $context = $event->getContext();

        if (isset($context['builder']) && $context['builder'] instanceof QueryBuilder) {
            return $context['builder'];
        }

        return null;
    }
}