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

use GraphQL\Type\Definition\Type;
use Hoa\Compiler\Llk\TreeNode;
use IteratorAggregate;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
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
     * @var Type
     */
    protected $type;

    /**
     * @var TreeNode
     */
    protected $expression;

    /**
     * @var string[]
     */
    protected $types;

    /**
     * @var int
     */
    protected $mode;

    public function __construct(Type $type, ?TreeNode $expression, int $mode = self::MODE_SQL)
    {
        Assert::keyExists($type->config, 'meta');
        Assert::isInstanceOfAny($type->config['meta'], [EntityDefinition::class, PropertyDefinition::class]);
        Assert::oneOf($mode, [self::MODE_SQL, self::MODE_GQL]);

        $this->type = $type;
        $this->expression = $expression;
        $this->mode = $mode;
    }

    public function getIterator()
    {
        $meta = $this->type->config['meta'];

        if ($this->expression) {
            yield from $this->getExpressionIterator();
        } elseif ($meta instanceof EntityDefinition) {
            yield from $this->getEntityIterator();
        } elseif ($meta instanceof PropertyDefinition) {
            yield from $this->getPropertyIterator();
        }
    }

    protected function getEntityIterator()
    {
        $meta = $this->type->config['meta'];
        $configuration = $GLOBALS['TCA'][$meta->getName()]['ctrl'];
        $expression = $configuration['sortby'] ?: $configuration['default_sortby'];

        foreach (QueryHelper::parseOrderBy($expression ?? '') as $item) {
            yield [
                'constraint' => $meta->getName(),
                'field' => $item[0],
                'order' => self::ORDER_MAPPINGS[strtolower($item[1] ?? 'ascending')],
            ];
        }
    }

    protected function getExpressionIterator()
    {
        $meta = $this->type->config['meta'];

        foreach ($this->expression->getChildren() as $item) {
            $path = $item->getChild(0);
            $field = $path->getChild(0)->getValueValue();
            $order = strtolower($item->getChild(count($item->getChildren()) > 2 ? 2 : 1)->getValueValue());
            $constraints = [null];

            if ($item->getChildrenNumber() > 2) {
                $constraints = [$item->getChild(1)->getChild(0)->getValueValue()];
            } elseif ($this->mode == self::MODE_SQL && $meta instanceof EntityDefinition) {
                $constraints = [$meta->getName()];
            } elseif ($this->mode == self::MODE_SQL && $meta instanceof PropertyDefinition) {
                $constraints = $meta->getRelationTableNames();
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

    protected function getPropertyIterator()
    {
        $meta = $this->type->config['meta'];
        $configuration = $meta->getConfiguration();

        if ($meta->isManyToManyRelationProperty()) {
            yield [
                'constraint' => $this->mode === self::MODE_SQL ? $meta->getManyToManyTableName() : null,
                'field' => 'sorting',
                'order' => self::ORDER_ASCENDING,
            ];
        } elseif ($meta->isSelectRelation() || $meta->isInlineRelationProperty()) {
            if (isset($configuration['config']['foreign_sortby'])) {
                yield [
                    'constraint' => $configuration['config']['foreign_table'],
                    'field' => $configuration['config']['foreign_sortby'],
                    'order' => self::ORDER_ASCENDING,
                ];
            } elseif (isset($configuration['config']['foreign_default_sortby'])) {
                $expression = $configuration['config']['foreign_default_sortby'];

                foreach (QueryHelper::parseOrderBy($expression ?? '') as $item) {
                    yield [
                        'constraint' => $meta->getName(),
                        'field' => $item[0],
                        'order' => self::ORDER_MAPPINGS[strtolower($item[1] ?? 'ascending')],
                    ];
                }
            }
        }
    }
}