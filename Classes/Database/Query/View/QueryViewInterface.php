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

use TYPO3\CMS\GraphQL\Database\Query\ColumnIdentifierCollection;
use TYPO3\CMS\GraphQL\Database\Query\TableIdentifier;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * The main view interface. All views must implement this.
 */
interface QueryViewInterface
{
    /**
     * Builds a query for the view.
     * 
     * @param TableIdentifier $tableIdentifier
     * @param ColumnIdentifierCollection $columnIdentifiers
     */
    public function buildQuery(TableIdentifier $tableIdentifier, ?ColumnIdentifierCollection $columnIdentifiers): ?QueryBuilder;
}