<?php

namespace FastFast\Common\Firestore;

use Beste\Json;
use Psr\Http\Message\ResponseInterface;

class Firestore
{
    public function __construct(
        private FirestoreAPIClient $firestoreApi,
        private $collection,
    )
    {
    }

    private function createDocumentRequest(iterable $documents)
    {
        return function () use ($documents) {
          foreach ($documents as $document) {
              yield $this->firestoreApi->createDocumentRequest($document, $this->collection);
          }
        };
    }

    public function addMultiDocuments($documents)
    {
        $sendReports = array_fill(0, count($documents), null);
        $config = [
            'filled' => function (ResponseInterface $response, int $index) use($documents, &$sendReports): void {
                $document = $documents[$index];
                $json = Json::decode((string) $response->getBody(), true);
                $sendReports[$index] = [
                    'document' => $document,
                    'response' => $json
                ];
            },
            'rejected' => function (\Throwable $throwable, int $index) use($documents, &$sendReports) {
                $message = $throwable->getMessage();
                $sendReports[$index] = [
                    'error' => $message,
                    'document' => $documents[$index]
                ];
            }
        ];
        $requests = $this->createDocumentRequest($documents);
        $this->firestoreApi->pool($requests(), $config)->wait();
        return $sendReports;
    }
}