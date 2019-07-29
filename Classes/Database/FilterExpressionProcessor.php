<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Database;

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
use Doctrine\DBAL\Connection;

/**
 * @internal
 * @todo Use full qualified identifer in SQL.
 */
class FilterExpressionProcessor
{
    /**
     * @var array
     */
    protected const CONNECTIVE = [
        '#and' => ['andX', 'orX'],
        '#or' => ['orX', 'andX'],
        
    ];

    protected const COMPARISION = [
        '#in' => ['IN', 'NOT IN'],
        '#equals' => ['=', '<>'],
        '#not_equals' => ['<>', '='],
        '#less_than' => ['<', '>='],
        '#greater_than' => ['>', '<='],
        '#greater_than_equals' => ['>=', '<'],
        '#less_than_equals' => ['<=', '>'],
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
     * @var callable
     */
    protected $handler;

    public function __construct(ResolveInfo $info, QueryBuilder $builder, callable $handler)
    {
        $this->info = $info;
        $this->builder = $builder;
        $this->handler = $handler;
    }

    public function process(?TreeNode $expression)
    {
        return $expression !== null ? $this->processNode($expression->getChild(0)) : null;
    }

    protected function processNode(TreeNode $node, int $domain = 0)
    {
        if ($this->isNullComparison($node)) {
            return $this->processNullComparison($node, $domain);
        } elseif ($this->isConnective($node)) {
            return $this->processConnective($node, $domain);
        } elseif ($this->isComparison($node)) {
            return $this->processComparison($node, $domain);
        } elseif ($this->isNegation($node)) {
            return $this->processNegation($node, $domain);
        } elseif ($node->getId() === '#list') {
            return $this->processList($node);
        }

        throw new Exception(
            sprintf('Failed to process node type "%s" in filter expression', $node->getId()),
            1563841479
        );
    }

    protected function processNullComparison(TreeNode $node, int $domain)
    {
        $operator = ($node->getId() === '#equals' xor $domain === 0) ? 'IS NOT NULL' : 'IS NULL';
        $left = $node->getChild($node->getChild(0)->isToken() ? 1 : 0);

        return call_user_func_array(
            $this->handler,
            [
                $this->builder,
                $operator,
                $this->processField($left)
            ]
        );
    }

    protected function processConnective(TreeNode $node, int $domain)
    {
        $left = $node->getChild(0);
        $right = $node->getChild(1);
        $operator = self::CONNECTIVE[$node->getId()][$domain];

        return $this->builder->expr()->{$operator}(
            $this->{'process' . $this->getType($left)}($left, $domain),
            $this->{'process' . $this->getType($right)}($right, $domain)
        );
    }

    protected function processComparison(TreeNode $node, int $domain)
    {
        $operands = [];
        
        foreach ($node->getChildren() as $operand) {
            $operands[] = $operand->getId() === '#field' ? $this->processField($operand)
                : $this->{'process' . $this->getType($operand)}($operand);
        }

        return call_user_func_array(
            $this->handler,
            array_merge(
                [
                    $this->builder,
                    self::COMPARISION[$node->getId()][$domain]
                ],
                $operands
            )
        );
    }

    protected function processNegation(TreeNode $node, int $domain)
    {
        return $this->processNode($node->getChildren()[0], ++$domain%2);
    }

    protected function processField(TreeNode $node): array
    {
        $path = $node->getChild($node->getChild(0)->getId() === '#path' ? 0 : 1);

        return [
            'identifier' => implode('.', array_map(function (TreeNode $node) {
                return $node->getValueValue();
            }, $path->getChildren()))
        ];
    }

    protected function processInteger(TreeNode $node): array
    {
        return [
            'value' => $node->getValueValue(),
            'type' => \PDO::PARAM_INT,
        ];
    }

    protected function processString(TreeNode $node): array
    {
        return [
            'value' => trim($node->getValueValue(), '`'),
            'type' => \PDO::PARAM_STR
        ];
    }

    protected function processBoolean(TreeNode $node): array
    {
        return [
            'value' => $node->getValueValue(),
            'type' => \PDO::PARAM_BOOL,
        ];
    }

    protected function processFloat(TreeNode $node): array
    {
        return [
            'value' => $node->getValueValue(),
            'type' => \PDO::PARAM_STR,
        ];
    }

    protected function processList(TreeNode $node): array
    {
        return [
            'value' => array_map(function (TreeNode $node) {
                return $node->getValueToken() === 'string' 
                    ? trim($node->getValueValue(), '`') : $node->getValueValue();
            }, $node->getChildren()),
            'type' => $node->getChild(0)->getValueToken() === 'int' 
                ? Connection::PARAM_INT_ARRAY : Connection::PARAM_STR_ARRAY,
        ];
    }

    protected function processNone(TreeNode $node): array
    {
        return [
            'value' => null,
            'type' => \PDO::PARAM_NULL,
        ];
    }

    protected function isConnective(TreeNode $node)
    {
        return !$node->isToken() && ($node->getId() === '#and' || $node->getId() === '#or');
    }

    protected function isComparison(TreeNode $node)
    {
        return !$node->isToken() && in_array($node->getId(), [
            '#equals', '#not_equals',
            '#greater_than', '#less_than',
            '#greater_than_equals', '#less_than_equals',
            '#in',
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