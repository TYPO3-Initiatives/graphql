<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Type;

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

use GraphQL\Type\Definition\ScalarType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\OrderExpressionParser;

/**
 * @internal
 */
class OrderExpressionType extends ScalarType
{
    /**
     * @var OrderExpressionType
     */
    protected static $instance;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new OrderExpressionType();
        }

        return self::$instance;
    }

    public function serialize($value)
    {
        return $value;
    }

    public function parseValue($value)
    {
        return GeneralUtility::makeInstance(OrderExpressionParser::class)->parse($value);
    }

    public function parseLiteral($valueNode, array $variables = null)
    {
        return GeneralUtility::makeInstance(OrderExpressionParser::class)->parse($valueNode->value);
    }
}