<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Tests\Unit;

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

use Hoa\Compiler\Llk\TreeNode;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\OrderExpressionParser;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/*
 * Test case
 */
class OrderExpressionParserTest extends UnitTestCase
{
    /**
     * @inheritdoc
     */
    protected $resetSingletonInstances = true;

    public function invalidExpressionProvider()
    {
        return [
            [''],
            ['foo'],
            ['123foo bar'],
            ['foo, bar descending'],
            [',foo ascending'],
            ['.foo ascending'],
            ['on ascending'],
            ['ascending ascending'],
            ['foo,ascending'],
            ['foo.ascending'],
            ['foo.ascending descending'],
            ['foo ascending,,,,,bar descending'],
            ['foo ascending,.bar descending'],
            ['on foo ascending'],
            ['on ascending foo'],
            ['foo on descending ascending'],
            ['ascending on descending'],
            ['ascending on descending ascending'],
        ];
    }

   /**
    * @test
    * @dataProvider invalidExpressionProvider
    */
    public function parseThrowsSyntaxExceptionForInvalidExpressions($expression)
    {
        $this->expectException(\Hoa\Compiler\Exception\Exception::class);

        GeneralUtility::makeInstance(OrderExpressionParser::class)->parse($expression);
    }

    public function expressionProvider()
    {
        return [
            [
                'foo ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                '   foo     ascending   ',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                'foo.bar ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                '   foo   .    bar    ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                'foo descending, bar ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                'foo descending         ,bar ascending ',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                'foo.bar descending, bar.foo ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                '  foo       . bar descending           ,    bar .          foo ascending    ',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                'foo on bar ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            [
                                'id' => '#constraint',
                                'children' => [
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                '   foo      on    bar descending        ',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                ],
                            ],
                            [
                                'id' => '#constraint',
                                'children' => [
                                    ['identifier', 'bar'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                ],
            ],
            [
                'foo.bar on baz descending, bar on qux ascending',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            [
                                'id' => '#constraint',
                                'children' => [
                                    ['identifier', 'baz'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                ],
                            ],
                            [
                                'id' => '#constraint',
                                'children' => [
                                    ['identifier', 'qux'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
            [
                '  foo       . bar    on baz          descending           ,    bar .          foo ascending    ',
                [
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'foo'],
                                    ['identifier', 'bar'],
                                ],
                            ],
                            [
                                'id' => '#constraint',
                                'children' => [
                                    ['identifier', 'baz'],
                                ],
                            ],
                            ['order', 'descending'],
                        ],
                    ],
                    [
                        'id' => '#field',
                        'children' => [
                            [
                                'id' => '#path',
                                'children' => [
                                    ['identifier', 'bar'],
                                    ['identifier', 'foo'],
                                ],
                            ],
                            ['order', 'ascending'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider expressionProvider
     */
    public function parseValidExpressions($expression, $expected)
    {
        $this->assertEquals($this->buildTree([
            'id' => '#expression',
            'children' => $expected,
        ]), GeneralUtility::makeInstance(OrderExpressionParser::class)->parse($expression));
    }

    protected function buildTree(array $array)
    {
        $parent = new TreeNode($array['id'], null);

        foreach ($array['children'] as $item) {
            if (array_key_exists('id', $item)) {
                $child = $this->buildTree($item);
            } else {
                $child = new TreeNode('token', ['namespace' => 'default', 'token' => $item[0], 'value' => $item[1]]);
            }
            $child->setParent($parent);
            $parent->appendChild($child);
        }

        return $parent;
    }
}