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

use GraphQL\Error\Debug;
use GraphQL\GraphQL;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityRelationMapFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @api
 */
class EntityReader implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @var Schema
     */
    protected static $schema = null;

    public function __construct()
    {
        if (self::$schema === null) {
            $entityRelationMapFactory = GeneralUtility::makeInstance(EntityRelationMapFactory::class, $GLOBALS['TCA']);
            $schemaFactory = GeneralUtility::makeInstance(EntitySchemaFactory::class);
            self::$schema = $schemaFactory->create($entityRelationMapFactory->create());
        }
    }

    public function execute(string $query, array $bindings = [], Context $context = null): array
    {
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('gql');
        $cache->flush();

        return GraphQL::executeQuery(
            self::$schema,
            $query,
            null,
            [
                'cache' => $cache,
                'context' => $context,
            ],
            $bindings,
            null,
            null,
            GraphQL::getStandardValidationRules()
        )->toArray(Debug::RETHROW_INTERNAL_EXCEPTIONS);
    }
}