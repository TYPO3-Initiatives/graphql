<?php
declare(strict_types = 1);

namespace TYPO3\CMS\GraphQL\Tests\Functional\Database;

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

use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Tests\Functional\DataHandling\AbstractDataHandlerActionTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\GraphQL\Database\Query\ContextAwareQueryBuilder;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\DataSet;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class ContextAwareQueryBuilderTest extends FunctionalTestCase
{
    /**
     * @var string
     */
    protected $dataSetDirectory = __DIR__ . '/../Fixtures/DataSet/';

    /**
     * @var array
     */
    protected $coreExtensionsToLoad = ['workspaces'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet($this->dataSetDirectory . 'WorkspaceQueryScenario.csv');
    }

    /**
     * @param string $dataSetName
     */
    protected function importScenarioDataSet($dataSetName)
    {
        $fileName = rtrim($this->dataSetDirectory, '/') . '/' . $dataSetName . '.csv';
        $fileName = GeneralUtility::getFileAbsFileName($fileName);
        $this->importCSVDataSet($fileName);
    }

    public function resultScenarioProvider()
    {
        return [
            [0, -1, 'pages', 'LiveQueryResult.csv'],
            [1, -1, 'pages', 'DraftQueryResult.csv'],
            [0, -1, 'tt_content', 'LiveQueryResult.csv'],
            [1, -1, 'tt_content', 'DraftQueryResult.csv'],
            [1, 0, 'tt_content', 'LocalizedDraftQueryResult.csv'],
        ];
    }

    /**
     * @param string $tableName
     * @param string $dataSetFile
     *
     * @test
     * @dataProvider resultScenarioProvider
     */
    public function shouldReturnResultScenarioInAnyOrder(int $workspaceIdentifier, int $languageIdentifier, string $tableName, string $dataSetFile)
    {
        $context = clone GeneralUtility::makeInstance(Context::class);
        $context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, $workspaceIdentifier));
        $context->setAspect('language', GeneralUtility::makeInstance(LanguageAspect::class, $languageIdentifier));

        $dataSet = DataSet::read($this->dataSetDirectory . $dataSetFile);

        $subject = GeneralUtility::makeInstance(
            ContextAwareQueryBuilder::class,
            $this->getConnectionPool()->getConnectionForTable($tableName),
            $context
        );

        $actual = $subject
            ->select(...$dataSet->getFields($tableName))
            ->from($tableName)
            ->execute()
            ->fetchAll(FetchMode::ASSOCIATIVE);

        $expected = $dataSet->getElements($tableName);

        $this->assertEquals($expected, $this->applyRecordIndex($actual));
    }

    private function applyRecordIndex(array $records): array
    {
        $values = array_values($records);
        $keys = array_map(
            function(array $record) {
                return $record['uid'] ?? $record->uid ?? $record[0] ?? null;
            },
            $values
        );
        return array_combine($keys, $values);
    }
}
