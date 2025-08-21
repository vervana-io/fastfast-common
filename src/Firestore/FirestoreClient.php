<?php

namespace FastFast\Common\Firestore;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class FirestoreClient
{
    private Client $httpClient;
    private string $projectId;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $projectId, string $apiKey, $db)
    {
        $this->projectId = $projectId;
        $this->apiKey = $apiKey;
        //https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:runQuery
        //https://firestore.googleapis.com/v1/projects/fastfast-ab3b0/databases/(default)/documents:runQuery
        $this->baseUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/$db/documents";
        
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Add a single document to Firestore
     *
     * @param string $collection
     * @param array $data
     * @param string|null $documentId
     * @return array
     * @throws GuzzleException
     */
    public function addDocument(string $collection, array $data, ?string $documentId = null): array
    {
        try {
            $url = "{$this->baseUrl}/{$collection}";
            
            if ($documentId) {
                $url .= "/{$documentId}";
                $method = 'PATCH';
            } else {
                $method = 'POST';
            }
            
            $url .= "?key={$this->apiKey}";

            $document = [
                'fields' => $this->convertToFirestoreFields($data)
            ];

            $response = $this->httpClient->request($method, $url, [
                'json' => $document
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Document added to Firestore', [
                'collection' => $collection,
                'document_id' => $documentId ?? basename($result['name']),
                'status' => $response->getStatusCode()
            ]);

            return $result;

        } catch (RequestException $e) {
            Log::error('Failed to add document to Firestore', [
                'collection' => $collection,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            
            throw new FirestoreException('Failed to add document: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete a document from Firestore
     *
     * @param string $collection
     * @param string $documentId
     * @return bool
     * @throws GuzzleException
     */
    public function deleteDocument(string $collection, string $documentId): bool
    {
        try {
            $url = "{$this->baseUrl}/{$collection}/{$documentId}?key={$this->apiKey}";

            $response = $this->httpClient->delete($url);

            Log::info('Document deleted from Firestore', [
                'collection' => $collection,
                'document_id' => $documentId,
                'status' => $response->getStatusCode()
            ]);

            return $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            Log::error('Failed to delete document from Firestore', [
                'collection' => $collection,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            
            throw new FirestoreException('Failed to delete document: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete a document from Firestore asynchronously
     *
     * @param string $collection
     * @param string $documentId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function deleteDocumentAsync(string $collection, string $documentId): \GuzzleHttp\Promise\PromiseInterface
    {
        $url = "{$this->baseUrl}/{$collection}/{$documentId}?key={$this->apiKey}";

        return $this->httpClient->deleteAsync($url)->then(
            function ($response) use ($collection, $documentId) {
                Log::info('Document deleted from Firestore (async)', [
                    'collection' => $collection,
                    'document_id' => $documentId,
                    'status' => $response->getStatusCode()
                ]);
                
                return [
                    'status' => 'success',
                    'document_id' => $documentId,
                    'response_code' => $response->getStatusCode()
                ];
            },
            function ($exception) use ($collection, $documentId) {
                $errorMessage = $exception->getMessage();
                $errorResponse = null;
                
                if ($exception instanceof RequestException && $exception->hasResponse()) {
                    $errorResponse = $exception->getResponse()->getBody()->getContents();
                }

                Log::error('Failed to delete document from Firestore (async)', [
                    'collection' => $collection,
                    'document_id' => $documentId,
                    'error' => $errorMessage,
                    'response' => $errorResponse
                ]);
                
                return [
                    'status' => 'failed',
                    'document_id' => $documentId,
                    'error' => $errorMessage,
                    'response' => $errorResponse
                ];
            }
        );
    }

    /**
     * Delete multiple documents from Firestore using async approach
     *
     * @param string $collection
     * @param array $documentIds Array of document IDs to delete
     * @param int $concurrency Maximum number of concurrent delete requests
     * @return array Results array with success/failure information
     */
    public function deleteMultipleDocuments(string $collection, array $documentIds, int $concurrency = 5): array
    {
        $promises = [];

        Log::info('Starting bulk document deletion', [
            'collection' => $collection,
            'count' => count($documentIds),
            'concurrency' => $concurrency
        ]);

        foreach ($documentIds as $documentId) {
            $promises[$documentId] = $this->deleteDocumentAsync($collection, $documentId);
        }

        // Process promises with concurrency limit
        $responses = Promise\Utils::settle($promises)->wait();

        $results = [];
        foreach ($responses as $documentId => $response) {
            if ($response['state'] === 'fulfilled') {
                $results[] = $response['value'];
            } else {
                $error = $response['reason'];
                $results[] = [
                    'status' => 'failed',
                    'document_id' => $documentId,
                    'error' => $error->getMessage()
                ];

                Log::error('Failed to delete document in bulk operation', [
                    'collection' => $collection,
                    'document_id' => $documentId,
                    'error' => $error->getMessage()
                ]);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $failureCount = count($results) - $successCount;

        Log::info('Bulk document deletion completed', [
            'collection' => $collection,
            'total' => count($results),
            'success' => $successCount,
            'failed' => $failureCount
        ]);

        return $results;
    }

    /**
     * Add multiple documents to Firestore using async approach
     *
     * @param string $collection
     * @param array $documents Array of documents where key is document ID (optional) and value is data
     * @param int $concurrency Maximum number of concurrent requests
     * @return array Results array with success/failure information
     */
    public function addMultipleDocuments(string $collection, array $documents, int $concurrency = 5): array
    {
        $promises = [];
        $results = [];

        Log::info('Starting bulk document addition', [
            'collection' => $collection,
            'count' => count($documents),
            'concurrency' => $concurrency
        ]);

        foreach ($documents as $documentId => $data) {
            $url = "{$this->baseUrl}/{$collection}";
            $method = 'POST';
            
            // If documentId is provided and is not numeric (array index)
            if (!is_numeric($documentId)) {
                $url .= "/{$documentId}";
                $method = 'PATCH';
            } else {
                $documentId = null; // Let Firestore generate ID
            }
            
            $url .= "?key={$this->apiKey}";

            $document = [
                'fields' => $this->formatForFirestore($data)
            ];

            $promises[$documentId ?? uniqid()] = $this->httpClient->requestAsync($method, $url, [
                'json' => $document
            ]);
        }

        // Process promises with concurrency limit
        $responses = Promise\Utils::settle($promises)->wait();

        foreach ($responses as $key => $response) {
            if ($response['state'] === 'fulfilled') {
                $result = json_decode($response['value']->getBody()->getContents(), true);
                $results[] = [
                    'status' => 'success',
                    'document_id' => $key !== uniqid() ? $key : basename($result['name']),
                    'data' => $result
                ];
            } else {
                $error = $response['reason'];
                $errorMessage = $error->getMessage();
                $errorResponse = null;
                
                if ($error instanceof RequestException && $error->hasResponse()) {
                    $errorResponse = $error->getResponse()->getBody()->getContents();
                }

                $results[] = [
                    'status' => 'failed',
                    'document_id' => $key,
                    'error' => $errorMessage,
                    'response' => $errorResponse
                ];

                Log::error('Failed to add document in bulk operation', [
                    'collection' => $collection,
                    'document_id' => $key,
                    'error' => $errorMessage,
                    'response' => $errorResponse
                ]);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $failureCount = count($results) - $successCount;

        Log::info('Bulk document addition completed', [
            'collection' => $collection,
            'total' => count($results),
            'success' => $successCount,
            'failed' => $failureCount
        ]);

        return $results;
    }

    /**
     * Get a document from Firestore
     *
     * @param string $collection
     * @param string $documentId
     * @return array|null
     * @throws GuzzleException
     */
    public function getDocument(string $collection, string $documentId): ?array
    {
        try {
            $url = "{$this->baseUrl}/{$collection}/{$documentId}?key={$this->apiKey}";

            $response = $this->httpClient->get($url);
            $result = json_decode($response->getBody()->getContents(), true);

            return $this->convertFromFirestoreFields($result['fields'] ?? []);

        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return null; // Document not found
            }
            
            Log::error('Failed to get document from Firestore', [
                'collection' => $collection,
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            
            throw new FirestoreException('Failed to get document: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Update a document in Firestore
     *
     * @param string $collection
     * @param string $documentId
     * @param array $data
     * @return array
     * @throws GuzzleException
     */
    public function updateDocument(string $collection, string $documentId, array $data): array
    {
        return $this->addDocument($collection, $data, $documentId);
    }

    /**
     * Convert PHP array to Firestore fields format
     *
     * @param array $data
     * @return array
     */
    private function convertToFirestoreFields(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $fields[$key] = $this->convertValue($value);
        }

        return $fields;
    }

    /**
     * Convert a single value to Firestore format
     *
     * @param mixed $value
     * @return array
     */
    private function convertValue($value): array
    {
        if (is_array($value)) {
            if ($this->isSequentialArray($value)) {
                // Handle array of values
                $arrayValue = [];
                foreach ($value as $item) {
                    $arrayValue[] = $this->convertValue($item);
                }
                return ['arrayValue' => ['values' => $arrayValue]];
            } else {
                // Handle associative array/object
                return ['mapValue' => ['fields' => $this->convertToFirestoreFields($value)]];
            }
        } elseif (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string) $value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        } elseif ($value instanceof \DateTime) {
            return ['timestampValue' => $value->format('c')];
        }

        return ['stringValue' => (string) $value];
    }

    /**
     * Recursively parses a Firestore-formatted document back into a simple PHP array.
     * @param array $fields The 'fields' array from a Firestore document.
     * @return array The clean PHP array.
     */
    function parseFirestoreDocument(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $value) {
            // The first key of the value array determines its type
            $type = key($value);
            switch ($type) {
                case 'stringValue':
                case 'doubleValue':
                case 'booleanValue':
                    $result[$key] = $value[$type];
                    break;
                case 'integerValue':
                    // Convert the integer-as-a-string back to a proper int
                    $result[$key] = (int)$value[$type];
                    break;
                case 'mapValue':
                    // Recursively parse nested objects
                    $result[$key] = $this->parseFirestoreDocument($value[$type]['fields']);
                    break;
                case 'arrayValue':
                    $result[$key] = [];
                    // Check if 'values' key exists before processing
                    if (isset($value[$type]['values'])) {
                        foreach ($value[$type]['values'] as $item) {
                            // Each item in the array is its own typed value, likely a map
                            $itemType = key($item);
                            if ($itemType === 'mapValue') {
                                $result[$key][] = $this->parseFirestoreDocument($item[$itemType]['fields']);
                            } else {
                                // Handle other potential types inside an array if needed
                                $result[$key][] = $item[$itemType];
                            }
                        }
                    }
                    break;
                case 'nullValue':
                    $result[$key] = null;
                    break;
                // Add other types like timestampValue, geoPointValue if you use them
                default:
                    $result[$key] = $value[$type];
                    break;
            }
        }
        return $result;
    }

    /**
     * Convert Firestore fields back to PHP array
     *
     * @param array $fields
     * @return array
     */
    private function convertFromFirestoreFields(array $fields): array
    {
        $data = [];

        foreach ($fields as $key => $field) {
            $data[$key] = $this->convertFromFirestoreValue($field);
        }

        return $data;
    }

    /**
     * Convert a single Firestore value back to PHP
     *
     * @param array $field
     * @return mixed
     */
    private function convertFromFirestoreValue(array $field)
    {
        if (isset($field['stringValue'])) {
            return $field['stringValue'];
        } elseif (isset($field['integerValue'])) {
            return (int) $field['integerValue'];
        } elseif (isset($field['doubleValue'])) {
            return (float) $field['doubleValue'];
        } elseif (isset($field['booleanValue'])) {
            return $field['booleanValue'];
        } elseif (isset($field['nullValue'])) {
            return null;
        } elseif (isset($field['timestampValue'])) {
            return new \DateTime($field['timestampValue']);
        } elseif (isset($field['arrayValue'])) {
            $array = [];
            foreach ($field['arrayValue']['values'] ?? [] as $value) {
                $array[] = $this->convertFromFirestoreValue($value);
            }
            return $array;
        } elseif (isset($field['mapValue'])) {
            return $this->convertFromFirestoreFields($field['mapValue']['fields'] ?? []);
        }

        return null;
    }


    /**
     * Recursively formats a PHP array into a Firestore-compatible format.
     * @param array $data The input PHP array.
     * @return array The formatted array.
     */
    function formatForFirestore(array $data): array
    {
        $fields = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $fields[$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                // Firestore REST API expects integer values as strings
                $fields[$key] = ['integerValue' => (string)$value];
            } elseif (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $fields[$key] = ['booleanValue' => $value];
            } elseif (is_null($value)) {
                $fields[$key] = ['nullValue' => null];
            } elseif (is_array($value)) {
                // Check if the array is a list (sequential keys) or a map (associative keys)
                if (!empty($value) && array_keys($value) === range(0, count($value) - 1)) {
                    // It's a list (array)
                    $arrayValues = [];
                    foreach ($value as $item) {
                        // Each item in the array could be a map (object) or another primitive
                        if (is_array($item)) {
                            $arrayValues[] = ['mapValue' => ['fields' => $this->formatForFirestore($item)]];
                        } else {
                            // Handle primitives inside an array if necessary (less common for complex objects)
                        }
                    }
                    $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
                } else {
                    // It's a map (object)
                    $fields[$key] = ['mapValue' => ['fields' => $this->formatForFirestore($value)]];
                }
            }
        }
        return $fields;
    }

    /**
     * Check if array is sequential (not associative)
     *
     * @param array $array
     * @return bool
     */
    private function isSequentialArray(array $array): bool
    {
        if (empty($array)) return true;
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Get the HTTP client instance
     *
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Get project ID
     *
     * @return string
     */
    public function getProjectId(): string
    {
        return $this->projectId;
    }

    /**
     * Search for documents in a collection based on field filters.
     * Note: This implementation uses the runQuery method and supports 'EQUAL' operators.
     * For more complex queries (e.g., >, <, array-contains), the query structure needs to be expanded.
     *
     * @param string $collection The collection ID to search in.
     * @param array $filters An associative array of field filters, e.g., ['field_name' => 'value'].
     * @param int $limit The maximum number of documents to return.
     * @param int $offset The number of documents to skip.
     * @param string|null $orderBy The field to order the results by.
     * @param string $direction The order direction, 'asc' or 'desc'.
     * @return array An array of matching documents.
     * @throws FirestoreException
     * @throws GuzzleException
     */
    public function searchDocuments(string $collection, array $filters = [], int $limit = 100, int $offset = 0, ?string $orderBy = null, string $direction = 'asc'): array
    {
        $queryUrl = rtrim($this->baseUrl, '/') . ':runQuery';

        $structuredQuery = [
            'from' => [['collectionId' => $collection]],
            'limit' => $limit,
            'offset' => $offset,
        ];

        if (!empty($filters)) {
            $fieldFilters = [];
            foreach ($filters as $field => $value) {
                $fieldFilters[] = [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $field],
                        'op' => 'EQUAL',
                        'value' => $this->convertValue($value)
                    ]
                ];
            }

            if (count($fieldFilters) === 1) {
                $structuredQuery['where'] = $fieldFilters[0];
            } else {
                $structuredQuery['where'] = [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => $fieldFilters
                    ]
                ];
            }
        }

        if ($orderBy) {
            $structuredQuery['orderBy'] = [
                [
                    'field' => ['fieldPath' => $orderBy],
                    'direction' => strtoupper($direction) === 'DESC' ? 'DESCENDING' : 'ASCENDING'
                ]
            ];
        }

        try {
            $response = $this->httpClient->post($queryUrl, [
                'json' => ['structuredQuery' => $structuredQuery]
            ]);

            $results = json_decode($response->getBody()->getContents(), true);
            $documents = [];

            foreach ($results as $result) {
                if (isset($result['document'])) {
                    $documents[] = [
                        'id' => basename($result['document']['name']),
                        'data' => $this->parseFirestoreDocument($result['document']['fields'] ?? []),
                        'createTime' => $result['document']['createTime'] ?? null,
                        'updateTime' => $result['document']['updateTime'] ?? null
                    ];
                }
            }

            Log::info('Documents searched in Firestore', [
                'collection' => $collection,
                'filters' => $filters,
                'limit' => $limit,
                'offset' => $offset,
                'results_count' => count($documents)
            ]);

            return $documents;

        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('Failed to search documents in Firestore', [
                'collection' => $collection,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'response' => $errorBody
            ]);
            
            throw new FirestoreException('Failed to search documents: ' . $e->getMessage() . ' - ' . $errorBody, $e->getCode(), $e);
        }
    }

    /**
     * Search for documents asynchronously.
     *
     * @param string $collection
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @param string|null $orderBy
     * @param string $direction
     * @return Promise\PromiseInterface
     */
    public function searchDocumentsAsync(string $collection, array $filters = [], int $limit = 100, int $offset = 0, ?string $orderBy = null, string $direction = 'asc'): Promise\PromiseInterface
    {
        return Promise\Utils::task(function () use ($collection, $filters, $limit, $offset, $orderBy, $direction) {
            return $this->searchDocuments($collection, $filters, $limit, $offset, $orderBy, $direction);
        });
    }

    /**
     * Search for documents with cursor-based pagination.
     * Note: An 'orderBy' clause is required for pagination.
     *
     * @param string $collection
     * @param array $filters
     * @param int $limit
     * @param array|null $startAfter An array of values from the previous page's last document, corresponding to the orderBy field(s).
     * @param string $orderBy The field to order by. This is required for pagination.
     * @param string $direction
     * @return array An array containing the documents and the cursor for the next page.
     * @throws FirestoreException
     * @throws GuzzleException
     */
    public function searchDocumentsPaginated(string $collection, array $filters = [], int $limit = 100, ?array $startAfter = null, string $orderBy = 'id', string $direction = 'asc'): array
    {
        if (empty($orderBy)) {
            throw new \InvalidArgumentException('The "orderBy" parameter is required for paginated searches.');
        }

        $queryUrl = rtrim($this->baseUrl, '/') . ':runQuery';

        $structuredQuery = [
            'from' => [['collectionId' => $collection]],
            'limit' => $limit,
            'orderBy' => [
                [
                    'field' => ['fieldPath' => $orderBy === 'id' ? '__name__' : $orderBy],
                    'direction' => strtoupper($direction) === 'DESC' ? 'DESCENDING' : 'ASCENDING'
                ]
            ]
        ];

        if (!empty($filters)) {
            $fieldFilters = [];
            foreach ($filters as $field => $value) {
                $fieldFilters[] = [
                    'fieldFilter' => [
                        'field' => ['fieldPath' => $field],
                        'op' => 'EQUAL',
                        'value' => $this->convertValue($value)
                    ]
                ];
            }

            if (count($fieldFilters) === 1) {
                $structuredQuery['where'] = $fieldFilters[0];
            } else {
                $structuredQuery['where'] = [
                    'compositeFilter' => [
                        'op' => 'AND',
                        'filters' => $fieldFilters
                    ]
                ];
            }
        }
        
        if ($startAfter) {
            $structuredQuery['startAt'] = [
                'values' => array_map([$this, 'convertValue'], $startAfter),
                'before' => false // This means "start after"
            ];
        }

        try {
            $response = $this->httpClient->post($queryUrl, [
                'json' => ['structuredQuery' => $structuredQuery]
            ]);

            $results = json_decode($response->getBody()->getContents(), true);
            $documents = [];
            $nextCursor = null;

            foreach ($results as $result) {
                if (isset($result['document'])) {
                    $documents[] = [
                        'id' => basename($result['document']['name']),
                        'data' => $this->parseFirestoreDocument($result['document']['fields'] ?? []),
                        'createTime' => $result['document']['createTime'] ?? null,
                        'updateTime' => $result['document']['updateTime'] ?? null
                    ];
                }
            }

            if (!empty($documents)) {
                $lastDoc = end($documents);
                $orderByField = $orderBy === 'id' ? 'id' : "data.{$orderBy}";
                $cursorValue = $this->getValueFromNestedArray($lastDoc, $orderByField);
                if ($cursorValue !== null) {
                    $nextCursor = [$cursorValue];
                }
            }
            
            return [
                'documents' => $documents,
                'nextCursor' => $nextCursor
            ];

        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            throw new FirestoreException('Failed to search documents (paginated): ' . $e->getMessage() . ' - ' . $errorBody, $e->getCode(), $e);
        }
    }
    
    /**
     * Helper to get a value from a nested array using dot notation.
     * @param array $array
     * @param string $key
     * @return mixed|null
     */
    private function getValueFromNestedArray(array $array, string $key)
    {
        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return null;
            }
            $array = $array[$segment];
        }
        return $array;
    }

    /**
     * Delete all documents based on conditions (query + delete approach)
     *
     * @param string $collection
     * @param array $filters Array of field filters ['field_name' => 'value']
     * @param int $batchSize Maximum documents to delete in one batch
     * @param bool $dryRun If true, only count documents that would be deleted
     * @return array Results with count and details of deleted documents
     * @throws GuzzleException
     */
    public function deleteAllDocuments(string $collection, array $filters = [], int $batchSize = 100, bool $dryRun = false): array
    {
        try {
            Log::info('Starting bulk document deletion', [
                'collection' => $collection,
                'filters' => $filters,
                'batch_size' => $batchSize,
                'dry_run' => $dryRun
            ]);

            $deletedCount = 0;
            $deletedDocuments = [];
            $errors = [];
            $startAfter = null;

            do {
                // Search for documents matching the conditions to get a batch to delete
                $searchResults = $this->searchDocumentsPaginated(
                    $collection, 
                    $filters, 
                    $batchSize, 
                    $startAfter,
                    'id', // Order by document ID for stable pagination
                    'asc'
                );

                $documents = $searchResults['documents'];
                $startAfter = $searchResults['nextCursor'];

                if (empty($documents)) {
                    break;
                }

                if ($dryRun) {
                    // Just count documents that would be deleted
                    $deletedCount += count($documents);
                    foreach ($documents as $doc) {
                        $deletedDocuments[] = [
                            'id' => $doc['id'],
                            'would_delete' => true
                        ];
                    }
                } else {
                    // Actually delete the documents
                    $documentIds = array_column($documents, 'id');
                    
                    // Delete in batch using async approach
                    $deleteResults = $this->deleteMultipleDocuments($collection, $documentIds, 10);
                    
                    foreach ($deleteResults as $result) {
                        if ($result['status'] === 'success') {
                            $deletedCount++;
                            $deletedDocuments[] = [
                                'id' => $result['document_id'],
                                'deleted' => true
                            ];
                        } else {
                            $errors[] = [
                                'document_id' => $result['document_id'],
                                'error' => $result['error']
                            ];
                        }
                    }
                }

            } while ($startAfter);

            $result = [
                'total_deleted' => $deletedCount,
                'deleted_documents' => $deletedDocuments,
                'errors' => $errors,
                'dry_run' => $dryRun,
                'collection' => $collection,
                'filters_applied' => $filters
            ];

            Log::info('Bulk document deletion completed', [
                'collection' => $collection,
                'filters' => $filters,
                'total_deleted' => $deletedCount,
                'errors_count' => count($errors),
                'dry_run' => $dryRun
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to delete all documents', [
                'collection' => $collection,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            throw new FirestoreException('Failed to delete all documents: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete all documents by condition asynchronously
     *
     * @param string $collection
     * @param array $filters Array of field filters ['field_name' => 'value']
     * @param int $batchSize Maximum documents to delete in one batch
     * @param bool $dryRun If true, only count documents that would be deleted
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function deleteAllDocumentsAsync(string $collection, array $filters = [], int $batchSize = 100, bool $dryRun = false): \GuzzleHttp\Promise\PromiseInterface
    {
        return Promise\Utils::task(function () use ($collection, $filters, $batchSize, $dryRun) {
            return $this->deleteAllDocuments($collection, $filters, $batchSize, $dryRun);
        });
    }

    /**
     * Count documents matching conditions without deleting them
     *
     * @param string $collection
     * @param array $filters Array of field filters ['field_name' => 'value']
     * @return int Number of documents matching the conditions
     * @throws GuzzleException
     */
    public function countDocumentsByCondition(string $collection, array $filters = []): int
    {
        try {
            $count = 0;
            $nextPageToken = null;
            $batchSize = 1000; // Use larger batch size for counting

            do {
                $searchResults = $this->searchDocumentsPaginated(
                    $collection, 
                    $filters, 
                    $batchSize, 
                    $nextPageToken
                );

                $count += count($searchResults['documents']);
                $nextPageToken = $searchResults['nextPageToken'];

            } while ($nextPageToken);

            Log::info('Document count by condition completed', [
                'collection' => $collection,
                'filters' => $filters,
                'count' => $count
            ]);

            return $count;

        } catch (Exception $e) {
            Log::error('Failed to count documents by condition', [
                'collection' => $collection,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            throw new FirestoreException('Failed to count documents by condition: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
