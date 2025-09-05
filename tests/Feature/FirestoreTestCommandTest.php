<?php

namespace Tests\Feature;

use FastFast\Common\Console\FirestoreTestCommand;
use FastFast\Common\Firestore\Examples\FirestoreUsageExample;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use Tests\TestCase;

class FirestoreTestCommandTest extends TestCase
{
    protected MockInterface $usageExampleMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usageExampleMock = $this->mock(FirestoreUsageExample::class);
        $mockRef = &$this->usageExampleMock;
        $this->app->bind(FirestoreUsageExample::class, function ($app, $params = []) use (&$mockRef) {
            return $mockRef;
        });
    }

    /**
     * @dataProvider commandOptionProvider
     */
    public function test_command_runs_with_all_options(string $option, string $expectedMethod)
    {
        $this->usageExampleMock->shouldReceive($expectedMethod)->once();
        Artisan::call('fastfast:firestore-test', ['--example' => $option]);
    }

    public static function commandOptionProvider(): array
    {
        return [
            'add' => ['add', 'addSingleDocument'],
            'delete' => ['delete', 'deleteDocument'],
            'delete-async' => ['delete-async', 'deleteDocumentAsync'],
            'delete-bulk' => ['delete-bulk', 'deleteMultipleDocuments'],
            'bulk' => ['bulk', 'addMultipleDocuments'],
            'get' => ['get', 'getDocument'],
            'update' => ['update', 'updateDocument'],
            'complex' => ['complex', 'complexDataExample'],
            'search' => ['search', 'searchDocuments'],
            'search-async' => ['search-async', 'searchDocumentsAsync'],
            'search-paginated' => ['search-paginated', 'searchDocumentsPaginated'],
            'delete-all' => ['delete-all', 'deleteAllDocuments'],
            'count-condition' => ['count-condition', 'countDocumentsByCondition'],
            'all' => ['all', 'runAllExamples'],
            'default' => ['', 'runAllExamples'],
        ];
    }
}




