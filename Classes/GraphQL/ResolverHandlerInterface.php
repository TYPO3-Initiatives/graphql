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
 * Decorates a resolver.
 * @internal
 */
interface ResolverHandlerInterface
{
    /**
     * Gets called before resolve.
     * 
     * @param mixed $source Previous value
     * @param array $arguments Arguments provided to the field in the query
     * @param array $context Contextual information
     * @param ResolveInfo $info Information about the current query as wel as schema details
     * @see https://graphql.org/learn/execution/
     */
    public function beforeResolve($source, array $arguments, array $context, ResolveInfo $info): void;

    /**
     * Gets called after resolve.
     * 
     * @param mixed $source Previous value
     * @param array $arguments Arguments provided to the field in the query
     * @param array $context Contextual information
     * @param ResolveInfo $info Information about the current query as wel as schema details
     * @param array $value Value of the query field or null
     * @return null|array Value of the query field or null
     * @see https://graphql.org/learn/execution/
     */
    public function afterResolve($source, array $arguments, array $context, ResolveInfo $info, ?array $value): ?array;

    /**
     * Returns the provided arguments.
     * 
     * @return array
     * @see https://webonyx.github.io/graphql-php/type-system/object-types/#field-arguments
     */
    public function getArguments(): array;
}