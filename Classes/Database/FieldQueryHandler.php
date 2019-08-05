<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Database;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.

 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use SebastianBergmann\RecursionContext\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\GraphQL\Event\BeforeValueResolvingEvent;
use TYPO3\CMS\GraphQL\ResolverHelper;

/**
 * @internal
 */
class FieldQueryHandler
{
    public function __invoke(BeforeValueResolvingEvent $event): void
    {
        $builder = $this->getQueryBuilder($event);

        if ($builder === null) {
            return;
        }

        $table = array_pop(QueryHelper::getQueriedTables($builder, QueryHelper::QUERY_PART_FROM));
        $columns = [];

        foreach (ResolverHelper::getFields($event->getInfo(), $table) as $field) {
            $columns[$field->name->value] = $builder->quoteIdentifier($table . '.' . $field->name->value) 
                . ' AS ' . $builder->quoteIdentifier($field->name->value);
        }

        foreach (ResolverHelper::getFields($event->getInfo()) as $field) {
            if (isset($columns[$field->name->value])) {
                continue;
            }

            $columns[] = 'NULL AS ' . $builder->quoteIdentifier($field->name->value);
        }

        if (!empty($columns)) {
            $builder->addSelectLiteral(...array_values($columns));
        }
    }

    protected function getQueryBuilder($event): ?QueryBuilder
    {
        $context = $event->getContext();

        if (isset($context['builder']) && $context['builder'] instanceof QueryBuilder) {
            return $context['builder'];
        }

        return null;
    }
}