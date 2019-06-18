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

use GraphQL\Type\Definition\ResolveInfo;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\GraphQL\FilterExpressionParser;

class FilterArgumentProcessor
{
    /**
     * @var string
     */
    public const ARGUMENT_NAME = 'filter';

    /**
     * @var array
     */
    protected const OPERATOR_MAPPING = [
        '#and' => ['andX', 'orX'],
        '#or' => ['orX', 'andX'],
        '#in' => ['in', 'notIn'],
        '#equals' => ['eq', 'neq'],
        '#not_equals' => ['neq', 'eq'],
        '#less_than' => ['lt', 'gte'],
        '#greater_than' => ['gt', 'lte'],
        '#greater_than_equals' => ['gte', 'lt'],
        '#less_than_equals' => ['lte', 'gt']
    ];

    /**
     * @var ResolveInfo
     */
    protected $info = null;

    /**
     * @var QueryBuilder
     */
    protected $builder = null;

    /**
     * @var TreeNode
     */
    protected $node = null;

    public function __construct(ResolveInfo $info, QueryBuilder $builder)
    {
        $this->info = $info;
        $this->builder = $builder;

        $arguments = $this->info->operation->selectionSet->selections[0]->arguments;

        foreach ($arguments as $argument) {
            if ($argument->name->value === self::ARGUMENT_NAME) {
                $value = $argument->value;
                $this->node = $value->kind === 'StringValue' ? FilterExpressionParser::parse($value->value) : $value->value;
                break;
            }
        }
    }

    public function process()
    {
        return $this->node !== null ? $this->processNode($this->node->getChild(0)) : null;
    }

    /**
     * @todo Use visitor pattern.
     */
    protected function processNode(TreeNode $node, $negate = false)
    {
        if ($this->isNullComparison($node)) {
            $field = $node->getChild(0)->isToken() ? $node->getChild(1) : $node->getChild(0);
            $operation = $node->getId() === '#equals' && !$negate ? 'isNull' : 'isNotNull';

            return $this->builder->expr()->{$operation}($this->processField($field));
        } else if ($this->isBinaryLogicalOperation($node)) {
            $left = $node->getChild(0);
            $right = $node->getChild(1);

            return $this->builder->expr()->{self::OPERATOR_MAPPING[$node->getId()][$negate ? 1 : 0]}(
                $this->{'process'.$this->getType($left)}($left, $negate),
                $this->{'process'.$this->getType($right)}($right, $negate)
            );
        } else if ($this->isComparison($node)) {
            $field = $node->getChild($node->getChild(0)->getId() === '#field' ? 0 : 1);
            $any = $node->getChild($node->getChild(0)->getId() !== '#field' ? 0 : 1);

            return $this->builder->expr()->{self::OPERATOR_MAPPING[$node->getId()][$negate ? 1 : 0]}(
                $this->processField($field),
                $this->{'process'.$this->getType($any)}($any)
            );
        } else if ($this->isNegation($node)) {
            return $this->processNode($node->getChildren()[0], true);
        }

        throw new \Exception(sprintf('Unkown expression %s', $node->getId()));
    }

    protected function processField(TreeNode $node)
    {
        $path = $node->getChild($node->getChild(0)->getId() === '#path' ? 0 : 1);

        return implode('.', array_map(function(TreeNode $node) {
            return $node->getValueValue();
        }, $path->getChildren()));
    }

    protected function processInteger(TreeNode $node)
    {
        return $this->builder->createNamedParameter($node->getValueValue(), \PDO::PARAM_INT);
    }

    protected function processString(TreeNode $node)
    {
        return $this->builder->createNamedParameter(trim($node->getValueValue(), '`'), \PDO::PARAM_STR);
    }

    protected function processBoolean(TreeNode $node)
    {
        return $this->builder->createNamedParameter($node->getValueValue(), \PDO::PARAM_BOOL);
    }

    protected function processFloat(TreeNode $node)
    {
        return $this->builder->createNamedParameter($node->getValueValue(), \PDO::PARAM_STR);
    }

    protected function processNone(TreeNode $node)
    {
        return 'NULL';
    }

    protected function processParameter(TreeNode $node)
    {
        // ...
    }

    protected function isBinaryLogicalOperation(TreeNode $node)
    {
        return !$node->isToken() && ($node->getId() === '#and' || $node->getId() === '#or');
    }

    protected function isComparison(TreeNode $node)
    {
        return !$node->isToken() && in_array($node->getId(), [
            '#equals', '#not_equals',
            '#greater_than', '#less_than',
            '#greater_than_equals', '#less_than_equals'
        ]);
    }

    protected function isNullComparison(TreeNode $node)
    {
        if ($node->isToken() || $node->getId() !== '#equals' && $node->getId() !== '#not_equals') {
            return false;
        }

        if (count(array_filter($node->getChildren(), function(TreeNode $node) {
            return $node->isToken() && $node->getValueToken() === 'null';
        })) !== 1) {
            return false;
        }

        return true;
    }

    protected function isNegation(TreeNode $node)
    {
        return !$node->isToken() && $node->getId() === '#not';
    }

    protected function getType(TreeNode $node): string
    {
        return $node->isToken() ? ucfirst($node->getValueToken()) : 'Node';
    }
}