<?php
declare(strict_types=1);
namespace TYPO3\CMS\Core\GraphQL\Build\Composer;

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

/**
 * @codeCoverageIgnore Build Helper
 */
final class ScriptHelper
{
    private const DIRECTORY = __DIR__ . '/../../.build/public/typo3/sysext';

    private const LINK = self::DIRECTORY . '/graphql';

    public static function linkPackage(): void
    {
        if (!is_dir(self::DIRECTORY)) {
            throw new \RuntimeException(sprintf('Directory "%s" does not exist', self::DIRECTORY));
        }

        if (!is_link(self::LINK)) {
            symlink(dirname(__DIR__, 2) . '/', self::LINK);
        }
    }
}