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

class ColumnIdentifier
{
    /**
     * @var string
     */
    private $columnName;

    /**
     * @var null|string
     */
    private $alias;

    /**
     * @var null|string
     */
    private $tableName;

    /**
     * @var null|string
     */
    private $database;

    public static function fromSelectExpression(string $expression): self
    {
        $expression = str_replace(["\t", "\n", '  '], ' ', $expression);
        $expression = str_ireplace(' as ', ' AS ', $expression);

        // ensure all three indexes are set using `array_pad`
        list($identifier, $alias, $suffix) = array_pad(
            GeneralUtility::trimExplode(' AS ', $expression, true, 3),
            3,
            null
        );
        if (!empty($suffix)) {
            throw new \InvalidArgumentException(
                'SelectIdentifier::fromExpression() could not parse the expression ' . $expression . '.',
                1567606567
            );
        }

        if (strpos($identifier, '.') === false) {
            $columnName = $identifier;
        } else {
            list($prefix, $database, $tableName, $columnName) = array_pad(
                explode('.', $identifier, 4),
                -4,
                null
            );
            if (!empty($suffix)) {
                throw new \InvalidArgumentException(
                    'ColumnIdentifier::fromSelectExpression() could not parse the expression ' . $expression . '.',
                    1567606568
                );
            }
        }

        return GeneralUtility::makeInstance(
            static::class,
            $columnName,
            $alias ?? null,
            $tableName ?? null,
            $database ?? null
        );
    }

    public function __construct(string $columnName, string $alias = null, string $tableName = null, string $database = null)
    {
        $this->columnName = $columnName;
        $this->alias = $alias;
        $this->tableName = $tableName;
        $this->database = $database;
    }

    public function quoteForSelect(\Closure $handler): string
    {
        // The SQL * operator must not be quoted. As it can only occur either by itself
        // or preceded by a tablename (tablename.*) check if the last character of a select
        // expression is the * and quote only prepended table name. In all other cases the
        // full expression is being quoted.
        $values = [];
        if ($this->database !== null) {
            $values[] = $handler($this->database);
        }
        if ($this->tableName !== null) {
            $values[] = $handler($this->tableName);
        }
        if ($this->columnName !== '*') {
            $values[] = $handler($this->columnName);
        } else {
            $values[] = $this->columnName;
        }
        $identifier = implode('.', $values);
        // Quote the alias for the current fieldName, if given
        if (!empty($alias)) {
            $identifier .= ' AS ' . $handler($this->alias);
        }
        return $identifier;
    }

    /**
     * @return string
     */
    public function getColumnName(): string
    {
        return $this->fieldName;
    }

    /**
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @return string|null
     */
    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    /**
     * @return string|null
     */
    public function getDatabase(): ?string
    {
        return $this->database;
    }
}
