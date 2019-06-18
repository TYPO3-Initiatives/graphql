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
use IteratorAggregate;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use Webmozart\Assert\Assert;
use GraphQL\Type\Definition\ResolveInfo;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;

class OrderExpressionTraversable implements IteratorAggregate
{
    const ORDER_ASCENDING = 0;

    const ORDER_DESCENDING = 1;

    protected const ORDER_MAPPINGS = [
        'asc' => self::ORDER_ASCENDING,
        'desc' => self::ORDER_DESCENDING,
        'ascending' => self::ORDER_ASCENDING,
        'descending' => self::ORDER_DESCENDING
    ];

    /**
     * @var ResolveInfo
     */
    protected $info;

    /**
     * @var TreeNode
     */
    protected $expression;

    /**
     * @var string[]
     */
    protected $types;

    public function __construct(ResolveInfo $info, ?TreeNode $expression, string ...$types)
    {
        Assert::keyExists($info->returnType->config, 'meta');
        Assert::isInstanceOfAny($info->returnType->config['meta'], [EntityDefinition::class, PropertyDefinition::class]);

        $this->info = $info;
        $this->expression = $expression;
        $this->types = array_flip($types);
    }

    public function getIterator()
    {
        if ($this->expression) {
            foreach ($this->expression->getChildren() as $item) {
                $constraint = $item->getChildrenNumber() > 2 ? $item->getChild(1)->getChild(0)->getValueValue() : null;
                $path = $item->getChild(0);
                $field = $path->getChild(0)->getValueValue();
                $order = strtolower($item->getChild(count($item->getChildren()) > 2 ? 2 : 1)->getValueValue());

                if ($constraint !== null && count($this->types) > 0 && !isset($this->types[$constraint])) {
                    continue;
                }

                yield [
                    'constraint' => $constraint,
                    'field' => $field,
                    'order' => self::ORDER_MAPPINGS[strtolower($order)]
                ];
            }
        } else {
            foreach ($this->types as $type => $_) {
                $configuration = $GLOBALS['TCA'][$type]['ctrl'];
                $expression = $configuration['sortby'] ?: $configuration['default_sortby'];

                foreach (QueryHelper::parseOrderBy($expression ?? '') as $item) {
                    yield [
                        'constraint' => $type,
                        'field' => $item[0],
                        'order' => self::ORDER_MAPPINGS[strtolower($item[1] ?? 'ascending')]
                    ];
                }
            }
        }
    }
}