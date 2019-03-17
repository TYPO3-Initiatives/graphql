<?php
defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['gql'] = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend::class,
    'options' => [],
    'groups' => [],
];

$GLOBALS['TYPO3_CONF_VARS']['SYS']['gql'] = [
    'entityRelationResolver' => [
        \TYPO3\CMS\Core\GraphQL\Database\PassiveManyToManyEntityRelationResolver::class,
        \TYPO3\CMS\Core\GraphQL\Database\PassiveOneToManyEntityRelationResolver::class,
        \TYPO3\CMS\Core\GraphQL\Database\ActiveEntityRelationResolver::class,
    ],
];