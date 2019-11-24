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

class TableIdentifier
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var null|string
     */
    private $alias;

    public static function create(string $tableName, string $alias = null): self
    {
        return GeneralUtility::makeInstance(
            static::class,
            $tableName,
            $alias
        );
    }

    public function __construct(string $tableName, string $alias = null)
    {
        $this->tableName = $tableName;
        $this->alias = $alias;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return null|string
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
