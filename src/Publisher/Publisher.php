<?php

namespace FastFast\Common\Publisher;

use Aws\Sns\SnsClient;
use Aws\Result;

class Publisher implements PublisherInterface
{
    public function __construct(private SnsClient $client)
    {
    }

    public function publish($data, $topic, $sub = null): Result
    {
        return $this->client->publish([
            'TopicArn' => $topic,
            'Message' => json_encode($data),
            'Subject' => $sub
        ]);
    }
}