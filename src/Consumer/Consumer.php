<?php

namespace FastFast\Common\Consumer;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Exception;
use FastFast\Common\Consumer\Messages\QueueMessage;
use FastFast\Common\Notifications\Notification;
use FastFast\Common\Util\Accessor;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\all;

class Consumer {

    private LoopInterface $loop;
    protected OutputStyle $logger;
    private $checkForMessage = 2;

    private SqsClient $sqsClient;
    private string $queueUrl;
    protected Notification $notification;

    public function __construct(
    ) {
        $aws = config('consumer');
        $this->sqsClient = new SqsClient($aws['sqs']);
        $this->queueUrl = $aws['queue'];
        $this->loop = Loop::get();
        //$this->checkForMessage = $checkForMessage;
        if (config('app.env') == 'local') {
            $this->checkForMessage = 300;
        }

        $this->notification = new Notification();
    }



    public function setLogger(OutputStyle $logger)
    {
        $this->logger = $logger;
    }

    protected function convertMessage($message): QueueMessage
    {
        $msg = new QueueMessage($message['Body']);
        $msg->setBody($message['Body']);
        $msg->setReceiptHandle($message['ReceiptHandle']);
        $msg->setMessageId($message['MessageId']);
        return $msg;
    }

    protected function getMessageValue($message, $key, $default = null)
    {
        return Accessor::getValue($message, $key, $default);
    }

    private function old(callable $handler)
    {
        $this->loop->addPeriodicTimer(
            $this->checkForMessage,
            function (TimerInterface $timer) use ($handler) {
                $this->info('cheching for messages');
                $this->getMessages($timer, function ($messages) use ($handler) {
                    //$this->logger->info('Getting Message', count($messages));
                    for ($key = 0; $key < count($messages); $key++) {
                        //$this->logger->info('message content', [$messages[$key]['Body']]);
                        $message = $this->convertMessage($messages[$key]);
                        $done = $handler($message->getBody());
                        if ($done) {
                            $this->ackMessage($message);
                        } else {
                            $this->nackMessage($message);
                        }
                    }
                });
            }
        );
    }
    protected function consume(callable $handler)
    {
        $this->loop->addPeriodicTimer(
            $this->checkForMessage,
            function (TimerInterface $timer) use ($handler) {
                $this->getMessages($timer, function ($messages) use ($handler) {
                    $processMessages = [];
                    for ($key = 0; $key < count($messages); $key++) {

                        $message = $this->convertMessage($messages[$key]);
                        $processMessages[] = $this->processMessage($message, $handler);

                    }
                    all($processMessages)->then(function ($results) {
                        $col = new Collection($results);
                        $rejected = $col->where('state', 'rejected');
                        if ($rejected->count() > 0) {
                            //requeue rejected
                            Log::warning('Rejected Promises', $col->toArray());// TODO move to DDL
                        }
                    })->then(function ($results) {
                        $this->logger->info('all processed'. $results);
                    });
                });
            }
        );
    }

    private function processMessage(QueueMessage $message, callable $handler): PromiseInterface
    {
        $differed = new Deferred();
        try {

            $this->logger->info('message content'. $message->getMessageId());

            $done = $handler($message);
            if ($done) {
                $this->ackMessage($message);
            } else {
                $this->nackMessage($message);
            }
            $differed->resolve($done);
        } catch (Exception $exception) {
            //dd($exception); //move to DDL
            $differed->reject($exception);
        }
        return $differed->promise()->then(function ($re) {
            //$this->logger->info('Thenn -------'.$re);
            return $re;
        });
    }

    private function getMessages(TimerInterface $timer, $handler): void
    {
        $result = $this->sqsClient->receiveMessage([
            'AttributeNames'        => ['SentTimestamp'],
            'MaxNumberOfMessages'   => 10,
            'MessageAttributeNames' => ['All'],
            'QueueUrl'              => $this->queueUrl, // REQUIRED
            'WaitTimeSeconds'       => 0,
            'VisibilityTimeout' => 30
        ]);
        //$this->info('Checking for message for -----'. $this->queueUrl, );
        $messages = $result->get('Messages');
        if ($messages != null) {
            $handler($messages);
            $this->checkForMessage = 1;
        } else {
            $this->checkForMessage = 4;
        }
    }

    protected function info($message, $context = null)
    {
        $this->logger->info($message, $context);
    }
    /**
     * Ack message
     *
     * @param $message
     * @throws Exception
     */
    private function ackMessage(QueueMessage $message)
    {
        if ($this->sqsClient == null) {
            throw new Exception("No SQS client defined");
        }

        try {
            $response = $this->sqsClient->deleteMessage([
                'QueueUrl' => $this->queueUrl, // REQUIRED
                'ReceiptHandle' => $message->getReceiptHandle(), // REQUIRED
            ]);
            $this->info('acknowledge response'. $response->count(), $response->toArray());
        } catch (Exception $ex) {
            Log::error($ex->getMessage(), $ex->getTrace());
        }
    }

    /**
     * Nack message
     *
     * @param $message
     * @throws Exception
     */
    private function nackMessage(QueueMessage $message)
    {
        if ($this->sqsClient == null) {
            throw new Exception("No SQS client defined");
        }
        try {
            $this->sqsClient->changeMessageVisibility([
                // VisibilityTimeout is required
                'VisibilityTimeout' => 60,
                'QueueUrl' => $this->queueUrl, // REQUIRED
                'ReceiptHandle' => $message->getReceiptHandle(), // REQUIRED
            ]);
        } catch (SqsException $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
        }
    }

    public function sendMessage(array $message, ?string $queueUrl = null,  $delay = 1)
    {
        $params = [
            'DelaySeconds' => $delay,
            'MessageAttributes' => [],
            'MessageBody' => json_encode($message),
            'QueueUrl' => $queueUrl ?? $this->queueUrl,
        ];
        return $this->sqsClient->sendMessage($params);
    }

    public function stopLoop(): void
    {
        $this->loop->stop();
    }
}
