<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL;

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
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal
 */
abstract class AbstractExpressionParser implements SingletonInterface
{
    /**
     * @var string
     */
    protected $grammar;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var FrontendInterface
     */
    protected $cache;

    public function __construct()
    {
        $path = GeneralUtility::getFileAbsFileName($this->grammar);
        $cacheManager = GeneralUtility::makeInstance(CacheManager::class);

        $this->parser = Llk::load(new Read($path));
        $this->cache = $cacheManager->hasCache('gql') ? $cacheManager->getCache('gql') : null;
    }

    /**
     * Parses an expression
     *
     * @param string $expression
     * @return TreeNode
     *
     * @throws UnexpectedToken
     */
    public function parse(string $expression): TreeNode
    {
        if ($this->cache !== null) {
            $key = $this->getCacheIdentifier($expression);

            if (!$this->cache->has($key)) {
                $this->cache->set($key, $this->parser->parse($expression));
            }

            return $this->cache->get($key);
        }

        return $this->parser->parse($expression);
    }

    protected function getCacheIdentifier($expression): string
    {
        return sha1($expression);
    }
}