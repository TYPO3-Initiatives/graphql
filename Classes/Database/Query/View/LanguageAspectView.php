<?php
declare(strict_types = 1);
namespace TYPO3\CMS\GraphQL\Database\Query\View;

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

use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\VersionMap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Database\Query\ColumnIdentifierCollection;
use TYPO3\CMS\GraphQL\Database\Query\TableIdentifier;

class LanguageAspectView implements QueryViewInterface
{
    /**
     * @var string
     */
    private const PARAMETER_PREFIX = ':_';

    /**
     * @var string
     */
    private const ALIAS_TABLE = 't';

    /**
     * @var string
     */
    private const ALIAS_OVERLAY = 'o';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LanguageAspect
     */
    private $languageAspect;

    /**
     * Initializes a new LanguageAspectView.
     * 
     * @param Connection $connection
     * @param WorkspaceAspect $workspaceAspect
     */
    public function __construct(Connection $connection, LanguageAspect $languageAspect)
    {
        $this->connection = $connection;
        $this->languageAspect = $languageAspect;
    }

    /**
     * @inheritdoc
     */
    public function buildQuery(TableIdentifier $tableIdentifier, ?ColumnIdentifierCollection $columnIdentifiers): ?QueryBuilder
    {
        $tableName = $tableIdentifier->getTableName();

        if (!$this->hasAspect($tableName) || $this->languageAspect->getContentId() < 0) {
            return null;
        }

        $queryBuilder = $this->getQueryBuilder()
            ->from($tableName, self::ALIAS_TABLE);

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $this->project($tableName, $columnIdentifiers, $queryBuilder);

        $languageField = $GLOBALS['TCA'][$tableName]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'];

        if ($this->languageAspect->getContentId() > 0) {
            switch ($this->languageAspect->getOverlayType()) {
                case LanguageAspect::OVERLAYS_OFF:
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->in(
                            self::ALIAS_TABLE . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                [-1, $this->languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('languageIdentifiers')
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            self::ALIAS_TABLE . '.' . $translationParent,
                            $queryBuilder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('languageDefault')
                            )
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_MIXED:
                    $queryBuilder->leftJoin(
                        self::ALIAS_TABLE,
                        $tableName,
                        self::ALIAS_OVERLAY,
                        (string) $queryBuilder->expr()->eq(
                            self::ALIAS_TABLE . '.uid',
                            $queryBuilder->quoteIdentifier('o.' . $translationParent)
                        )
                    )->andWhere(
                        $queryBuilder->expr()->orX(
                            $queryBuilder->expr()->andX(
                                $queryBuilder->expr()->neq(
                                    self::ALIAS_TABLE . '.' . $translationParent,
                                    $queryBuilder->createNamedParameter(
                                        0,
                                        \PDO::PARAM_INT,
                                        self::PARAMETER_PREFIX . md5('languageDefault')
                                    )
                                ),
                                $queryBuilder->expr()->eq(
                                    self::ALIAS_TABLE . '.' . $languageField,
                                    $queryBuilder->createNamedParameter(
                                        $this->languageAspect->getContentId(),
                                        \PDO::PARAM_INT,
                                        self::PARAMETER_PREFIX . md5('languageIdentifier')
                                    )
                                )
                            ),
                            $queryBuilder->expr()->in(
                                self::ALIAS_TABLE . '.' . $languageField,
                                $queryBuilder->createNamedParameter(
                                    [-1, 0],
                                    Connection::PARAM_INT_ARRAY,
                                    self::PARAMETER_PREFIX . md5('languageIdentifiers')
                                )
                            )
                        ),
                        $queryBuilder->expr()->isNull(
                            self::ALIAS_OVERLAY . '.uid'
                        )
                    );
                    break;
                case LanguageAspect::OVERLAYS_ON:
                    $queryBuilder->orWhere(
                        $queryBuilder->expr()->eq(
                            self::ALIAS_TABLE . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                -1,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('languageAll')
                            )
                        ),
                        $queryBuilder->expr()->andX(
                            $queryBuilder->expr()->eq(
                                self::ALIAS_TABLE . '.' . $languageField,
                                $queryBuilder->createNamedParameter(
                                    $this->languageAspect->getContentId(),
                                    \PDO::PARAM_INT,
                                    self::PARAMETER_PREFIX . md5('languageIdentifier')
                                )
                            ),
                            $queryBuilder->expr()->neq(
                                self::ALIAS_TABLE . '.' . $translationParent,
                                $queryBuilder->createNamedParameter(
                                    0,
                                    \PDO::PARAM_INT,
                                    self::PARAMETER_PREFIX . md5('languageDefault')
                                )
                            )
                        )
                    );
                    $languages[] = 0;
                    break;
                case LanguageAspect::OVERLAYS_ON_WITH_FLOATING:
                    $queryBuilder->andWhere(
                        $queryBuilder->expr()->in(
                            self::ALIAS_TABLE . '.' . $languageField,
                            $queryBuilder->createNamedParameter(
                                [-1, $this->languageAspect->getContentId()],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('languageIdentifiers')
                            )
                        )
                    );
                    break;
            }
        } elseif ($this->languageAspect->getContentId() === 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    self::ALIAS_TABLE . '.' . $languageField,
                    $queryBuilder->createNamedParameter(
                        [-1, 0],
                        Connection::PARAM_INT_ARRAY,
                        self::PARAMETER_PREFIX . md5('languageIdentifiers')
                    )
                )
            );
        }

        return $queryBuilder;
    }

    private function project(string $tableName, ?ColumnIdentifierCollection $columnIdentifiers, QueryBuilder $queryBuilder): QueryBuilder
    {
        $fieldNames = [];
        // As long as we do not have all columns used in the 
        // outer query we have to project them all.
        if ($columnIdentifiers === null) {
            $fieldNames = array_map(function ($tableColumn) {
                return $tableColumn->getName();
            }, GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable($tableName)
                ->getSchemaManager()
                ->listTableDetails($tableName)
                ->getColumns()
            );
        }

        foreach ($fieldNames as $fieldName) {
            $queryBuilder->addSelect(self::ALIAS_TABLE . '.' . $fieldName);
        }

        return $queryBuilder;
    }

    private function hasAspect(string $tableName): bool
    {
        return isset($GLOBALS['TCA'][$tableName]['ctrl']['languageField'])
            && isset($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField']);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(QueryBuilder::class, $this->connection);
    }
}
