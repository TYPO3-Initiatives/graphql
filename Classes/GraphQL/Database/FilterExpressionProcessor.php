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
use TYPO3\CMS\Core\Exception;

/**
 * @internal
 * @todo Use full qualified identifer in SQL.
 */
class FilterExpressionProcessor
{
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
        '#less_than_equals' => ['lte', 'gt'],
    ];

    /**
     * @var ResolveInfo
     */
    protected $info;

    /**
     * @var QueryBuilder
     */
    protected $builder;

    /**
     * @var TreeNode
     */
    protected $expression;

    public function __construct(ResolveInfo $info, ?TreeNode $expression, QueryBuilder $builder)
    {
        $this->info = $info;
        $this->builder = $builder;
        $this->expression = $expression;
    }

    public function process()
    {
        return $this->expression !== null ? $this->processNode($this->expression->getChild(0)) : null;
    }

    protected function processNode(TreeNode $node, int $domain = 0)
    {
        if ($this->isNullComparison($node)) {
            return $this->processNullComparison($node, $domain);
        } elseif ($this->isBinaryLogicalOperation($node)) {
            return $this->processBinaryLogicalOperation($node, $domain);
        } elseif ($this->isComparison($node)) {
            return $this->processComparison($node, $domain);
        } elseif ($this->isNegation($node)) {
            return $this->processNegation($node, $domain);
        }

        throw new Exception(
            sprintf('Failed to process node in expression "%s"', $this->expression),
            1563841479
        );
    }

    protected function processNullComparison(TreeNode $node, int $domain)
    {
        $field = $node->getChild(0)->isToken() ? $node->getChild(1) : $node->getChild(0);
        $operation = ($node->getId() === '#equals' xor $domain === 0) ? 'isNotNull' : 'isNull';

        return $this->builder->expr()->{$operation}($this->processField($field));
    }

    protected function processBinaryLogicalOperation(TreeNode $node, int $domain)
    {
        $left = $node->getChild(0);
        $right = $node->getChild(1);
        $operation = self::OPERATOR_MAPPING[$node->getId()][$domain];

        return $this->builder->expr()->{$operation}(
            $this->{'process' . $this->getType($left)}($left, $domain),
            $this->{'process' . $this->getType($right)}($right, $domain)
        );
    }

    protected function processComparison(TreeNode $node, int $domain)
    {
        $field = $node->getChild($node->getChild(0)->getId() === '#field' ? 0 : 1);
        $any = $node->getChild($node->getChild(0)->getId() !== '#field' ? 0 : 1);

        return $this->builder->expr()->{self::OPERATOR_MAPPING[$node->getId()][$domain]}(
            $this->processField($field),
            $this->{'process' . $this->getType($any)}($any)
        );
    }

    protected function processNegation(TreeNode $node, int $domain)
    {
        return $this->processNode($node->getChildren()[0], ++$domain%2);
    }

    protected function processField(TreeNode $node)
    {
        $path = $node->getChild($node->getChild(0)->getId() === '#path' ? 0 : 1);

        return implode('.', array_map(function (TreeNode $node) {
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

    protected function isBinaryLogicalOperation(TreeNode $node)
    {
        return !$node->isToken() && ($node->getId() === '#and' || $node->getId() === '#or');
    }

    protected function isComparison(TreeNode $node)
    {
        return !$node->isToken() && in_array($node->getId(), [
            '#equals', '#not_equals',
            '#greater_than', '#less_than',
            '#greater_than_equals', '#less_than_equals',
        ]);
    }

    protected function isNullComparison(TreeNode $node)
    {
        if ($node->isToken() || $node->getId() !== '#equals' && $node->getId() !== '#not_equals') {
            return false;
        }

        if (count(array_filter($node->getChildren(), function (TreeNode $node) {
            return $node->isToken() && $node->getValueToken() === 'null';
        })) !== 1) {
            return false;
        }

        return true;
    }

    protected function isNegation(TreeNode $node): bool
    {
        return !$node->isToken() && $node->getId() === '#not';
    }

    protected function getType(TreeNode $node): string
    {
        return $node->isToken() ? ucfirst($node->getValueToken()) : 'Node';
    }
}