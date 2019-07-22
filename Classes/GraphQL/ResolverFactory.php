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

use GraphQL\Type\Definition\Type;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal
 */
class ResolverFactory implements SingletonInterface
{
    /**
     * Creates a resolver for the given type.
     *
     * @param Type $type Type to create a resolver for
     */
    public function create(Type $type)
    {
        foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['gql']['resolver'] as $resolver) {
            if ($resolver::canResolve($type)) {
                $resolver = GeneralUtility::makeInstance($resolver, $type);

                if ($resolver instanceof AbstractResolver) {
                    foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['gql']['resolverHandler'] as $resolverHandler) {
                        if ($resolverHandler::canHandle($resolver)) {
                            $resolver->addHandler(GeneralUtility::makeInstance($resolverHandler, $resolver));
                        }
                    }
                }
                
                return $resolver;
            }
        }

        throw new \RuntimeException(sprintf('No resolver found for type %s', $type));
    }
}