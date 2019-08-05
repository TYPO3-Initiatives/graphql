<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Tests\Functional\EntityReader;

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

use Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\GraphQL\EntityReader;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class LiveLocalizationTest extends FunctionalTestCase
{
    /**
     * @var array
     */
    protected $testExtensionsToLoad = [
        'typo3/sysext/graphql',
        'typo3/sysext/graphql/Tests/Functional/EntityReader/Extensions/persistence',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importDataSet(__DIR__ . '/Fixtures/live-default.xml');
        $this->importDataSet(__DIR__ . '/Fixtures/live-localization.xml');
    }

    public function scalarPropertyQueryProvider()
    {
        return [
            [
                '{
                    pages {
                        uid
                        title
                    }
                }',
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_OFF, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['uid' => 133, 'title' => 'Seite 2'],
                            ['uid' => 134, 'title' => 'Seite 3'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages {
                        uid
                        title
                    }
                }',
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_MIXED, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['uid' => 130, 'title' => 'Page 1.2'],
                            ['uid' => 131, 'title' => 'Seite 1'],
                            ['uid' => 132, 'title' => 'Seite 1.1'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages {
                        uid
                        title
                    }
                }',
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_ON, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['uid' => 131, 'title' => 'Seite 1'],
                            ['uid' => 132, 'title' => 'Seite 1.1'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages {
                        uid
                        title
                    }
                }',
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_ON_WITH_FLOATING, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['uid' => 131, 'title' => 'Seite 1'],
                            ['uid' => 132, 'title' => 'Seite 1.1'],
                            ['uid' => 133, 'title' => 'Seite 2'],
                            ['uid' => 134, 'title' => 'Seite 3'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider scalarPropertyQueryProvider
     */
    public function readScalarProperty(string $query, array $aspects, array $expected)
    {
        $reader = new EntityReader();
        $result = $reader->execute($query, [], new Context($aspects));
        $this->assertEquals($expected, $result);
    }

    public function relationPropertyQueryProvider()
    {
        return [
            [
                '{
                    tx_persistence_entity {
                        uid
                        title
                        relation_inline_1n_file_reference {
                            uid
                            title
                            uid_local {
                                identifier
                            }
                        }
                    }
                }',
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_ON, []),
                ],
                [
                    'data' => [
                        'tx_persistence_entity' => [
                            [
                                'uid' => 1029, 
                                'title' => 'Entität 1', 
                                'relation_inline_1n_file_reference' => [
                                    [
                                        'uid' => 262,
                                        'title' => 'Dateiverweis 2',
                                        'uid_local' => [
                                            'identifier' => '/image_2.jpg'
                                        ]
                                    ],
                                    [
                                        'uid' => 263,
                                        'title' => 'Dateiverweis 3',
                                        'uid_local' => [
                                            'identifier' => '/image_3.jpg'
                                        ]
                                    ],
                                ]
                            ],
                            [
                                'uid' => 1030, 
                                'title' => 'Entität 2', 
                                'relation_inline_1n_file_reference' => []
                            ],
                            [
                                'uid' => 1031, 
                                'title' => 'Entität 3', 
                                'relation_inline_1n_file_reference' => []
                            ],
                            [
                                'uid' => 1032, 
                                'title' => 'Entität 4', 
                                'relation_inline_1n_file_reference' => []
                            ],
                            [
                                'uid' => 1033, 
                                'title' => 'Entität 5', 
                                'relation_inline_1n_file_reference' => []
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider relationPropertyQueryProvider
     */
    public function readRelationProperty(string $query, array $aspects, array $expected)
    {
        $reader = new EntityReader();
        $result = $reader->execute($query, [], new Context($aspects));
        $this->assertEquals($expected, $result);
    }

    public function orderResultQueryProvider()
    {
        return [
        ];
    }

    /**
     * @test
     * @dataProvider orderResultQueryProvider
     */
    public function orderResult(string $query, array $expected)
    {
        $reader = new EntityReader();
        $result = $reader->execute($query);
        $this->assertEquals($expected, $result);
    }

    public function filterRestrictedQueryProvider()
    {
        return [
            [
                '{
                    pages(filter: "title = `Seite 2`") {
                        title
                    }
                }',
                [],
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_OFF, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['title' => 'Seite 2'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages(filter: "title in [`Seite 1.1`, `Page 1.2`]") {
                        title
                    }
                }',
                [],
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_MIXED, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['title' => 'Page 1.2'],
                            ['title' => 'Seite 1.1'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages(filter: "title != `Seite 1.1`") {
                        title
                    }
                }',
                [],
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_ON, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['title' => 'Seite 1'],
                        ],
                    ],
                ],
            ],
            [
                '{
                    pages(filter: "`Seite 1` = title or `Seite 3` = title") {
                        title
                    }
                }',
                [],
                [
                    'language' => new LanguageAspect(2, null, LanguageAspect::OVERLAYS_ON_WITH_FLOATING, []),
                ],
                [
                    'data' => [
                        'pages' => [
                            ['title' => 'Seite 1'],
                            ['title' => 'Seite 3'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider filterRestrictedQueryProvider
     */
    public function readFilterRestricted(string $query, array $bindings, array $aspects, array $expected)
    {
        $reader = new EntityReader();
        $result = $reader->execute($query, $bindings, new Context($aspects));
        $this->assertEquals($expected, $result);
    }

    public function unsupportedQueryProvider()
    {
        return [
        ];
    }

    /**
     * @test
     * @dataProvider unsupportedQueryProvider
     */
    public function throwUnsupported(string $query, string $exceptionClass, int $exceptionCode)
    {
        try {
            $reader = new EntityReader();
            $reader->execute($query);
        } catch (Exception $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            $this->assertEquals($exceptionCode, $exception->getCode());
        }
    }

    public function invalidQueryProvider()
    {
        return [
        ];
    }

    /**
     * @test
     * @dataProvider invalidQueryProvider
     */
    public function throwInvalid(string $query, string $exceptionClass, int $exceptionCode)
    {
        try {
            $reader = new EntityReader();
            $reader->execute($query);
        } catch (Exception $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            $this->assertEquals($exceptionCode, $exception->getCode());
        }
    }
}
