<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Tests\Functional;

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

trait EntityReaderTestTrait
{
    /**
     * Sorts a entity reader result by key or by value
     * when the keys are numerical.
     *
     * @param array $result Result to sort
     */
    protected function sortResult(array &$result)
    {
        if (array_keys($result) === range(0, count($result) - 1)
            && count(array_filter(array_values($result), 'is_array')) === count($result)
        ) {
            $keys = [];
            $arguments[] = &$result;

            foreach ($result as $value) {
                $keys = array_merge($keys, array_keys($value));
            }

            $keys = array_unique($keys);

            sort($keys);

            foreach ($keys as $key) {
                $column = [];
                
                foreach ($result as $value) {
                    $column[] = $value[$key] ?? '';
                }

                $arguments[] = SORT_ASC;
                $arguments[] = $column;
            }

            array_multisort(...$arguments);
        } else if ($result !== []) {
            ksort($result);
        }

        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $this->sortResult($result[$key]);
            }
        }
    }
}