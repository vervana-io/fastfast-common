<?php

namespace Tests\Unit;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use FastFast\Common\Publisher\Publisher;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    public function test_publish_calls_sns_with_correct_params()
    {
        $captured = [];
        $snsClient = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->addMethods(['publish'])->getMock();
        $snsClient->method('publish')->willReturnCallback(function ($args) use (&$captured) {
            $captured[] = $args;
            return new \Aws\Result(['MessageId' => '1']);
        });
        $sqsClient = $this->getMockBuilder(\Aws\Sqs\SqsClient::class)->disableOriginalConstructor()->getMock();
        $publisher = new Publisher($snsClient, $sqsClient);
        $publisher->publish([
            'event' => 'order_created',
            'order' => ['id' => 123],
            'payload' => ['foo' => 'bar']
        ], 'arn:aws:sns:us-east-1:123456789012:MyTopic');
        $this->assertNotEmpty($captured);
        $this->assertArrayHasKey('TopicArn', $captured[0]);
    }
} 