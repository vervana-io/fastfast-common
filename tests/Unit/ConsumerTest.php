<?php

namespace Tests\Unit;

use Aws\Sqs\SqsClient;
use FastFast\Common\Consumer\Consumer;
use PHPUnit\Framework\TestCase;

class ConsumerTest extends TestCase
{
    public function test_send_message_calls_sqs()
    {
        $sqsClient = $this->createMock(SqsClient::class);
        $sqsClient->expects($this->once())
            ->method('sendMessage')
            ->with($this->arrayHasKey('MessageBody'));

        $consumer = $this->getMockBuilder(Consumer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $consumer->sqsClient = $sqsClient;
        $consumer->queueUrl = 'test-url';

        $consumer->sendMessage(['foo' => 'bar']);
    }
} 