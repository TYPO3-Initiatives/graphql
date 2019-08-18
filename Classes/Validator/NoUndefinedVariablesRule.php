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
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\GraphQL\ExpressionNodeVisitor;
use TYPO3\CMS\GraphQL\Type\FilterExpressionType;

/**
 * @internal
 */
class NoUndefinedVariablesRule extends ValidationRule
{
    /**
     * @var bool[]
     */
    public $variableNameDefined;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->variableNameDefined = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $variableName = $node->name->value;

                        if (!empty($this->variableNameDefined[$variableName])) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::undefinedVarMessage(
                                $variableName,
                                $operation->name ? $operation->name->value : null
                            ),
                            [$node, $operation]
                        ));
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION  => function (VariableDefinitionNode $definition) {
                $this->variableNameDefined[$definition->variable->name->value] = true;
            },
            NodeKind::ARGUMENT => function ($argument) use ($context) {
                if ($context->getArgument()->getType() instanceof FilterExpressionType) {
                    if ($argument->value->kind === NodeKind::STRING) {
                        $expression = $context->getArgument()->getType()->parseValue($argument->value->value);

                        $visitor = new ExpressionNodeVisitor([
                            '#variable' => function (TreeNode $node) {
                                $this->variableNameDefined[$node->getChild(0)->getValueValue()] = true;
                                return false;
                            },
                        ]);

                        $visitor->visit($expression);
                    }
                }
            },
        ];
    }

    public static function undefinedVarMessage($varName, $opName = null)
    {
        return $opName
            ? sprintf('Variable "$%s" is not defined by operation "%s".', $varName, $opName)
            : sprintf('Variable "$%s" is not defined.', $varName);
    }
}
