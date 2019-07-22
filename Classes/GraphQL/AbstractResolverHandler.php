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
abstract class AbstractResolverHandler implements ResolverHandlerInterface
{
    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @inheritdoc
     */
    public function __construct(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function onResolve($source, array $arguments, array $context, ResolveInfo $info): void
    {
        return;
    }

    /**
     * @inheritdoc
     */
    public function onResolved($value, $source, array $arguments, array $context, ResolveInfo $info)
    {
        return $value;
    }
}