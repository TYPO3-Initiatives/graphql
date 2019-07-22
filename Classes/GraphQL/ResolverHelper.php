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

use GraphQL\Language\AST\NodeKind;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * @internal
 */
class ResolverHelper
{
    /**
     * Returns all fields of the given resolve info.
     *
     * @param ResolveInfo $info Resolve to search in
     * @param string $type Type name to to restrict the fields on
     * @return SelectionNode[] List of all fields
     * @todo Support fragment spreads
     */
    public static function getFields(ResolveInfo $info, string $type = null): array
    {
        $fields = [];

        foreach ($info->fieldNodes as $fieldNode) {
            foreach ($fieldNode->selectionSet->selections as $selection) {
                if ($selection->kind === NodeKind::FIELD) {
                    $fields[] = $selection;
                } elseif ($selection->kind === NodeKind::INLINE_FRAGMENT
                    && ($type === null || $selection->typeCondition->name->value === $type)
                ) {
                    foreach ($selection->selectionSet->selections as $selection) {
                        if ($selection->kind === NodeKind::FIELD) {
                            $fields[] = $selection;
                        }
                    }
                }
            }
        }

        return $fields;
    }
}