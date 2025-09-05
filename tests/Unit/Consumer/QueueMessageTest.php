<?php

namespace Tests\Unit\Consumer;

use FastFast\Common\Consumer\Messages\QueueMessage;
use PHPUnit\Framework\TestCase;

class QueueMessageTest extends TestCase
{
    public function test_body_and_basic_accessors()
    {
        $message = new QueueMessage(json_encode(['a' => 1]));
        $this->assertSame(['a' => 1], $message->getBody());

        $message->setProperties(['x' => 'y']);
        $this->assertSame(['x' => 'y'], $message->getProperties());
        $message->setProperty('z', 5);
        $this->assertSame(5, $message->getProperty('z'));

        $message->setHeaders(['h' => 'v']);
        $this->assertSame(['h' => 'v'], $message->getHeaders());
        $message->setHeader('k', 'w');
        $this->assertSame('w', $message->getHeader('k'));

        $message->setAttributes(['a' => 'b']);
        $this->assertSame(['a' => 'b'], $message->getAttributes());
        $this->assertSame('b', $message->getAttribute('a'));
    }

    public function test_fifo_and_visibility_fields()
    {
        $message = new QueueMessage();
        $message->setDelaySeconds(10);
        $this->assertSame(10, $message->getDelaySeconds());
        $message->setMessageDeduplicationId('dedupe');
        $this->assertSame('dedupe', $message->getMessageDeduplicationId());
        $message->setMessageGroupId('group');
        $this->assertSame('group', $message->getMessageGroupId());
        $message->setReceiptHandle('handle');
        $this->assertSame('handle', $message->getReceiptHandle());
        $message->setRequeueVisibilityTimeout(60);
        $this->assertSame(60, $message->getRequeueVisibilityTimeout());
    }

    public function test_header_helpers()
    {
        $message = new QueueMessage();
        $message->setReplyTo('reply');
        $this->assertSame('reply', $message->getReplyTo());
        $message->setCorrelationId('corr');
        $this->assertSame('corr', $message->getCorrelationId());
        $message->setMessageId('mid');
        $this->assertSame('mid', $message->getMessageId());
        $message->setTimestamp(1700000000);
        $this->assertSame(1700000000, $message->getTimestamp());
    }
}


