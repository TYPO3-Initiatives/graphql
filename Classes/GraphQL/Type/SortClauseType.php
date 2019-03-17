<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Type;

use GraphQL\Type\Definition\ScalarType;

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

class SortClauseType extends ScalarType
{
    protected static $instance;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new SortClauseType();
        }

        return self::$instance;
    }

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        $parsed = [];
        $items = explode(',', $value);

        foreach ($items as $item) {
            list($field, $order) = preg_split('/\s+/', trim($item));
            $parsed[] = [
                'field' => $field,
                'order' => strtolower($order) ?? 'ascending'
            ];
        }

        return $parsed;
    }

    public function parseLiteral($valueNode, array $variables = null)
    {
        $parsed = [];
        $items = explode(',', $valueNode->value);

        foreach ($items as $item) {
            list($field, $order) = preg_split('/\s+/', trim($item));
            $parsed[] = [
                'field' => $field,
                'order' => strtolower($order) ?? 'ascending'
            ];
        }

        return $parsed;
    }
}