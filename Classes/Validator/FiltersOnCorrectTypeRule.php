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
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Type\Definition\CompositeType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\WrappingType;
use GraphQL\Utils\AST;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\GraphQL\ExpressionNodeVisitor;
use TYPO3\CMS\GraphQL\Type\FilterExpressionType;
use TYPO3\CMS\GraphQL\Utility\TypeUtility;

/**
 * @internal
 */
class FiltersOnCorrectTypeRule extends ValidationRule
{
    /**
     * @var array
     */
    public $variableDefinitions;

    /**
     * @var array
     */
    public $filterUsages;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->variableDefinitions = [];
                    $this->filterUsages = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) {
                    $operationName = $operation->name ? $operation->name->value : null;

                    foreach ($this->filterUsages as [$fieldName, $operationName, $operandNode, $type, $argument]) {
                        if (!$this->isValidField($context, $type, $argument, $fieldName)) {
                            continue;
                        }

                        $fieldType = $this->getFieldType($type, $fieldName);

                        if ($operandNode->getId() === '#variable') {
                            $operandName = $operandNode->getChild(0)->getValueValue();
                            
                            $operandType = AST::typeFromAST(
                                $context->getSchema(),
                                $this->variableDefinitions[$operandName]->type
                            );
                        } elseif ($operandNode->getId() === '#field') {
                            $operandName = $node->getChild(0)->getChild(0)->getValueValue();

                            if (!$this->isValidField($context, $type, $fieldName)) {
                                continue;
                            }

                            $operandType = $this->getFieldType($type, $operandName);
                        } else {
                            $operandType = TypeUtility::fromFilterExpressionValue($operandNode, $fieldType);
                        }

                        if ($fieldType->name !== Type::ID && $operationName !== '#in'
                            && $fieldType->name !== $operandType->name
                        ) {
                            $context->reportError(new Error(
                                self::fieldMismatchMessage($fieldName, $operandType->name),
                                [$argument]
                            ));
                            continue;
                        }

                        if ($fieldType->name === Type::ID && $operationName !== '#in'
                            && !in_array($operandType->name, [Type::INT, Type::STRING])
                        ) {
                            $context->reportError(new Error(
                                self::fieldMismatchMessage($fieldName, $operandType->name),
                                [$argument]
                            ));
                            continue;
                        }

                        if ($operationName === '#match' && $operandType->name !== Type::STRING) {
                            $context->reportError(new Error(
                                self::operationMismatchMessage($operandType->name, $operationName),
                                [$argument]
                            ));
                            continue;
                        }

                        if ($operationName === '#in' && !$operandType instanceof ListOfType) {
                            $context->reportError(new Error(
                                self::operationMismatchMessage($operandType->name, $operationName),
                                [$argument]
                            ));
                            continue;
                        }

                        if ($fieldType->name !== Type::ID && $operationName === '#in'
                            && $fieldType->name !== $operandType->getWrappedType(true)->name
                        ) {
                            $context->reportError(new Error(
                                self::fieldMismatchMessage($fieldName, $operandType->getWrappedType(true)->name),
                                [$argument]
                            ));
                            continue;
                        }

                        if ($fieldType->name === Type::ID && $operationName === '#in'
                            && !in_array($operandType->getWrappedType(true)->name, [Type::INT, Type::STRING])
                        ) {
                            $context->reportError(new Error(
                                self::fieldMismatchMessage($fieldName, $operandType->getWrappedType(true)->name),
                                [$argument]
                            ));
                            continue;
                        }
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION  => function (VariableDefinitionNode $definition) {
                $this->variableDefinitions[$definition->variable->name->value] = $definition;
            },
            NodeKind::ARGUMENT => function ($argument) use ($context) {
                if ($context->getArgument()->getType() instanceof FilterExpressionType) {
                    if ($argument->value->kind === NodeKind::STRING) {
                        $expression = $context->getArgument()->getType()->parseValue($argument->value->value);

                        $visitor = new ExpressionNodeVisitor([
                            '#field' => function (TreeNode $node) use ($argument, $context) {
                                $type = $context->getType() instanceof WrappingType
                                    ? $context->getType()->getWrappedType(true) : $context->getType();

                                $this->filterUsages[] = [
                                    $node->getChild(0)->getChild(0)->getValueValue(),
                                    $node->getParent()->getId(),
                                    $node->getParent()->getChild(0) === $node
                                        ? $node->getParent()->getChild(1) : $node->getParent()->getChild(0),
                                    $type,
                                    $argument,
                                ];
                                return false;
                            },
                        ]);

                        $visitor->visit($expression);
                    }
                }
            },
        ];
    }

    protected function isValidField(ValidationContext $context, Type $type, Node $argument, string $fieldName): bool
    {
        if (!$type->hasField($fieldName)) {
            $context->reportError(new Error(
                self::unknownFieldMessage($fieldName, $type->type->name),
                [$argument]
            ));
            return false;
        }

        return true;
    }

    protected function getFieldType(Type $type, string $fieldName): Type
    {
        $fieldType = $type->getField($fieldName)->getType();

        if ($fieldType instanceof WrappingType) {
            $fieldType = $fieldType->getWrappedType(true);
        }

        if ($fieldType instanceof CompositeType) {
            $fieldType = TypeUtility::mapDatabaseType($type->getField($fieldName)->config['storage']);
        }

        return $fieldType;
    }

    public static function unknownFieldMessage($fieldName, $typeName)
    {
        return sprintf('Type "%s" does not have any field named "%s".', $typeName, $fieldName);
    }

    public static function fieldMismatchMessage($fieldName, $operandType)
    {
        return sprintf('Field "%s" does not match with filter operand type "%s".', $fieldName, $operandType);
    }

    public static function operationMismatchMessage($operandType, $operationName)
    {
        return sprintf('Type "%s" can not be used in filter operation "%s".', $operandType, $operationName);
    }
}