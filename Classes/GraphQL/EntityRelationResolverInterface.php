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

use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;

/**
 * Resolver of an entity relationship.
 * @internal
 */
interface EntityRelationResolverInterface extends ResolverInterface
{
    /**
     * Creates the resolver.
     * 
     * @param PropertyDefinition $propertyDefinition Entity property definition to resolve
     */
    public function __construct(PropertyDefinition $propertyDefinition);

    /**
     * Returns whether the resolver can be used for an entity relationship or not.
     * 
     * @param PropertyDefinition $propertyDefinition Entity property definition to resolve
     */
    public static function canResolve(PropertyDefinition $propertyDefinition);
}