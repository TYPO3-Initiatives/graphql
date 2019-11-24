<?php
declare(strict_types = 1);
namespace TYPO3\CMS\GraphQL\Database\Query;

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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\RecordRestrictionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Database\Query\View\LanguageAspectView;
use TYPO3\CMS\GraphQL\Database\Query\View\WorkspaceAspectView;

/**
 * Object oriented approach to building context aware SQL queries.
 *
 * It's an advanced query builder by taking into account the context - that is 
 * the proper fetching of resolved language and workspace records, if given.
 * 
 * @api
 */
final class ContextAwareQueryBuilder extends QueryBuilder
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var TableIdentifier
     */
    private $tableIdentifier;

    /**
     * Initializes a new context aware query builder.
     *
     * @param Connection $connection
     * @param Context $context
     * @param QueryRestrictionContainerInterface $restrictionContainer
     * @param \Doctrine\DBAL\Query\QueryBuilder $concreteQueryBuilder
     * @param array $additionalRestrictions
     */
    public function __construct(
        Connection $connection,
        Context $context,
        QueryRestrictionContainerInterface $restrictionContainer = null,
        \Doctrine\DBAL\Query\QueryBuilder $concreteQueryBuilder = null,
        array $additionalRestrictions = null
    ) {
        parent::__construct($connection, $restrictionContainer, $concreteQueryBuilder, $additionalRestrictions);
        $this->context = $context;
    }

    /**
     * @inheritdoc
     */
    public function from(string $from, string $alias = null): QueryBuilder
    {
        $this->tableIdentifier = TableIdentifier::create($from, $alias);

        return parent::from($from, $alias);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if ($this->getType() !== \Doctrine\DBAL\Query\QueryBuilder::SELECT) {
            return parent::execute();
        }

        $inlineViewQueries = $this->getInlineViewQueries();
        $innerQuery = end($inlineViewQueries);

        while ($outerQuery = prev($inlineViewQueries)) {
            $innerQuery = $this->mergeInlineView($outerQuery, $innerQuery);
        }

        if ($innerQuery !== false) {
            $this->mergeInlineView($this, $innerQuery);
        }

        $originalWhereConditions = $this->addAdditionalWhereConditions();

        $result = $this->concreteQueryBuilder->execute();

        $this->concreteQueryBuilder->add('where', $originalWhereConditions, false);

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function getQueriedTables(): array
    {
        $queriedTables = parent::getQueriedTables();
        // Fakes the table name of the from clause otherwise the query restrictions won't work.
        $tableAlias = $this->tableIdentifier->getAlias() ?? $this->tableIdentifier->getTableName();
        $queriedTables[$tableAlias] = $this->tableIdentifier->getTableName();

        return $queriedTables;
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        parent::__clone();
        $this->context = clone $this->context;
    }

    /**
     * @todo Make views plugable like restrictions.
     * @todo Collect all column identifier used by the projection and selection.
     */
    private function getInlineViewQueries(): array
    {
        $inlineViewQueries = [];

        if ($this->context->hasAspect('workspace')) {
            $workspaceAspectView = new WorkspaceAspectView($this->connection, $this->context->getAspect('workspace'));
            $inlineViewQueries[] = $workspaceAspectView->buildQuery($this->tableIdentifier, null);
        }

        if ($this->context->hasAspect('language')) {
            $languageAspectView = new LanguageAspectView($this->connection, $this->context->getAspect('language'));
            $inlineViewQueries[] = $languageAspectView->buildQuery($this->tableIdentifier, null);
        }

        return array_filter($inlineViewQueries);
    }

    /**
     * @todo Throw exception when an inner parameter is already set
     */
    private function mergeInlineView(QueryBuilder $outerQueryBuilder, QueryBuilder $innerQueryBuilder): QueryBuilder
    {
        $outerFromPart = $outerQueryBuilder->getQueryPart('from');

        if (
            !isset($outerFromPart[0]['table']) 
            || $outerFromPart[0]['table'] !== $this->quoteIdentifier($this->tableIdentifier->getTableName())
        ) {
            throw new \Exception('Inline view is not compatible with outer query.', 1574599278);
        }

        $outerQueryBuilder->add(
            'from',
            [
                [
                    'table' => sprintf('(%s)', $innerQueryBuilder->getSQL()), 
                    'alias' => $outerFromPart[0]['alias'] ?? $outerFromPart[0]['table']
                ]
            ],
            false
        );

        foreach ($innerQueryBuilder->getParameters() as $key => $value) {
            $outerQueryBuilder->setParameter(
                $key,
                $value,
                $innerQueryBuilder->getParameterType($key)
            );
        }

        return $outerQueryBuilder;
    }
}
