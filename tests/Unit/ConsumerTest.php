<?php

namespace Tests\Unit;

use Aws\Sqs\SqsClient;
use FastFast\Common\Consumer\Consumer;
use PHPUnit\Framework\TestCase;

class ConsumerTest extends TestCase
{
    public function test_send_message_calls_sqs()
    {
        $captured = [];
        $fakeSqs = $this->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->addMethods(['sendMessage'])
            ->getMock();
        $fakeSqs->method('sendMessage')->willReturnCallback(function ($args) use (&$captured) {
            $captured[] = $args;
            return new \Aws\Result(['MessageId' => '1']);
        });

        $consumer = $this->getMockBuilder(Consumer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Inject private properties via reflection
        $refClient = new \ReflectionProperty(Consumer::class, 'sqsClient');
        $refClient->setAccessible(true);
        $refClient->setValue($consumer, $fakeSqs);

        $refQueue = new \ReflectionProperty(Consumer::class, 'queueUrl');
        $refQueue->setAccessible(true);
        $refQueue->setValue($consumer, 'test-url');

        $consumer->sendMessage(['foo' => 'bar']);
        $this->assertNotEmpty($captured);
        $this->assertArrayHasKey('MessageBody', $captured[0]);
    }
} 