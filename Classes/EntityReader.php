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

use GraphQL\Error\Debug;
use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\NoUndefinedVariables;
use GraphQL\Validator\Rules\NoUnusedVariables;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityRelationMapFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\GraphQL\Validator\NoUndefinedVariablesRule;
use TYPO3\CMS\GraphQL\Validator\NoUnusedVariablesRule;
use TYPO3\CMS\GraphQL\Validator\VariablesOfCorrectTypeRule;

/**
 * @api
 */
class EntityReader implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @var \GraphQL\Type\Schema
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
            array_merge(
                DocumentValidator::defaultRules(),
                [
                    NoUndefinedVariables::class => new NoUndefinedVariablesRule(),
                    NoUnusedVariables::class => new NoUnusedVariablesRule(),
                    VariablesOfCorrectTypeRule::class => new VariablesOfCorrectTypeRule(),
                ]
            )
        )->toArray(Debug::RETHROW_INTERNAL_EXCEPTIONS);
    }
}