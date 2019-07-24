<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL;

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
use TYPO3\CMS\Core\Configuration\MetaModel\MultiplicityConstraint;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Exception;

/**
 * @internal
 */
abstract class AbstractRelationshipResolver extends AbstractResolver implements BufferedResolverInterface
{
    /**
     * @var PropertyDefinition
     */
    protected $propertyDefinition;

    /**
     * @var MultiplicityConstraint
     */
    protected $multiplicityConstraint;

    public function __construct(Type $type)
    {
        parent::__construct($type);

        $this->propertyDefinition = $type->config['meta'];

        foreach ($this->propertyDefinition->getConstraints() as $constraint) {
            if ($constraint instanceof MultiplicityConstraint) {
                $this->multiplicityConstraint = $constraint;
                break;
            }
        }
    }

    protected function assertResolveInfoIsValid(ResolveInfo $info)
    {
        if ($info->field !== $this->propertyDefinition->getName()) {
            throw new Exception(
                sprintf(
                    'Resolver was initialized for field "%s" but requested was "%s"',
                    $this->propertyDefinition->getName(), $info->field
                ),
                1563841651
            );
        }
    }

    protected function getPropertyDefinition(): PropertyDefinition
    {
        return $this->propertyDefinition;
    }

    protected function getMultiplicityConstraint(): MultiplicityConstraint
    {
        return $this->multiplicityConstraint;
    }
}