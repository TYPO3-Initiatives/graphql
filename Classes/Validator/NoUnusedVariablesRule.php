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

/**
 * @internal
 */
class NoUnusedVariablesRule extends ValidationRule
{
    /**
     * @var VariableDefinitionNode[]
     */
    public $variableDefinitions;

    /**
     * @var bool[]
     */
    public $variableNameUsed;

    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => function () {
                    $this->variableDefinitions = [];
                    $this->variableNameUsed = [];
                },
                'leave' => function (OperationDefinitionNode $operation) use ($context) {
                    $usages = $context->getRecursiveVariableUsages($operation);
                    $operationName = $operation->name ? $operation->name->value : null;

                    foreach ($usages as $usage) {
                        $node = $usage['node'];
                        $this->variableNameUsed[$node->name->value] = true;
                    }

                    foreach ($this->variableDefinitions as $variableDefinition) {
                        $variableName = $variableDefinition->variable->name->value;

                        if (!empty($this->variableNameUsed[$variableName])) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::unusedVariableMessage($variableName, $operationName),
                            [$variableDefinition]
                        ));
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION => function ($definition) {
                $this->variableDefinitions[] = $definition;
            },
            NodeKind::ARGUMENT => function ($argument) use ($context) {
                if ($context->getArgument()->getType() instanceof FilterExpressionType) {
                    if ($argument->value->kind === NodeKind::STRING) {
                        $expression = $context->getArgument()->getType()->parseValue($argument->value->value);

                        $visitor = new ExpressionNodeVisitor([
                            '#variable' => function (TreeNode $node) {
                                $this->variableNameUsed[$node->getChild(0)->getValueValue()] = true;
                                return false;
                            },
                        ]);

                        $visitor->visit($expression);
                    }
                }
            },
        ];
    }

    public static function unusedVariableMessage($variableName, $operationName = null)
    {
        return $operationName
            ? sprintf('Variable "$%s" is never used in operation "%s".', $variableName, $operationName)
            : sprintf('Variable "$%s" is never used.', $variableName);
    }
}
