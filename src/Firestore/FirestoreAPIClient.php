<?php

namespace FastFast\Common\Firestore;

use FastFast\Common\Http\RequestFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\PromiseInterface;
use Kreait\Firebase\Messaging\Message;
use Psr\Http\Message\RequestInterface;
use Iterator;

class FirestoreAPIClient
{

    public function __construct(
        private readonly ClientInterface $client,
        private readonly string $projectId,
        private readonly RequestFactory $requestFactory,
    ) {
    }

    public function createDocumentRequest($document, string $collection): RequestInterface
    {
        return $this->requestFactory->createPostRequest($document, $this->projectId, $collection);
    }

    /**
     * @param list<RequestInterface>|Iterator<RequestInterface> $requests
     * @param array<string, mixed> $config
     */
    public function pool(array|Iterator $requests, array $config): PromiseInterface
    {
        $pool = new Pool($this->client, $requests, $config);

        return $pool->promise();
    }
}