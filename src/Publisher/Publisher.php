<?php

namespace FastFast\Common\Publisher;

use Aws\Sns\SnsClient;
use Aws\Result;

class Publisher implements PublisherInterface
{
    public function __construct(private SnsClient $client)
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
}