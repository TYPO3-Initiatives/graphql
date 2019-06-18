<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\GraphQL;

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
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\GraphQL\Type\FilterExpressionType;
use TYPO3\CMS\Core\GraphQL\Type\OrderExpressionType;

abstract class AbstractEntityRelationResolver implements EntityRelationResolverInterface, BufferedResolverInterface
{
    /**
     * @var PropertyDefinition
     */
    protected $propertyDefinition;

    public function __construct(PropertyDefinition $propertyDefinition)
    {
        $this->propertyDefinition = $propertyDefinition;
    }

    public function getArguments(): array
    {
        return [
            [
                'name' => EntitySchemaFactory::FILTER_ARGUMENT_NAME,
                'type' => FilterExpressionType::instance(),
            ],
            [
                'name' => EntitySchemaFactory::ORDER_ARGUMENT_NAME,
                'type' => OrderExpressionType::instance(),
            ],
        ];
    }

    protected function assertResolveInfoIsValid(ResolveInfo $info)
    {
        if ($info->field !== $this->propertyDefinition->getName()) {
            throw new \RuntimeException(
                sprintf('Resolver was initialized for field %s but requested was %s.',
                $this->propertyDefinition->getName(), $info->field)
            );
        }
    }

    protected function getPropertyDefinition(): PropertyDefinition
    {
        return $this->propertyDefinition;
    }
}