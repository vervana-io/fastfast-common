
<?php
namespace App;

use React\EventLoop\LoopInterface;
use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\Input\ReceiveMessageRequest;
use AsyncAws\Sqs\Input\DeleteMessageRequest;
use AsyncAws\Sqs\ValueObject\Message;
use Clue\React\Mq\Queue;
use React\ChildProcess\Process;
use React\Promise\PromiseInterface;
use function React\Async\async;
use function React\Async\await;
use function React\Promise\all;

class SqsConsumer
{
    private LoopInterface $loop;
    private SqsClient $sqsClient;
    private string $queueUrl;
    private Queue $workerPool;
    private string $workerScriptPath;
    private int $maxConcurrentDbWorkers;

    public function __construct(
        LoopInterface $loop,
        SqsClient $sqsClient,
        string $queueUrl,
        string $workerScriptPath,
        int $maxConcurrentDbWorkers = 5
    ) {
        $this->loop = $loop;
        $this->sqsClient = $sqsClient;
        $this->queueUrl = $queueUrl;
        $this->workerScriptPath = $workerScriptPath;
        $this->maxConcurrentDbWorkers = $maxConcurrentDbWorkers;
        $this->workerPool = $this->setupWorkerPool();
    }

    /**
     * Sets up the persistent worker pool for blocking operations.
     * @return Queue
     */
    private function setupWorkerPool(): Queue
    {
        return new Queue(
            $this->maxConcurrentDbWorkers, // Max concurrent jobs (number of active workers) [4]
            null, // Unlimited queue size for pending jobs [4]
            function (array $task): PromiseInterface {
                return async(function () use ($task) {
                    $process = new Process("php {$this->workerScriptPath}");
                    $process->start($this->loop); // Pass the event loop

                    $process->stdin->write(json_encode($task). "\n");
                    $process->stdin->end(); // Close STDIN to signal end of input

                    $output = '';
                    $errorOutput = '';

                    $process->stdout->on('data', function ($chunk) use (&$output) { $output.= $chunk; });
                    $process->stderr->on('data', function ($chunk) use (&$errorOutput) { $errorOutput.= $chunk; });

                    return await(new \React\Promise\Promise(function ($resolve, $reject) use ($process, &$output, &$errorOutput, $task) {
                        $process->on('exit', function ($exitCode, $termSignal) use ($resolve, $reject, &$output, &$errorOutput, $task) {
                            if ($exitCode === 0) {
                                $workerResult = json_decode(trim($output), true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($workerResult['status'])) {
                                    $resolve($workerResult);
                                } else {
                                    $reject(new \RuntimeException("Worker returned invalid response for task {$task['action']}: ". ($output?: 'No output')));
                                }
                            } else {
                                $reject(new \RuntimeException("Worker process failed for task {$task['action']}. Exit code: {$exitCode}, Signal: {$termSignal}, Error: ". ($errorOutput?: 'No stderr output')));
                            }
                        });
                        $process->on('error', function (\Throwable $e) use ($reject, $task) {
                            $reject(new \RuntimeException("Failed to start worker process for task {$task['action']}: ". $e->getMessage()));
                        });
                    }));
                })();
            }
        );
    }

    /**
     * Starts the SQS consumer loop.
     * Polls SQS periodically and processes messages.
     */
    public function start(): void
    {
        echo "Starting SQS consumer for queue: {$this->queueUrl}\n";

        $this->loop->addPeriodicTimer(0.1, async(function () {
            try {
                $request = new ReceiveMessageRequest();
                $request->setWaitTimeSeconds(20); // Long polling [5, 6, 7, 8, 9, 10, 11, 12]
                $request->setMaxNumberOfMessages(10); // Receive up to 10 messages at once

                echo "Polling SQS for messages...\n";
                $result = await($this->sqsClient->receiveMessage($request));
                $messages = $result->getMessages();

                if (empty($messages)) {
                    echo "No messages received. Waiting for next poll cycle.\n";
                    return;
                }

                echo "Received ". count($messages). " message(s).\n";

                $processingPromises =[];
                foreach ($messages as $message) {
                    $processingPromises = $this->processMessage($message)->then(
                        function () use ($message) {
                            // Message processed successfully, now delete it [13, 14]
                            echo "Deleting message ID: ". $message->getMessageId(). "\n";
                            return $this->sqsClient->deleteMessage(new DeleteMessageRequest());
                        },
                        function (\Throwable $e) use ($message) {
                            echo "Error processing message ID ". $message->getMessageId(). ": ". $e->getMessage(). "\n";
                            // Here, you would implement retry logic or move to DLQ [15, 16, 17, 18, 19, 20, 21, 22, 23, 24]
                            // For simplicity, message will reappear after VisibilityTimeout if not deleted.
                        }
                    );
                }

                // Wait for all messages in the current batch to be processed and deleted [25, 16]
                await(all($processingPromises));
                echo "Batch processing complete.\n";

            } catch (\Exception $e) {
                echo "Fatal error in consumer loop: ". $e->getMessage(). "\n";
                // Consider stopping the loop or implementing a backoff before retrying poll
                $this->loop->stop();
            }
        }));
    }

    /**
     * Processes a single SQS message asynchronously by delegating to the worker pool.
     * @param Message $message
     * @return PromiseInterface
     */
    private function processMessage(Message $message): PromiseInterface
    {
        return async(function () use ($message) {
            $messageId = $message->getMessageId();
            $messageBody = $message->getBody();
            echo "  [Processing] Message ID: {$messageId}, Body: {$messageBody}\n";

            $payload = json_decode($messageBody, true); // Assuming message body is JSON

            // 1. Database Operations (via worker pool)
            // Example: Fetch user data
            $userTask = ['action' => 'fetch_user', 'payload' => ['user_id' => $payload['user_id']]];
            $userResult = await($this->workerPool($userTask));

            if ($userResult['status'] === 'error') {
                throw new \RuntimeException("DB fetch failed: ". $userResult['message']);
            }
            echo "  Fetched user: ". json_encode($userResult['data']). "\n";

            // Example: Update order status
            $orderUpdateTask = ['action' => 'update_order_status', 'payload' => ['order_id' => $payload['order_id'], 'status' => 'processed']];
            $orderResult = await($this->workerPool($orderUpdateTask));

            if ($orderResult['status'] === 'error') {
                throw new \RuntimeException("DB update failed: ". $orderResult['message']);
            }
            echo "  Updated order: ". json_encode($orderResult['data']). "\n";

            // 2. Send Notifications (via Laravel's Queued Notifications, dispatched by worker)
            $notificationTask = ['action' => 'send_notifications', 'payload' => [
                'user_ids' => [$payload['user_id']],
                'type' => 'order_processed',
                'data' => ['order_id' => $payload['order_id'], 'status' => 'processed']];
            $notificationResult = await($this->workerPool($notificationTask));

            if ($notificationResult['status'] === 'error') {
                echo "  Notification dispatch failed: ". $notificationResult['message']. "\n";
                // Decide if this should re-queue the SQS message or just log and proceed.
            } else {
                echo "  Notifications dispatched successfully.\n";
            }

            echo "  [Finished] Processing Message ID: {$messageId}\n";
        })();
    }
}