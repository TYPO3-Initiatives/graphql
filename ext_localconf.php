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
        \TYPO3\CMS\GraphQL\Database\ActiveRelationshipResolver::class,
        \TYPO3\CMS\GraphQL\Database\EntityResolver::class,
        \TYPO3\CMS\GraphQL\Database\PassiveManyToManyRelationshipResolver::class,
        \TYPO3\CMS\GraphQL\Database\PassiveOneToManyRelationshipResolver::class,
    ],
];