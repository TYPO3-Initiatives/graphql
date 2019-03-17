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

use GraphQL\Deferred;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityRelationMap;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\GraphQL\Database\EntityResolver;
use TYPO3\CMS\Core\GraphQL\EntityRelationResolverFactory;
use TYPO3\CMS\Core\GraphQL\Type\SortClauseType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EntitySchemaFactory
{
    protected static $objectTypes = [];

    protected static $interfaceTypes = [];

    public function create(EntityRelationMap $entityRelationMap): Schema
    {
        $query = [
            'name' => 'Query',
            'fields' => [],
        ];

        foreach ($entityRelationMap->getEntityDefinitions() as $entityDefinition) {
            $resolver = GeneralUtility::makeInstance(EntityResolver::class, $entityDefinition);

            $query['fields'][$entityDefinition->getName()] = [
                'type' => Type::listOf($this->buildObjectType($entityDefinition)),
                'args' => $resolver->getArguments(),
                'meta' => $entityDefinition,
                'resolve' => function($source, $arguments, $context, ResolveInfo $info) use ($resolver) {
                    return $resolver->resolve($source, $arguments, $context, $info);
                }
            ];
        }

        return new Schema([
            'query' => new ObjectType($query),
        ]);
    }

    protected function buildEntityInterfaceType() {
        if (!array_key_exists('Entity', self::$interfaceTypes)) {
            self::$interfaceTypes['Entity'] = new InterfaceType([
                'name' => 'Entity',
                'description' => 'Base type for all entites managed by TYPO3 CMS.',
                'fields' => [
                    'uid' => [
                        'type' => Type::id(),
                        'description' => 'The id of the entity.',
                    ]
                ],
                'resolveType' => function ($value) {
                    return self::$objectTypes[$value['__table']];
                },
            ]);
        }

        return self::$interfaceTypes['Entity'];
    }

    protected function buildObjectType(EntityDefinition $entityDefinition)
    {
        if (array_key_exists($entityDefinition->getName(), self::$objectTypes)) {
            $objectType = self::$objectTypes[$entityDefinition->getName()];
        } else {
            $objectType = new ObjectType([
                'name' => $entityDefinition->getName(),
                'interfaces' => [
                    $this->buildEntityInterfaceType()
                ],
                'fields' => function() use($entityDefinition) {
                    return array_map(function($propertyDefinition) {
                        $field = [
                            'name' => $propertyDefinition->getName(),
                            'type' => $this->buildFieldType($propertyDefinition),
                            'args' => [],
                            'meta' => $propertyDefinition
                        ];

                        if ($propertyDefinition->isRelationProperty() && !$propertyDefinition->isLanguageRelationProperty()) {
                            $resolver = GeneralUtility::makeInstance(EntityRelationResolverFactory::class)->create($propertyDefinition);

                            $field['args'] = $resolver->getArguments();
                            $field['resolve'] = function($source, $arguments, $context, ResolveInfo $info) use ($resolver) {
                                $resolver->collect($source, $arguments, $context, $info);

                                return new Deferred(function() use ($resolver, $source, $arguments, $context, $info) {
                                    return $resolver->resolve($source, $arguments, $context, $info);
                                });
                            };
                        }

                        return $field;
                    }, $entityDefinition->getPropertyDefinitions()) + $this->buildEntityInterfaceType()->getFields();
                },
                'meta' => $entityDefinition,
            ]);

            self::$objectTypes[$entityDefinition->getName()] = $objectType;
        }

        return $objectType;
    }

    /**
     * @todo Respect cardinality of relation properties
     */
    protected function buildFieldType(PropertyDefinition $propertyDefinition)
    {
        if ($propertyDefinition->isRelationProperty()) {
            $activeRelations = $propertyDefinition->getActiveRelations();

            if (count($activeRelations) > 1) {
                return Type::listOf($this->buildEntityInterfaceType());
            } else if ($activeRelations[0]->getTo() instanceof PropertyDefinition) {
                return Type::listOf($this->buildObjectType($activeRelations[0]->getTo()->getEntityDefinition()));
            } else {
                return Type::listOf($this->buildObjectType($activeRelations[0]->getTo()));
            }
        } else {
            $scalarTypeMap = [
                'check' => function(PropertyDefinition $propertyDefinition) {
                    return Type::boolean();
                },
                'input' => function(PropertyDefinition $propertyDefinition) {
                    // @todo render types
                    return Type::string();
                },
                'text' => function(PropertyDefinition $propertyDefinition) {
                    return Type::string();
                },
                'radio' => function(PropertyDefinition $propertyDefinition) {
                    return Type::string();
                },
                'select' => function(PropertyDefinition $propertyDefinition) {
                    return Type::string();
                },
                'passthrough' => function(PropertyDefinition $propertyDefinition) {
                    return Type::string();
                },
            ];

            return isset($scalarTypeMap[$propertyDefinition->getPropertyType()])
                ? $scalarTypeMap[$propertyDefinition->getPropertyType()]($propertyDefinition) : Type::string();
        }
    }

    protected static function buildEnumeration(PropertyDefinition $propertyDefinition)
    {
        // @todo items processing function and LLL
        return new EnumType([
            'name' => $propertyDefinition->getName(),
            'values' => array_map(function($item) {
                return [
                    'value' => $item[1],
                    'description' => $item[0]
                ];
            }, $propertyDefinition->getConfiguration()['items'])
        ]);
    }
}