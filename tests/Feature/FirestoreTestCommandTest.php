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
    }

    /**
     * @dataProvider commandOptionProvider
     */
    public function test_command_runs_with_all_options(string $option, string $expectedMethod)
    {
        $this->usageExampleMock->shouldReceive($expectedMethod)->once();

        Artisan::call('fastfast:firestore-test', ['--example' => $option]);

        $this->app[FirestoreTestCommand::class]->handle($this->app[\FastFast\Common\Firestore\FirestoreClient::class]);
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

