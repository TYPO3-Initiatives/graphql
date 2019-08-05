<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Database;

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

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use TYPO3\CMS\Core\Configuration\MetaModel\EntityDefinition;
use TYPO3\CMS\Core\Configuration\MetaModel\PropertyDefinition;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;

/**
 * @internal
 */
class FilterQueryHandler
{
    public function __invoke(BeforeValueResolvingEvent $event): void
    {
        $context = $event->getContext();

        if (!isset($context['builder']) || !$context['builder'] instanceof QueryBuilder) {
            return;
        }

        $type = $event->getResolver()->getType();

        if (!isset($type->config['meta'])) {
            return;
        }
        
        $meta = $type->config['meta'];
        
        if (!$meta instanceof PropertyDefinition && !$meta instanceof EntityDefinition) {
            return;
        }

        $arguments = $event->getArguments();
        $builder = $context['builder'];
        
        $processor = GeneralUtility::makeInstance(
            FilterExpressionProcessor::class,
            $event->getInfo(),
            $builder,
            function (QueryBuilder $builder, string $operator, array ...$operands) use ($event) {
                $table = array_pop(QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_FROM));
                $operands = array_map(function ($operand) use ($builder, $event, $table) {
                    return isset($operand['identifier'])
                        ? $builder->quoteIdentifier($table . '.' . $operand['identifier'])
                        : $builder->createNamedParameter($operand['value'], $operand['type']);
                }, $operands);

                if (count($operands) === 2) {
                    return $builder->expr()->comparison(
                        $operands[0],
                        $operator,
                        in_array($operator, ['IN', 'NOT IN']) ? '(' . $operands[1] . ')' : $operands[1]
                    );
                } elseif (count($operands) === 1) {
                    return $operands[0] . ' ' . $operator;
                }

                throw new Exception('Unexpected filter expression leaf', 1564352799);
            }
        );

        $condition = $processor->process($arguments[FilterArgumentProvider::ARGUMENT_NAME] ?? null);

        if ($condition !== null) {
            $builder->andWhere($condition);
        }
    }
}