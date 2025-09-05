<?php

namespace Tests\Unit\Firestore;

use FastFast\Common\Firestore\FirestoreClient;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class FirestoreClientTest extends TestCase
{
    private function setHttpClient(FirestoreClient $client, $http)
    {
        $ref = new \ReflectionProperty(FirestoreClient::class, 'httpClient');
        $ref->setAccessible(true);
        $ref->setValue($client, $http);
    }

    public function test_add_document_post_and_patch()
    {
        $http = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->onlyMethods(['request'])->getMock();
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function ($method, $url, $opts) {
                if ($method === 'POST' && str_contains($url, '/documents/col?key=')) {
                    return new Response(200, [], json_encode(['name' => 'projects/p/databases/(default)/documents/col/docX']));
                }
                if ($method === 'PATCH' && str_contains($url, '/documents/col/doc-1?key=')) {
                    return new Response(200, [], json_encode(['name' => 'projects/p/databases/(default)/documents/col/doc-1']));
                }
                $this->fail('Unexpected request: ' . $method . ' ' . $url);
            });

        $client = new FirestoreClient('p', 'k', '(default)');
        $this->setHttpClient($client, $http);
        $client->addDocument('col', ['a' => 1]);
        $client->addDocument('col', ['a' => 1], 'doc-1');
        $this->assertTrue(true);
    }

    public function test_get_document_returns_array_or_null()
    {
        $http = $this->getMockBuilder(Client::class)->disableOriginalConstructor()->onlyMethods(['get'])->getMock();
        $http->expects($this->once())
            ->method('get')
            ->willReturn(new Response(200, [], json_encode(['fields' => ['x' => ['stringValue' => 'y']]])));
        $client = new FirestoreClient('p', 'k', '(default)');
        $this->setHttpClient($client, $http);
        $res = $client->getDocument('c', 'id');
        $this->assertSame(['x' => 'y'], $res);
    }
}


