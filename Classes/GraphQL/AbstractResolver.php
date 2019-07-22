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
use GraphQL\Type\Definition\Type;

/**
 * @internal
 */
abstract class AbstractResolver implements ResolverInterface
{
    /**
     * @var Type
     */
    protected $type;

    /**
     * @var array
     */
    protected $handlers;

    public function __construct(Type $type)
    {
        $this->type = $type;
        $this->handlers = [];
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

    /**
     * @inheritdoc
     */
    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * Adds a resolver handler.
     *
     * @param ResolverHandlerInterface $handler Resolver handler to add
     */
    public function addHandler(ResolverHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    protected function onResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        foreach ($this->handlers as $handler) {
            $handler->onResolve($source, $arguments, $context, $info);
        }
    }

    protected function onResolved($value, $source, array $arguments, array $context, ResolveInfo $info)
    {
        foreach ($this->handlers as $handler) {
            $value = $handler->onResolved($value, $source, $arguments, $context, $info);
        }

        return $value;
    }
}