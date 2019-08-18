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
use GraphQL\Validator\Rules\ValidationRule;
use GraphQL\Validator\ValidationContext;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\GraphQL\ExpressionNodeVisitor;
use TYPO3\CMS\GraphQL\Type\FilterExpressionType;
use TYPO3\CMS\GraphQL\Type\OrderExpressionType;

/**
 * @internal
 */
class NoUnsupportedFeaturesRule extends ValidationRule
{
    /**
     * @inheritdoc
     */
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::ARGUMENT => function ($argument) use ($context) {
                if (($context->getArgument()->getType() instanceof OrderExpressionType
                    || $context->getArgument()->getType() instanceof FilterExpressionType)
                    && $argument->value->kind === NodeKind::STRING
                ) {
                    $expression = $context->getArgument()->getType()->parseValue($argument->value->value);

                    $visitor = new ExpressionNodeVisitor([
                        '#field' => function (TreeNode $node) use ($argument, $context) {
                            if ($node->getChild(0)->getChildrenNumber() > 1) {
                                $context->reportError(new Error(
                                    self::noNestedFieldsMessage(),
                                    [$argument]
                                ));
                            }
                            return false;
                        },
                    ]);

                    $visitor->visit($expression);
                }
            },
        ];
    }

    public static function noNestedFieldsMessage()
    {
        return 'Nested field access in expressions is not implemented yet.';
    }
}
