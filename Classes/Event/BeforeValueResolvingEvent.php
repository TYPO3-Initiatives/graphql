<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Event;

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
use TYPO3\CMS\GraphQL\ResolverInterface;

/**
 * @internal
 */
final class BeforeValueResolvingEvent
{
    /**
     * @var mixed
     */
    private $source;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var array
     */
    private $context;

    /**
     * @var ResolveInfo
     */
    private $info;

    /**
     * @var ResolverInterface
     */
    private $resolver;

    public function __construct($source, array $arguments, array $context, ResolveInfo $info, ResolverInterface $resolver)
    {
        $this->source = $source;
        $this->arguments = $arguments;
        $this->context = $context;
        $this->info = $info;
        $this->resolver = $resolver;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getInfo(): ResolveInfo
    {
        return $this->info;
    }

    public function getResolver(): ResolverInterface
    {
        return $this->resolver;
    }
}
