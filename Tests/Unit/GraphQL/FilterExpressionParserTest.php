<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Tests\Unit\GraphQL;

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
use TYPO3\CMS\Core\GraphQL\FilterExpressionParser;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/*
* Test case
*/
class FilterExpressionParserTest extends UnitTestCase
{

    public function invalidExpressionProvider()
    {
        return [
            ['foo.bar'],
            ['#foo != bar'],
            ['foo. >= bar'],
            ['foo <= 123bar'],
            ['123foo > bar'],
            ['.foo < bar'],
            ['foo <= .bar'],
            ['(foo < bar'],
            ['foo = bar)('],
            ['foo = 0123'],
            ['foo = .1.2'],
            ['foo = -0123'],
            ['foo = +123'],
            ['foo in [`bar`, 123]'],
            ['(foo = )bar'],
            ['null = null'],
            ['foo = false and false = null'],
        ];
    }

   /**
    * @test
    * @dataProvider invalidExpressionProvider
    */
    public function parseThrowsSyntaxExceptionForInvalidExpressions($expression)
    {
        $this->expectException(\Hoa\Compiler\Exception\Exception::class);
        FilterExpressionParser::parse($expression);
    }

    public function expressionProvider()
    {
        return [
            [
                'foo = bar',
                [
                    'id' => '#equals',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'bar']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'foo.bar != baz',
                [
                    'id' => '#not_equals',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo'],
                                        ['identifier', 'bar']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'baz']
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'not foo.bar.baz >= 123',
                [
                    'id' => '#not',
                    'children' => [
                        [
                            'id' => '#greater_than_equals',
                            'children' => [
                                [
                                    'id' => '#field',
                                    'children' => [
                                        [
                                            'id' => '#path',
                                            'children' => [
                                                ['identifier', 'foo'],
                                                ['identifier', 'bar'],
                                                ['identifier', 'baz']
                                            ]
                                        ]
                                    ]
                                ],
                                ['integer', '123']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'foo.bar != `:`',
                [
                    'id' => '#not_equals',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo'],
                                        ['identifier', 'bar']
                                    ]
                                ]
                            ]
                        ],
                        ['string', '`:`']
                    ]
                ]
            ],
            [
                'foo.bar on baz match `^.*\`.*$`',
                [
                    'id' => '#match',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo'],
                                        ['identifier', 'bar']
                                    ]
                                ],
                                [
                                    'id' => '#constraint',
                                    'children' => [
                                        ['identifier', 'baz']
                                    ]
                                ]
                            ]
                        ],
                        ['string', '`^.*\`.*$`']
                    ]
                ]
            ],
            [
                'foo in [1.23, 12.3, -.123, 0.123]',
                [
                    'id' => '#in',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#list',
                            'children' => [
                                ['float', '1.23'],
                                ['float', '12.3'],
                                ['float', '-.123'],
                                ['float', '0.123']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'foo in [0, 12, -123]',
                [
                    'id' => '#in',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#list',
                            'children' => [
                                ['integer', '0'],
                                ['integer', '12'],
                                ['integer', '-123']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'foo in [`bar`, `baz`, `qux`]',
                [
                    'id' => '#in',
                    'children' => [
                        [
                            'id' => '#field',
                            'children' => [
                                [
                                    'id' => '#path',
                                    'children' => [
                                        ['identifier', 'foo']
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#list',
                            'children' => [
                                ['string', '`bar`'],
                                ['string', '`baz`'],
                                ['string', '`qux`']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'foo = bar or not baz match :qux',
                [
                    'id' => '#or',
                    'children' => [
                        [
                            'id' => '#equals',
                            'children' => [
                                [
                                    'id' => '#field',
                                    'children' => [
                                        [
                                            'id' => '#path',
                                            'children' => [
                                                ['identifier', 'foo']
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    'id' => '#field',
                                    'children' => [
                                        [
                                            'id' => '#path',
                                            'children' => [
                                                ['identifier', 'bar']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#not',
                            'children' => [
                                [
                                    'id' => '#match',
                                    'children' => [
                                        [
                                            'id' => '#field',
                                            'children' => [
                                                [
                                                    'id' => '#path',
                                                    'children' => [
                                                        ['identifier', 'baz']
                                                    ]
                                                ]
                                            ]
                                        ],
                                        [
                                            'id' => '#parameter',
                                            'children' => [
                                                ['identifier', 'qux']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                'not foo = false and bar = baz or qux = true',
                [
                    'id' => '#or',
                    'children' => [
                        [
                            'id' => '#and',
                            'children' => [
                                [
                                    'id' => '#not',
                                    'children' => [
                                        [
                                            'id' => '#equals',
                                            'children' => [
                                                [
                                                    'id' => '#field',
                                                    'children' => [
                                                        [
                                                            'id' => '#path',
                                                            'children' => [
                                                                ['identifier', 'foo']
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                                ['boolean', 'false']
                                            ]
                                        ]
                                    ]
                                ],
                                [
                                    'id' => '#equals',
                                    'children' => [
                                        [
                                            'id' => '#field',
                                            'children' => [
                                                [
                                                    'id' => '#path',
                                                    'children' => [
                                                        ['identifier', 'bar']
                                                    ]
                                                ]
                                            ]
                                        ],
                                        [
                                            'id' => '#field',
                                            'children' => [
                                                [
                                                    'id' => '#path',
                                                    'children' => [
                                                        ['identifier', 'baz']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        [
                            'id' => '#equals',
                            'children' => [
                                [
                                    'id' => '#field',
                                    'children' => [
                                        [
                                            'id' => '#path',
                                            'children' => [
                                                ['identifier', 'qux']
                                            ]
                                        ]
                                    ]
                                ],
                                ['boolean', 'true']
                            ]
                        ]
                    ]
                ]
            ],
            [
                'not (foo = null and (baz = true or foo = :bar))',
                [
                    'id' => '#not',
                    'children' => [
                        [
                            'id' => '#and',
                            'children' => [
                                [
                                    'id' => '#equals',
                                    'children' => [
                                        [
                                            'id' => '#field',
                                            'children' => [
                                                [
                                                    'id' => '#path',
                                                    'children' => [
                                                        ['identifier', 'foo']
                                                    ]
                                                ]
                                            ]
                                        ],
                                        ['null', 'null']
                                    ]
                                ],
                                [
                                    'id' => '#or',
                                    'children' => [
                                        [
                                            'id' => '#equals',
                                            'children' => [
                                                [
                                                    'id' => '#field',
                                                    'children' => [
                                                        [
                                                            'id' => '#path',
                                                            'children' => [
                                                                ['identifier', 'baz']
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                                ['boolean', 'true']
                                            ]
                                        ],
                                        [
                                            'id' => '#equals',
                                            'children' => [
                                                [
                                                    'id' => '#field',
                                                    'children' => [
                                                        [
                                                            'id' => '#path',
                                                            'children' => [
                                                                ['identifier', 'foo']
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                                [
                                                    'id' => '#parameter',
                                                    'children' => [
                                                        ['identifier', 'bar']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
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
            'children' => [$expected]
        ]), FilterExpressionParser::parse($expression));
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