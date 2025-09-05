<?php

namespace Tests\Unit\Publisher;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Aws\Result;
use FastFast\Common\Publisher\Publisher;
use PHPUnit\Framework\TestCase;

class PublisherTest extends TestCase
{
    public function test_publish_success_and_produce()
    {
        $sns = $this->getMockBuilder(SnsClient::class)->disableOriginalConstructor()->addMethods(['publish'])->getMock();
        $sns->method('publish')->willReturn(new Result(['MessageId' => '1']));
        $sqs = $this->getMockBuilder(SqsClient::class)->disableOriginalConstructor()->addMethods(['sendMessage'])->getMock();
        $sqs->method('sendMessage')->willReturn(new Result(['MessageId' => '1']));

        $publisher = new Publisher($sns, $sqs);
        $res = $publisher->publish(['event' => 'e', 'order' => ['id' => 1]], 'arn:topic');
        $this->assertIsArray($res);

        $publisher->produce(['x' => 1], 'queue-url', 2, ['MessageAttributes' => ['k' => ['StringValue' => 'v', 'DataType' => 'String']]]);
    }
}


