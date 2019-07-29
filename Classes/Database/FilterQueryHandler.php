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
                $operands = array_map(function ($operand) use ($builder, $event) {
                    return isset($operand['identifier'])
                        ? $this->getFieldExpression($operand['identifier'], $event->getInfo(), $builder)
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

    protected function getFieldExpression(string $field, ResolveInfo $info, QueryBuilder $builder): string
    {
        $table = array_pop(QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_FROM));
        $joinTables = QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_JOIN);
        $languageOverlayTables = QueryHelper::filterLanguageOverlayTables($joinTables);

        if (count($languageOverlayTables) > 0) {
            $type = $info->schema->getType($table)->getField($field)->type;
            $emptyValue = $type instanceof StringType ? '\'\'' : '0';

            $expression = 'COALESCE(';

            foreach ($languageOverlayTables as $languageOverlayTableAlias => $languageOverlayTable) {
                $expression .= 'NULLIF(' . $builder->quoteIdentifier(
                    $languageOverlayTableAlias . '.' . $field
                ) . ',' . $emptyValue . '),';
            }

            $expression .= $builder->quoteIdentifier($table . '.' . $field) . ',NULL)';

            return $expression;
        }

        return $builder->quoteIdentifier($table . '.' . $field);
    }
}