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

use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\GraphQL\Type\FilterExpressionType;
use TYPO3\CMS\Core\GraphQL\Type\OrderExpressionType;

abstract class AbstractEntityResolver implements ResolverInterface
{
    /**
     * @var EntityDefinition
     */
    protected $entityDefinition;

    public function __construct(EntityDefinition $entityDefinition)
    {
        $this->entityDefinition = $entityDefinition;
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
            ]
        ];
    }

    protected function getEntityDefinition(): EntityDefinition
    {
        return $this->entityDefinition;
    }
}