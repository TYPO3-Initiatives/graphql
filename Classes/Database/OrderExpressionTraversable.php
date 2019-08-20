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

use Hoa\Compiler\Llk\TreeNode;
use IteratorAggregate;
use TYPO3\CMS\Core\Configuration\MetaModel\ElementInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
class OrderExpressionTraversable implements IteratorAggregate
{
    /**
     * @var int
     */
    const ORDER_ASCENDING = 0;

    /**
     * @var int
     */
    const ORDER_DESCENDING = 1;

    /**
     * @var int
     */
    const MODE_SQL = 0;

    /**
     * @var int
     */
    const MODE_GQL = 1;

    /**
     * @var array
     */
    protected const ORDER_MAPPINGS = [
        'asc' => self::ORDER_ASCENDING,
        'desc' => self::ORDER_DESCENDING,
        'ascending' => self::ORDER_ASCENDING,
        'descending' => self::ORDER_DESCENDING,
    ];

    /**
     * @var ElementInterface
     */
    protected $element;

    /**
     * @var TreeNode
     */
    protected $expression;

    /**
     * @var int
     */
    protected $mode;

    public function __construct(ElementInterface $element, ?TreeNode $expression, int $mode = self::MODE_SQL)
    {
        Assert::isInstanceOfAny($element, [EntityDefinition::class, PropertyDefinition::class]);
        Assert::oneOf($mode, [self::MODE_SQL, self::MODE_GQL]);

        $this->element = $element;
        $this->expression = $expression;
        $this->mode = $mode;
    }

    public function getIterator()
    {
        if (!$this->expression) {
            return;
        }

        foreach ($this->expression->getChildren() as $item) {
            $path = $item->getChild(0);
            $field = $path->getChild(0)->getValueValue();
            $order = strtolower($item->getChild(count($item->getChildren()) > 2 ? 2 : 1)->getValueValue());
            $constraints = [null];

            if ($item->getChildrenNumber() > 2) {
                $constraints = [$item->getChild(1)->getChild(0)->getValueValue()];
            } elseif ($this->mode == self::MODE_SQL && $this->element instanceof EntityDefinition) {
                $constraints = [$this->element->getName()];
            } elseif ($this->mode == self::MODE_SQL && $this->element instanceof PropertyDefinition) {
                $constraints = $this->element->getRelationTableNames();
            }

            foreach ($constraints as $constraint) {
                yield [
                    'constraint' => $constraint,
                    'field' => $field,
                    'order' => self::ORDER_MAPPINGS[strtolower($order)],
                ];
            }
        }
    }
}