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
use GraphQL\Type\Definition\Type;
use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\GraphQL\Exception\NotSupportedException;
use TYPO3\CMS\GraphQL\Exception\SchemaException;
use Webmozart\Assert\Assert;

/**
 * @internal
 * @todo Implement this as a validation rule
 */
class OrderExpressionValidator
{
    /**
     * @var Type
     */
    protected $type;

    /**
     * @var TreeNode
     */
    protected $expression;

    /**
     * @var ResolveInfo
     */
    protected $info;

    public function __construct(Type $type, ?TreeNode $expression, ResolveInfo $info)
    {
        Assert::keyExists($type->config, 'meta');
        Assert::isInstanceOfAny($type->config['meta'], [EntityDefinition::class, PropertyDefinition::class]);

        $this->type = $type;
        $this->expression = $expression;
        $this->info = $info;
    }

    public function validate()
    {
        if ($this->expression === null) {
            return;
        }

        foreach ($this->expression->getChildren() as $item) {
            $this->validateTypeConstraint($item);
            $this->validateField($item);
        }
    }

    protected function validateTypeConstraint(TreeNode $item)
    {
        if ($item->getChildrenNumber() < 3) {
            return;
        }

        $type = $item->getChild(1)->getChild(0)->getValueValue();

        if (!$this->info->schema->hasType($type)) {
            throw new SchemaException(
                sprintf('Unknown type "%s" in order clause constraint', $type),
                1560598849
            );
        }

        if (Type::isLeafType($this->info->schema->getType($type))) {
            throw new NotSupportedException(
                sprintf('Leaf type "%s" is not supported in order clause constraint', $type),
                1560598849
            );
        }

        if (Type::isAbstractType($this->info->schema->getType($type))) {
            throw new NotSupportedException(
                sprintf('Abstract type "%s" is not supported in order clause constraint', $type),
                1560648120
            );
        }

        if ($this->type->config['meta'] instanceof EntityDefinition
            && $this->type->config['meta']->getName() !== $type
            || $this->type->config['meta'] instanceof PropertyDefinition
            && !$this->type->config['meta']->hasActiveRelationTo($type)
        ) {
            throw new SchemaException(
                sprintf('Type "%s" is out of scope in order clause', $type),
                1560655028
            );
        }
    }

    protected function validateField(TreeNode $item)
    {
        $types = $this->type->config['meta'] instanceof EntityDefinition
            ? [$this->type->config['meta']->getName()] : $this->type->config['meta']->getRelationTableNames();

        $path = $item->getChild(0);
        $field = $path->getChild(0)->getValueValue();
        $constraints = $item->getChildrenNumber() > 2 ? [$item->getChild(1)->getChild(0)->getValueValue()] : $types;

        foreach ($constraints as $constraint) {
            if (!$this->info->schema->getType($constraint)->hasField($field)) {
                throw new SchemaException(
                    sprintf('Unknown field "%s" in order clause', $field),
                    1560645175
                );
            }

            if (count($path->getChildren()) > 1) {
                throw new NotSupportedException(
                    sprintf('Nested field "%s" is not supported in order clause', $field),
                    1563841549
                );
            }

            if (Type::isCompositeType($this->info->schema->getType($constraint)->getField($field)->getType())) {
                throw new NotSupportedException(
                    sprintf(
                        'Composite type "%s" is not supported in order clause',
                        $this->info->schema->getType($constraint)->getField($field)->getType()
                    ),
                    1560598442
                );
            }
        }
    }
}