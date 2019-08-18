<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Utility;

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

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use Doctrine\DBAL\Types\Type as DatabaseType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\Type;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\Core\Exception;

/**
 * @internal
 */
class TypeUtility
{
    public static function fromFilterExpressionValue(TreeNode $node, Type $default): Type
    {
        if ($node->getId() === '#list') {
            return new ListOfType(self::fromFilterExpressionValue($node->getChild(0), $default));
        }

        switch ($node->getValueToken()) {
            case 'string':
                return Type::string();
            case 'integer':
                return Type::int();
            case 'float':
                return Type::float();
            case 'boolean':
                return Type::boolean();
            case 'null':
                return $default;
        }

        throw new Exception('Unexpected value in filter expression.', 1566256204);
    }

    public static function mapDatabaseType(DatabaseType $type): Type
    {
        if ($type instanceof IntegerType || $type instanceof BigIntType || $type instanceof SmallIntType) {
            return Type::int();
        } else if ($type instanceof FloatType || $type instanceof DecimalType) {
            return Type::float();
        } else if ($type instanceof BooleanType) {
            return Type::boolean();
        }

        return Type::string();
    }
}
