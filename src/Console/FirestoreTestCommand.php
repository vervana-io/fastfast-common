<?php

namespace FastFast\Common\Console;

use Illuminate\Console\Command;
use FastFast\Common\Firestore\FirestoreClient;
use FastFast\Common\Firestore\Examples\FirestoreUsageExample;

class FirestoreTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fastfast:firestore-test {--example=all : Which example to run (all, add, delete, delete-async, delete-bulk, bulk, get, update, complex)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Firestore client functionality';

    /**
     * Execute the console command.
     */
    public function handle(FirestoreClient $firestore): int
    {
        $example = $this->option('example');
        $usageExample = new FirestoreUsageExample($firestore);

        $this->info('Testing Firestore Client...');
        $this->info('Project ID: ' . $firestore->getProjectId());

        try {
            switch ($example) {
                case 'add':
                    $this->info('Running single document add example...');
                    $usageExample->addSingleDocument();
                    break;
                    
                case 'delete':
                    $this->info('Running delete document example...');
                    $usageExample->deleteDocument();
                    break;
                    
                case 'delete-async':
                    $this->info('Running async delete document example...');
                    $usageExample->deleteDocumentAsync();
                    break;
                    
                case 'delete-bulk':
                    $this->info('Running bulk delete documents example...');
                    $usageExample->deleteMultipleDocuments();
                    break;
                    
                case 'bulk':
                    $this->info('Running bulk add documents example...');
                    $usageExample->addMultipleDocuments();
                    break;
                    
                case 'get':
                    $this->info('Running get document example...');
                    $usageExample->getDocument();
                    break;
                    
                case 'update':
                    $this->info('Running update document example...');
                    $usageExample->updateDocument();
                    break;
                    
                case 'complex':
                    $this->info('Running complex data example...');
                    $usageExample->complexDataExample();
                    break;
                    
                case 'all':
                default:
                    $this->info('Running all examples...');
                    $usageExample->runAllExamples();
                    break;
            }

            $this->info('Firestore test completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Firestore test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
