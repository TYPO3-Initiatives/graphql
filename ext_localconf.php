<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['gql'] = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
    'options' => [],
    'groups' => [],
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['gql'] = [
    'resolver' => [
        \TYPO3\CMS\Core\GraphQL\Database\ActiveRelationshipResolver::class,
        \TYPO3\CMS\Core\GraphQL\Database\EntityResolver::class,
        \TYPO3\CMS\Core\GraphQL\Database\PassiveManyToManyRelationshipResolver::class,
        \TYPO3\CMS\Core\GraphQL\Database\PassiveOneToManyRelationshipResolver::class,
    ],
    'resolverHandler' => [
        \TYPO3\CMS\Core\GraphQL\Database\QueryFilterHandler::class,
        \TYPO3\CMS\Core\GraphQL\Database\QueryOrderHandler::class,
    ],
];