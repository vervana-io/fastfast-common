<?php

namespace FastFast\Common\Publisher;

use Aws\Sns\SnsClient;
use Aws\Result;
use Aws\Sqs\SqsClient;

class Publisher implements PublisherInterface
{
    public function __construct(private SnsClient $client, private SqsClient $sqsClient)
    {
    }

    public function publish($data, $topic = null, $sub = null): array
    {
        $topic = $topic ?? config('consumer.topic_arn');
        try {

            $result =  $this->client->publish([
                'TopicArn' => $topic,
                'Message' => json_encode($data),
                'Subject' => $sub,
                'MessageGroupId' => $data['event'] . $data['order']['id']
            ]);
            return $result->toArray();
        }catch (\Exception $e) {
            return [];
        }
    }

    public function produce($data, $qUrl, $paramAttributes = [], $id = 'order-message-group-id')
    {
        $params = [
            //'DelaySeconds' => $delay,
            'MessageAttributes' => $paramAttributes,
            'MessageBody' => json_encode($data),
            'QueueUrl' => $qUrl,
            'MessageGroupId' => $id,
        ];
        return $this->sqsClient->sendMessage($params);
    }

    public function produceBatch(array $entries, $qUrl)
    {
        // Expecting entries to be an array of AWS SQS SendMessageBatchRequestEntry items
        // with keys: Id, MessageBody, MessageAttributes (optional), MessageGroupId (for FIFO).
        // We'll JSON-encode any associative arrays passed as MessageBody automatically.
        $normalized = [];
        foreach ($entries as $entry) {
            $normalized[] = [
                'Id' => $entry['Id'] ?? uniqid('msg_', true),
                'MessageBody' => is_string($entry['MessageBody']) ? $entry['MessageBody'] : json_encode($entry['MessageBody']),
                'MessageAttributes' => $entry['MessageAttributes'] ?? [],
                // MessageGroupId is required for FIFO queues; if using standard queues it will be ignored
                'MessageGroupId' => $entry['MessageGroupId'] ?? 'notification-group-id',
            ];
        }

        return $this->sqsClient->sendMessageBatch([
            'QueueUrl' => $qUrl,
            'Entries' => $normalized,
        ]);
    }
}