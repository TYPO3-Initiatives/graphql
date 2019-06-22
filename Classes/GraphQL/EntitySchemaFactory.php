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
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Configuration\MetaModel\MultiplicityConstraint;

class EntitySchemaFactory
{
    const ENTITY_INTERFACE_NAME = 'Entity';

    const ENTITY_QUERY_NAME = 'entities';

    const FILTER_ARGUMENT_NAME = 'filter';

    const ORDER_ARGUMENT_NAME = 'order';

    const ENTITY_TYPE_FIELD = '__table';

    /**
     * @var array
     */
    protected static $objectTypes = [];

    /**
     * @var array
     */
    protected static $interfaceTypes = [];

    public function create(EntityRelationMap $entityRelationMap): Schema
    {
        $query = [
            'name' => self::ENTITY_QUERY_NAME,
            'fields' => [],
        ];

        foreach ($entityRelationMap->getEntityDefinitions() as $entityDefinition) {
            $resolver = GeneralUtility::makeInstance(EntityResolver::class, $entityDefinition);
            $type = Type::listOf($this->buildObjectType($entityDefinition));

            $type->config['meta'] = $entityDefinition;

            $query['fields'][$entityDefinition->getName()] = [
                'type' => $type,
                'args' => $resolver->getArguments(),
                'meta' => $entityDefinition,
                'resolve' => function ($source, $arguments, $context, ResolveInfo $info) use ($resolver) {
                    return $resolver->resolve($source, $arguments, $context, $info);
                },
            ];
        }

        return new Schema([
            'query' => new ObjectType($query),
        ]);
    }

    protected function buildEntityInterfaceType(): Type
    {
        if (!array_key_exists(self::ENTITY_INTERFACE_NAME, self::$interfaceTypes)) {
            self::$interfaceTypes[self::ENTITY_INTERFACE_NAME] = new InterfaceType([
                'name' => self::ENTITY_INTERFACE_NAME,
                'fields' => [
                    'uid' => [
                        'type' => Type::id(),
                    ],
                    'pid' => [
                        'type' => Type::int(),
                    ],
                ],
                'resolveType' => function ($value) {
                    return self::$objectTypes[$value[self::ENTITY_TYPE_FIELD]];
                },
            ]);
        }

        return self::$interfaceTypes[self::ENTITY_INTERFACE_NAME];
    }

    protected function buildObjectType(EntityDefinition $entityDefinition): Type
    {
        if (array_key_exists($entityDefinition->getName(), self::$objectTypes)) {
            $objectType = self::$objectTypes[$entityDefinition->getName()];
        } else {
            $objectType = new ObjectType([
                'name' => $entityDefinition->getName(),
                'interfaces' => [
                    $this->buildEntityInterfaceType(),
                ],
                'fields' => function () use ($entityDefinition) {
                    return array_map(function ($propertyDefinition) {
                        $field = [
                            'name' => $propertyDefinition->getName(),
                            'type' => $this->buildFieldType($propertyDefinition),
                            'meta' => $propertyDefinition,
                        ];

                        if (
                            $propertyDefinition->isRelationProperty() &&
                            !$propertyDefinition->isLanguageRelationProperty()
                        ) {
                            $factory = GeneralUtility::makeInstance(EntityRelationResolverFactory::class);
                            $resolver = $factory->create($propertyDefinition);

                            $field['args'] = $resolver->getArguments();
                            $field['resolve'] = function ($source, $arguments, $context, $info) use ($resolver) {
                                $resolver->collect($source, $arguments, $context, $info);

                                return new Deferred(function () use ($resolver, $source, $arguments, $context, $info) {
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

    protected function buildFieldType(PropertyDefinition $propertyDefinition): Type
    {
        $type = $propertyDefinition->isRelationProperty()
            ? $this->buildCompositeFieldType($propertyDefinition) : $this->buildScalarFieldType($propertyDefinition);

        $type->config['meta'] = $propertyDefinition;

        return $type;
    }

    /**
     * @todo Respect cardinality of relation properties
     */
    protected function buildCompositeFieldType(PropertyDefinition $propertyDefinition): Type
    {
        $activeRelations = $propertyDefinition->getActiveRelations();
        $activeRelation = array_pop($activeRelations);

        $type = !empty($activeRelations) ? $this->buildEntityInterfaceType() : $this->buildObjectType(
            $activeRelation->getTo() instanceof PropertyDefinition
                ? $activeRelation->getTo()->getEntityDefinition() : $activeRelation->getTo()
        );

        foreach ($propertyDefinition->getConstraints() as $constraint) {
            if ($constraint instanceof MultiplicityConstraint) {
                if ($constraint->getMinimum() > 0) {
                    $type = Type::nonNull($type);
                }
                if ($constraint->getMaximum() === null || $constraint->getMaximum() > 1) {
                    $type = Type::nonNull(Type::listOf($type));
                }
                break;
            }
        }

        return $type;
    }

    protected function buildScalarFieldType(PropertyDefinition $propertyDefinition): Type
    {
        switch ($propertyDefinition->getPropertyType()) {
            case 'check':
                return Type::boolean();
            default:
                return Type::string();
        }
    }
}