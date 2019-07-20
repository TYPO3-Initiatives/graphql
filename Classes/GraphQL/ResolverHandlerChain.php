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

/**
 * @internal
 */
class ResolverHandlerChain implements ResolverHandlerInterface
{
    /**
     * @var array
     */
    protected $handlers = [];

    /**
     * Adds a resolver handler.
     * 
     * @param ResolverHandlerInterface $handler Resolver handler to add
     */
    public function addHandler(ResolverHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * @inheritdoc
     */
    public function beforeResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        foreach ($this->handlers as $handler) {
            $handler->beforeResolve($source, $arguments, $context, $info);
        }
    }

    /**
     * @inheritdoc
     */
    public function afterResolve($source, array $arguments, array $context, ResolveInfo $info, ?array $data): ?array
    {
        foreach ($this->handlers as $handler) {
            $data = $handler->afterResolve($source, $arguments, $context, $info, $data);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        $arguments = [];

        foreach ($this->handlers as $handler) {
            $arguments = array_merge($arguments, $handler->getArguments());
        }

        return $arguments;
    }
}