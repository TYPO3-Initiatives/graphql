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

use Hoa\Compiler\Llk\Llk;
use Hoa\Compiler\Llk\TreeNode;
use Hoa\File\Read;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OrderExpressionParser
{
    protected static $compiler;

    /**
     * @throws \Hoa\Compiler\Exception\UnexpectedToken
     */
    public static function parse($expression): ?TreeNode
    {
        if (!self::$compiler) {
            $path = GeneralUtility::getFileAbsFileName('EXT:graphql/Resources/Private/Grammar/Order.pp');
            self::$compiler = Llk::load(new Read($path));
        }

        $expressionNode = self::$compiler->parse($expression);

        return $expressionNode instanceof TreeNode ? $expressionNode : null;
    }
}