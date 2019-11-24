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

use TYPO3\CMS\Core\Utility\GeneralUtility;

class ColumnIdentifierCollection implements \IteratorAggregate
{
    /**
     * @var ColumnIdentifier[]
     */
    private $identifiers;

    public function __construct(ColumnIdentifier ...$identifiers)
    {
        $this->identifiers = $identifiers;
    }

    public function hasColumnName(TableIdentifier $tableIdentifier, string $columnName): bool
    {
        $tableName = $tableIdentifier->getAlias() ?? $tableIdentifier->getTableName();
        foreach ($this->identifiers as $identifier) {
            if ($identifier->getFieldName() !== $columnName) {
                continue;
            }
            // either `tableAlias.field` or just `field`
            if ($identifier->getTableName() === $tableName || $identifier->getTableName() === null) {
                return true;
            }
        }
        return false;
    }

    public function getIterator() {
        return new \ArrayIterator($this->identifiers);
    }
}
