<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL\Database;

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

use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\Core\GraphQL\Exception\NotSupportedException;
use TYPO3\CMS\Core\GraphQL\Exception\SchemaException;
use Webmozart\Assert\Assert;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;

class OrderExpressionValidator
{
    public static function validate(ResolveInfo $info, ?TreeNode $expression, string ...$types)
    {
        Assert::notEmpty($types);
        Assert::keyExists($info->returnType->config, 'meta');
        Assert::isInstanceOfAny($info->returnType->config['meta'], [EntityDefinition::class, PropertyDefinition::class]);

        if ($expression === null) {
            return;
        }

        $schema = $info->schema;
        $meta = $info->returnType->config['meta'];

        foreach ($expression->getChildren() as $item) {
            $constraint = $item->getChildrenNumber() > 2 ? [$item->getChild(1)->getChild(0)->getValueValue()] : $types;
            $path = $item->getChild(0);
            $field = $path->getChild(0)->getValueValue();

            foreach ($constraint as $type) {
                if (!$schema->hasType($type)) {
                    throw new SchemaException(sprintf('Unknown type "%s" in order clause constraint', $type), 1560598849);
                }

                if (Type::isLeafType($schema->getType($type))) {
                    throw new NotSupportedException(sprintf('Leaf type "%s" is not supported in order clause constraint', $type), 1560598849);
                }

                if (Type::isAbstractType($schema->getType($type))) {
                    throw new NotSupportedException(sprintf('Abstract type "%s" is not supported in order clause constraint', $type), 1560648120);
                }

                if (!$schema->getType($type)->hasField($field)) {
                    throw new SchemaException(sprintf('Unknown field "%s" in order clause', $field), 1560645175);
                }

                if (count($path->getChildren()) > 1 || Type::isCompositeType($schema->getType($type)->getField($field)->getType())) {
                    throw new NotSupportedException(sprintf('Composite field "%s" is not supported in order clause', $field), 1560598442);
                }

                if ($meta instanceof EntityDefinition && $meta->getName() !== $type || $meta instanceof PropertyDefinition && !$meta->hasActiveRelationTo($type)) {
                    throw new SchemaException(sprintf('Type "%s" is out of scope in order clause', $type), 1560655028);
                }
            }
        }
    }
}