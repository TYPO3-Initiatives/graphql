<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Validator;

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

use GraphQL\Error\Error;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\AbstractType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\GraphQL\ExpressionNodeVisitor;
use TYPO3\CMS\GraphQL\Type\OrderExpressionType;

/**
 * @internal
 */
class OrdersOnCorrectTypeRule extends ValidationRule
{
    /**
     * @var array
     */
    public $orderUsages;

    /**
     * @inheritdoc
     */
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->orderUsages = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) {
                    $operationName = $operation->name ? $operation->name->value : null;

                    foreach ($this->orderUsages as [$fieldName, $constraintName, $type, $node]) {
                        if (!$context->getSchema()->hasType($constraintName)) {
                            $context->reportError(new Error(
                                self::unknownConstraintMessage($constraint),
                                [$node]
                            ));
                            continue;
                        }

                        $constraintType = $context->getSchema()->getType($constraintName);

                        if ($constraintType instanceof WrappingType) {
                            $constraintType = $constraintType->getWrappedType(true);
                        }

                        if (Type::isLeafType($constraintType)) {
                            $context->reportError(new Error(
                                self::badConstraintMessage($constraintName),
                                [$node]
                            ));
                            continue;
                        }

                        if ($constraintType->name !== $type->name && (!$type instanceof AbstractType
                            || !$context->getSchema()->isPossibleType($type, $constraintType))
                        ) {
                            $context->reportError(new Error(
                                self::badConstraintMessage($constraintName),
                                [$node]
                            ));
                            continue;
                        }

                        if (!$constraintType->hasField($fieldName)) {
                            $context->reportError(new Error(
                                self::unknownFieldMessage($fieldName, $constraintName),
                                [$node]
                            ));
                            continue;
                        }
                    }
                },
            ],
            NodeKind::ARGUMENT => function ($argument) use ($context) {
                if ($context->getArgument()->getType() instanceof OrderExpressionType
                    && $argument->value->kind === NodeKind::STRING
                ) {
                    $expression = $context->getArgument()->getType()->parseValue($argument->value->value);

                    $visitor = new ExpressionNodeVisitor([
                        '#field' => function (TreeNode $node) use ($argument, $context) {
                            $type = $context->getType() instanceof WrappingType
                                ? $context->getType()->getWrappedType(true) : $context->getType();

                            $this->orderUsages[] = [
                                $node->getChild(0)->getChild(0)->getValueValue(),
                                $node->getChildrenNumber() < 3
                                    ? $type->name : $node->getChild(1)->getChild(0)->getValueValue(),
                                $type,
                                $argument,
                            ];
                            return false;
                        },
                    ]);

                    $visitor->visit($expression);
                }
            },
        ];
    }

    public static function badConstraintMessage($typeName)
    {
        return sprintf('Type "%s" can not be used in order clause constraint.', $typeName);
    }

    public static function badFieldMessage($fieldName)
    {
        return sprintf('Field "%s" can not be used in order clause.', $fieldName);
    }

    public static function unknownFieldMessage($fieldName, $typeName)
    {
        return sprintf('Type "%s" does not have any field named "%s".', $typeName, $fieldName);
    }

    public static function unknownConstraintMessage($typeName)
    {
        return sprintf('Type "%s" does not exist.', $typeName);
    }
}
