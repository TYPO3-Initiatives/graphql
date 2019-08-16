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

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\DecimalType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use Doctrine\DBAL\Types\SmallIntType;
use GraphQL\Deferred;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityRelationMap;
use TYPO3\CMS\Core\Configuration\MetaModel\MultiplicityConstraint;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Event\BeforeFieldArgumentsInitializationEvent;

/**
 * @internal
 */
class EntitySchemaFactory
{
    const ENTITY_INTERFACE_NAME = 'Entity';

    const ENTITY_QUERY_NAME = 'entities';

    const ENTITY_TYPE_FIELD = '__table';

    /**
     * @var array
     */
    protected static $objectTypes = [];

    /**
     * @var array
     */
    protected static $interfaceTypes = [];

    /**
     * @var ResolverFactory
     */
    protected $resolverFactory;

    public function __construct()
    {
        $this->resolverFactory = GeneralUtility::makeInstance(ResolverFactory::class);
    }

    public function create(EntityRelationMap $entityRelationMap): Schema
    {
        $query = [
            'name' => self::ENTITY_QUERY_NAME,
            'fields' => [],
        ];

        $dispatcher = $this->getEventDispatcher();

        foreach ($entityRelationMap->getEntityDefinitions() as $entityDefinition) {
            $type = Type::listOf($this->buildObjectType($entityDefinition));
            $type->config['meta'] = $entityDefinition;

            $name = $entityDefinition->getName();
            $event = new BeforeFieldArgumentsInitializationEvent($name, $type);

            $dispatcher->dispatch($event);

            $resolver = $this->resolverFactory->create($type);

            $query['fields'][$name] = [
                'type' => $type,
                'args' => array_replace_recursive($resolver->getArguments(), $event->getArguments()),
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
                    $table = GeneralUtility::makeInstance(ConnectionPool::class)
                        ->getConnectionForTable($entityDefinition->getName())
                        ->getSchemaManager()
                        ->listTableDetails($entityDefinition->getName());

                    $propertyDefinitions = array_filter(
                        $entityDefinition->getPropertyDefinitions(),
                        function ($propertyDefinition) use ($table) {
                            return $table->hasColumn($propertyDefinition->getName());
                        }
                    );

                    return array_map(function ($propertyDefinition) {
                        $type = $this->buildFieldType($propertyDefinition);

                        $name = $propertyDefinition->getName();
                        $event = new BeforeFieldArgumentsInitializationEvent($name, $type);

                        $this->getEventDispatcher()->dispatch($event);

                        $field = [
                            'name' => $name,
                            'type' => $type,
                            'meta' => $propertyDefinition,
                        ];

                        if ($propertyDefinition->isRelationProperty()
                            && !$propertyDefinition->isLanguageRelationProperty()
                        ) {
                            $resolver = $this->resolverFactory->create($type);

                            $field['args'] = array_replace_recursive(
                                $resolver->getArguments(),
                                $event->getArguments()
                            );

                            $field['resolve'] = function ($source, $arguments, $context, $info) use ($resolver) {
                                $resolver->collect($source, $arguments, $context, $info);

                                return new Deferred(function () use ($resolver, $source, $arguments, $context, $info) {
                                    return $resolver->resolve($source, $arguments, $context, $info);
                                });
                            };
                        }

                        return $field;
                    }, $propertyDefinitions) + $this->buildEntityInterfaceType()->getFields();
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
        $type = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($propertyDefinition->getEntityDefinition()->getName())
            ->getSchemaManager()
            ->listTableDetails($propertyDefinition->getEntityDefinition()->getName())
            ->getColumn($propertyDefinition->getName())
            ->getType();

        if ($type instanceof IntegerType || $type instanceof BigIntType || $type instanceof SmallIntType) {
            return Type::int();
        } else if ($type instanceof FloatType || $type instanceof DecimalType) {
            return Type::float();
        } else if ($type instanceof BooleanType) {
            return Type::boolean();
        }
        
        return Type::string();
    }

    protected function getEventDispatcher(): EventDispatcherInterface
    {
        return GeneralUtility::makeInstance(EventDispatcher::class);
    }
}