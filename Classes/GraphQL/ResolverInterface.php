<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\GraphQL;

use GraphQL\Type\Definition\ResolveInfo;

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

interface ResolverInterface
{
    public function resolve($source, array $arguments, array $context, ResolveInfo $info): array;
    public function getArguments(): array;
}