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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EntityRelationResolverFactory implements SingletonInterface
{
    /**
     * @todo Use some configuration for this
     */
    public function create(PropertyDefinition $propertyDefinition, ResolverHandlerChain $handlers = null)
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['gql']['entityRelationResolver'] as $entityRelationResolver) {
            if ($entityRelationResolver::canResolve($propertyDefinition)) {
                return GeneralUtility::makeInstance($entityRelationResolver, $propertyDefinition, $handlers);
            }
        }

        throw new \RuntimeException(sprintf('No resolver found for property %s', $propertyDefinition));
    }
}