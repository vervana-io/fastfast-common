<?php

namespace FastFast\Common\Consumer;

use Aws\Sqs\Exception\SqsException;
use Aws\Sqs\SqsClient;
use Exception;
use FastFastCommon\Util\Accessor;
use Illuminate\Support\Collection;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use FastFastCommon\Consumer\Messages\QueueMessage;

class Consumer {

    private LoopInterface $loop;
    private $checkForMessage = 2;

    private SqsClient $sqsClient;
    private string $queueUrl;

    public function __construct(
        $checkForMessage,
    ) {
        $aws = config('consumer');
        if (!$this->sqsClient) {
            $this->sqsClient = new SqsClient($aws['sqs']);
        }
        $this->queueUrl = $aws['queue_url'];
        $this->loop = Loop::get();
        $this->checkForMessage = $checkForMessage;
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
                $this->logger->info("Checking for messages", ['timer' => $this->checkForMessage]);
                $this->getMessages($timer, function ($messages) use ($handler) {

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
                $this->logger->info("Checking for messages", ['timer' => $this->checkForMessage]);
                $this->getMessages($timer, function ($messages) use ($handler) {
                    $promises = [];
                    for ($key = 0; $key < count($messages); $key++) {
                        //$this->logger->info('message content', [$messages[$key]['Body']]);
                        $message = $this->convertMessage($messages[$key]);
                        $promises[] = $this->processMessage($message, $handler);
                    }
                    \GuzzleHttp\Promise\Utils::settle($promises)->then(function ($results) {
                        $col = new Collection($results);
                        $rejected = $col->where('state', 'rejected')->count();
                        if ($rejected > 0) {
                            //requeue rejected
                        }
                    });
                });
            }
        );
    }

    private function processMessage(QueueMessage $message, callable$handler): PromiseInterface
    {
        $differed = new Deferred();
        try {
            $done = $handler($message);
            if ($done) {
                $this->ackMessage($message);
            } else {
                $this->nackMessage($message);
            }
        } catch (\Exception $exception) {
            $differed->reject($exception);
        }
        return $differed->promise();
    }

    private function getMessages(TimerInterface $timer, $handler): void
    {
        $this->logger->info('QueueURL', [$this->queueUrl]);
        $result = $this->sqsClient->receiveMessage([
            'AttributeNames'        => ['SentTimestamp'],
            'MaxNumberOfMessages'   => 10,
            'MessageAttributeNames' => ['All'],
            'QueueUrl'              => $this->queueUrl, // REQUIRED
            'WaitTimeSeconds'       => 0,
            'VisibilityTimeout' => 30
        ]);

        $messages = $result->get('Messages');
        if ($messages != null) {
            $handler($messages);
            $this->checkForMessage = 1;
        } else {
            $this->checkForMessage = 10;
        }
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
            $this->logger->info('acknowledge response', $response->toArray());
        } catch (Exception $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());
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
            $this->logger->error($exception->getMessage(), $exception->getTrace());
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
