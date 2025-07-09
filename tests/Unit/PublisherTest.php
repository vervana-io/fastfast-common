<?php

namespace Tests\Unit;

use Aws\Sns\SnsClient;
use FastFast\Common\Publisher\Publisher;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    public function test_publish_calls_sns_with_correct_params()
    {
        $snsClient = $this->createMock(SnsClient::class);
        $snsClient->expects($this->once())
            ->method('publish')
            ->with($this->callback(function ($params) {
                return isset($params['TopicArn'], $params['Message']);
            }));

        $publisher = new Publisher($snsClient);
        $publisher->publish(['foo' => 'bar'], 'arn:aws:sns:us-east-1:123456789012:MyTopic');
    }
} 