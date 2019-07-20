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

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal
 */
class ResolverHandlerFactory implements SingletonInterface
{
    /**
     * @return ResolverHandlerChain
     */
    public function create(): ResolverHandlerChain
    {
        $handlers = GeneralUtility::makeInstance(ResolverHandlerChain::class);

        foreach ($GLOBALS['TYPO3_CONF_VARS']['SYS']['gql']['resolverHandler'] as $resolverHandler) {
            $handlers->addHandler(GeneralUtility::makeInstance($resolverHandler));
        }

        return $handlers;
    }
}