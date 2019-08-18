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
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\GraphQL\ExpressionNodeVisitor;
use TYPO3\CMS\GraphQL\Type\FilterExpressionType;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NamedTypeNode;

/**
 * @internal
 * @todo Inlcude field types when validate
 */
class VariablesOfCorrectTypeRule extends ValidationRule
{
    /**
     * @var VariableDefinitionNode[]
     */
    public $variableDefinitions;

    /**
     * @var TreeNode[]
     */
    public $variableUsages;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->variableDefinitions = [];
                    $this->variableUsages = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) {
                    $operationName = $operation->name ? $operation->name->value : null;

                    foreach ($this->variableUsages as $variableName => $variableUsage) {
                        $variableDefinition = $this->variableDefinitions[$variableName];
                        $variableType = $variableDefinition->type;
                        $variableOperation = $variableUsage->getParent()->getId();

                        if ($variableType instanceof ListTypeNode && $variableOperation !== '#in') {
                            $context->reportError(new Error(
                                self::badTypeMessage(
                                    $variableName, 
                                    $variableType->kind, 
                                    'Filter operation "in" requires argument of type "List"'
                                ),
                                [$variableDefinition]
                            ));
                        }

                        if ($variableType instanceof ListTypeNode && $variableOperation !== '#in' 
                            && (!$variableType instanceof NamedTypeNode || !in_array(
                                $variableType->type->name, 
                                [NodeKind::STRING, NodeKind::INT, NodeKind::FLOAT]
                            ))
                        ) {
                            $context->reportError(new Error(
                                self::badTypeMessage(
                                    $variableName, 
                                    $variableType->type->name, 
                                    'Filter operation "in" requires list of type "String", "Integer" or "Float"'
                                ),
                                [$variableDefinition]
                            ));
                        }

                        if ($variableOperation === '#match' 
                            && (!$variableType instanceof NamedTypeNode || $variableType->name !== NodeKind::STRING)
                        ) {
                            $context->reportError(new Error(
                                self::badTypeMessage(
                                    $variableName, 
                                    $variableType->name, 
                                    'Filter operation "match" requires argument of type string'
                                ),
                                [$variableDefinition]
                            ));
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
                            '#variable' => function (TreeNode $node) {
                                $this->variableUsages[$node->getChild(0)->getValueValue()] = $node;
                                return false;
                            },
                        ]);

                        $visitor->visit($expression);
                    }
                }
            },
        ];
    }

    public static function badTypeMessage($variableName, $typeName, $message = null)
    {
        return sprintf('Variable "$%s" cannot be type "%s"', $variableName, $typeName) .
            ($message ? "; ${message}" : '.');
    }
}
