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

use GraphQL\GraphQL;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\NoUndefinedVariables;
use GraphQL\Validator\Rules\NoUnusedVariables;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityRelationMapFactory;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\EntitySchemaFactory;
use TYPO3\CMS\GraphQL\Exception\ExecutionException;
use TYPO3\CMS\GraphQL\Validator\FiltersOnCorrectTypeRule;
use TYPO3\CMS\GraphQL\Validator\NoUndefinedVariablesRule;
use TYPO3\CMS\GraphQL\Validator\NoUnsupportedFeaturesRule;
use TYPO3\CMS\GraphQL\Validator\NoUnusedVariablesRule;
use TYPO3\CMS\GraphQL\Validator\OrdersOnCorrectTypeRule;

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
        $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime');

        return GraphQL::executeQuery(
            self::$schema,
            $query,
            null,
            [
                'query' => uniqid(),
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
                    NoUnsupportedFeaturesRule::class => new NoUnsupportedFeaturesRule(),
                    FiltersOnCorrectTypeRule::class => new FiltersOnCorrectTypeRule(),
                    OrdersOnCorrectTypeRule::class => new OrdersOnCorrectTypeRule(),
                ]
            )
        )
        ->setErrorsHandler(function (array $errors, callable $formatter) {
            throw new ExecutionException('Query execution has failed.', 1566148265, null, ...$errors);
        })
        ->toArray();
    }
}