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

use Doctrine\DBAL\Connection;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;

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

        $meta = $event->getResolver()->getElement();
        
        if (!$meta instanceof EntityDefinition) {
            return;
        }

        $tables = QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_FROM);

        if (count($tables) > 1) {
            return;
        }

        $table = array_pop($tables);

        if (!isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])
            || !isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])
        ) {
            return;
        }

        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        $languageAspect = $this->getLanguageAspect($event);

        if ($languageAspect !== null && $languageAspect->getContentId() > 0) {
            switch ($languageAspect->getOverlayType()) {
                case LanguageAspect::OVERLAYS_OFF:
                    $builder->andWhere(
                        $builder->expr()->in(
                            $table . '.' . $languageField,
                            $builder->createNamedParameter(
                                [-1, $languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY
                            )
                        ),
                        $builder->expr()->eq(
                            $table . '.' . $translationParent,
                            $builder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT
                            )
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_MIXED:
                    $builder->leftJoin(
                        $table,
                        $table,
                        'language_overlay',
                        (string) $builder->expr()->eq(
                            $table . '.uid',
                            $builder->quoteIdentifier('language_overlay.' . $translationParent)
                        )
                    )->andWhere(
                        $builder->expr()->orX(
                            $builder->expr()->andX(
                                $builder->expr()->neq(
                                    $table . '.' . $translationParent,
                                    $builder->createNamedParameter(
                                        0,
                                        \PDO::PARAM_INT
                                    )
                                ),
                                $builder->expr()->eq(
                                    $table . '.' . $languageField,
                                    $builder->createNamedParameter(
                                        $languageAspect->getContentId(),
                                        \PDO::PARAM_INT
                                    )
                                )
                            ),
                            $builder->expr()->in(
                                $table . '.' . $languageField,
                                $builder->createNamedParameter(
                                    [-1, 0],
                                    Connection::PARAM_INT_ARRAY
                                )
                            )
                        ),
                        $builder->expr()->isNull(
                            'language_overlay.uid'
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_ON:
                    $builder->orWhere(
                        $builder->expr()->eq(
                            $table . '.' . $languageField,
                            $builder->createNamedParameter(
                                -1,
                                \PDO::PARAM_INT
                            )
                        ),
                        $builder->expr()->andX(
                            $builder->expr()->eq(
                                $table . '.' . $languageField,
                                $builder->createNamedParameter(
                                    $languageAspect->getContentId(),
                                    \PDO::PARAM_INT
                                )
                            ),
                            $builder->expr()->neq(
                                $table . '.' . $translationParent,
                                $builder->createNamedParameter(
                                    0,
                                    \PDO::PARAM_INT
                                )
                            )
                        )
                    );
                    $languages[] = 0;
                    break;
                case LanguageAspect::OVERLAYS_ON_WITH_FLOATING:
                    $builder->andWhere(
                        $builder->expr()->in(
                            $table . '.' . $languageField,
                            $builder->createNamedParameter(
                                [-1, $languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY
                            )
                        )
                    );
                    break;
            }
        } elseif ($languageAspect !== null && $languageAspect->getContentId() === 0) {
            $builder->andWhere(
                $builder->expr()->in(
                    $table . '.' . $languageField,
                    $builder->createNamedParameter(
                        [-1, $languageAspect->getContentId()],
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