<?php

namespace FastFast\Common\Consumer;

use AsyncAws\Sqs\SqsClient;
use AsyncAws\Sqs\Input\ReceiveMessageRequest;
use AsyncAws\Sqs\Input\DeleteMessageRequest;
use AsyncAws\Sqs\ValueObject\Message;
use Illuminate\Console\OutputStyle;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use function React\Async\async;
use function React\Async\await;
class ConsumerAsync
{

    private LoopInterface $loop;
    protected OutputStyle $logger;
    private $checkForMessage = 2;

    private SqsClient $sqsClient;
    private string $queueUrl;

    public function __construct()
    {
        $aws = config('consumer');
        $this->sqsClient = new SqsClient($aws['sqs']);
        $this->queueUrl = $aws['queue'];
        $this->loop = Loop::get();
    }


// --- Asynchronous Message Processing Function (Placeholder) ---
// In a real application, this would involve complex business logic
// and likely other asynchronous I/O operations (DB, HTTP, etc.)
    function processMessageAsync(Message $message): \React\Promise\PromiseInterface
    {
        return async(function () use ($message) {
            echo "  [Processing] Message ID: ". $message->getMessageId(). ", Body: ". $message->getBody(). "\n";
            // Simulate asynchronous work (e.g., API call, DB write)
            await(\React\Promise\Timer\sleep(random_int(1, 3) / 10)); // Simulate 0.1 to 0.3 seconds work
            echo "  [Finished] Processing Message ID: ". $message->getMessageId(). "\n";
        })(); // Immediately invoke the async function
    }
}