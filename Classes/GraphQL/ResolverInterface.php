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
 * Fetches data of a single query field.
 * 
 * @internal
 */
interface ResolverInterface
{
    /**
     * Returns whether the resolver can be used for the given type or not.
     *
     * @param Type $type Type to resolve
     * @return bool
     */
    public static function canResolve(Type $type): bool;

    /**
     * Creates a resolver.
     *
     * @param Type $type Type to create the resolver for
     */
    public function __construct(Type $type);

    /**
     * Returns the data of the query field.
     *
     * @param mixed $source Previous value
     * @param array $arguments Arguments provided to the field in the query
     * @param array $context Contextual information
     * @param ResolveInfo $info Information about the current query as wel as schema details
     * @return mixed Value of the query field or null
     * @see https://graphql.org/learn/execution/
     */
    public function resolve($source, array $arguments, array $context, ResolveInfo $info);

    /**
     * Returns the type handled by this resolver.
     *
     * @return Type
     */
    public function getType(): Type;

    /**
     * Returns the provided arguments of the query field.
     *
     * @return array
     * @see https://webonyx.github.io/graphql-php/type-system/object-types/#field-arguments
     */
    public function getArguments(): array;
}