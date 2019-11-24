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

use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\VersionMap;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Database\Query\ColumnIdentifierCollection;
use TYPO3\CMS\GraphQL\Database\Query\TableIdentifier;

class WorkspaceAspectView implements QueryViewInterface
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
    private const ALIAS_VERSION = 'v';

    /**
     * @var string
     */
    private const ALIAS_PLACEHOLDER = 'p';

    /**
     * @var string
     */
    private const ALIAS_ORIGINAL = 'o';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var WorkspaceAspect
     */
    private $workspaceAspect;

    /**
     * Initializes a new WorkspaceAspectView.
     * 
     * @param Connection $connection
     * @param WorkspaceAspect $workspaceAspect
     */
    public function __construct(Connection $connection, WorkspaceAspect $workspaceAspect)
    {
        $this->connection = $connection;
        $this->workspaceAspect = $workspaceAspect;
    }

    /**
     * @inheritdoc
     */
    public function buildQuery(TableIdentifier $tableIdentifier, ?ColumnIdentifierCollection $columnIdentifiers): ?QueryBuilder
    {
        $tableName = $tableIdentifier->getTableName();
        $workspaceId = (int) $this->workspaceAspect->getId();

        if (!$this->hasAspect($tableName)) {
            return null;
        }

        $queryBuilder = $this->getQueryBuilder()
            ->from($tableName, self::ALIAS_TABLE);

        $queryBuilder
            ->getRestrictions()
            ->removeAll();

        $this->project($tableName, $columnIdentifiers, $queryBuilder);

        $queryBuilder
            ->leftJoin(
                self::ALIAS_TABLE,
                $tableName,
                self::ALIAS_VERSION,
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.uid',
                        $queryBuilder->quoteIdentifier(self::ALIAS_VERSION . '.t3ver_oid')
                    ),
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            0,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        self::ALIAS_VERSION . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    )
                )
            )
            ->leftJoin(
                self::ALIAS_TABLE,
                $tableName,
                self::ALIAS_ORIGINAL,
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.t3ver_oid',
                        $queryBuilder->quoteIdentifier(self::ALIAS_ORIGINAL . '.uid')
                    ),
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        self::ALIAS_ORIGINAL . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            self::PARAMETER_PREFIX . md5('workspaceIdentifiers')
                        )
                    )
                )
            )
            ->leftJoin(
                self::ALIAS_TABLE,
                $tableName,
                self::ALIAS_PLACEHOLDER,
                (string) $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.t3ver_oid',
                        $queryBuilder->quoteIdentifier(self::ALIAS_PLACEHOLDER . '.t3ver_move_id')
                    ),
                    $queryBuilder->expr()->neq(
                        self::ALIAS_PLACEHOLDER . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            0,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceLive')
                        )
                    ),
                    $queryBuilder->expr()->eq(
                        self::ALIAS_TABLE . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            $workspaceId,
                            \PDO::PARAM_INT,
                            self::PARAMETER_PREFIX . md5('workspaceContext')
                        )
                    ),
                    $queryBuilder->expr()->in(
                        self::ALIAS_PLACEHOLDER . '.t3ver_wsid',
                        $queryBuilder->createNamedParameter(
                            [0, $workspaceId],
                            Connection::PARAM_INT_ARRAY,
                            self::PARAMETER_PREFIX . md5('workspaceIdentifiers')
                        )
                    )
                )
            )
            ->andWhere(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq(
                            self::ALIAS_TABLE . '.t3ver_wsid',
                            $queryBuilder->createNamedParameter(
                                0,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('workspaceLive')
                            )
                        ),
                        $queryBuilder->expr()->isNull(
                            self::ALIAS_VERSION . '.uid'
                        )
                    ),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->eq(
                            self::ALIAS_TABLE . '.t3ver_wsid',
                            $queryBuilder->createNamedParameter(
                                $workspaceId,
                                \PDO::PARAM_INT,
                                self::PARAMETER_PREFIX . md5('workspaceContext')
                            )
                        ),
                        $queryBuilder->expr()->notIn(
                            self::ALIAS_TABLE . '.t3ver_state',
                            $queryBuilder->createNamedParameter(
                                [1, 3],
                                Connection::PARAM_INT_ARRAY,
                                self::PARAMETER_PREFIX . md5('workspaceStates')
                            )
                        )
                    )
                )
            );

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

        if ($this->hasAspect($tableName)) {
            $fieldLiterals = [
                'uid' => [
                    'literal' => 'COALESCE(%s,%s)',
                    'identifiers' => [self::ALIAS_ORIGINAL . '.uid', self::ALIAS_TABLE . '.uid'],
                ],
                'pid' => [
                    'literal' => 'COALESCE(%s,%s,%s)',
                    'identifiers' => [self::ALIAS_PLACEHOLDER . '.pid', self::ALIAS_ORIGINAL . '.pid', self::ALIAS_TABLE . '.pid'],
                ],
                'deleted' => [
                    'literal' => 'CASE WHEN %s = 2 THEN 1 ELSE %s END',
                    'identifiers' => [self::ALIAS_TABLE . '.t3ver_state', self::ALIAS_TABLE . '.deleted'],
                ],
                'sorting' => [
                    'literal' => 'COALESCE(%s,%s)',
                    'identifiers' => [self::ALIAS_PLACEHOLDER . '.sorting', self::ALIAS_TABLE . '.sorting'],
                ],
            ];

            foreach ($fieldNames as $fieldName) {
                if (isset($fieldLiterals[$fieldName])) {
                    $queryBuilder->addSelectLiteral(
                        sprintf(
                            $fieldLiterals[$fieldName]['literal'] 
                                . ' AS ' . $queryBuilder->quoteIdentifier($fieldName),
                            ...array_map(function($identifier) use ($queryBuilder) {
                                return $queryBuilder->quoteIdentifier($identifier);
                            }, $fieldLiterals[$fieldName]['identifiers'])
                        )
                    );
                } else {
                    $queryBuilder->addSelect(self::ALIAS_TABLE . '.' . $fieldName);
                }
            }
        } else {
            foreach ($fieldNames as $fieldName) {
                $queryBuilder->addSelect(self::ALIAS_TABLE . '.' . $fieldName);
            }
        }

        return $queryBuilder;
    }

    private function hasAspect(string $tableName): bool
    {
        return isset($GLOBALS['TCA'][$tableName]['ctrl']['versioningWS']);
    }

    private function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(QueryBuilder::class, $this->connection);
    }
}
