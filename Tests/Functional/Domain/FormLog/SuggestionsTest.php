<?php

declare(strict_types=1);

namespace Pagemachine\Formlog\Tests\Functional\Domain\FormLog;

use Pagemachine\Formlog\Domain\FormLog\Suggestions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Testcase for Pagemachine\Formlog\Domain\FormLog\Suggestions
 */
final class SuggestionsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/formlog',
    ];

    #[DataProvider('properties')]
    #[Test]
    public function returnsSuggestionsForProperty(string $property, array $expected): void
    {
        $data = [
            'pages' => [
                [
                    'uid' => 1,
                    'title' => 'Bar form page',
                ],
                [
                    'uid' => 2,
                    'title' => 'Foo form page',
                ],
                [
                    'uid' => 3,
                    'title' => 'Other page',
                ],
            ],
            'tx_formlog_entries' => [
                [
                    'uid' => 1,
                    'page' => 2,
                    'identifier' => 'foo',
                ],
                [
                    'uid' => 2,
                    'page' => 2,
                    'identifier' => 'foo',
                ],
                [
                    'uid' => 3,
                    'page' => 1,
                    'identifier' => 'bar',
                ],
            ],
        ];
        $datbaseConnection = $this->getConnectionPool()->getConnectionByName('Default');

        foreach ($data as $table => $records) {
            foreach ($records as $record) {
                $datbaseConnection->insert($table, $record);
            }
        }

        $suggestions = new Suggestions(GeneralUtility::makeInstance(ConnectionPool::class));

        $result = $suggestions->getForProperty($property);

        self::assertEquals($expected, $result);
    }

    public static function properties(): \Generator
    {
        yield 'basic' => [
            'identifier',
            [
                'bar',
                'foo',
            ],
        ];

        yield 'page' => [
            'page.title',
            [
                'Bar form page',
                'Foo form page',
            ],
        ];
    }
}
