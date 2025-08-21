<?php

namespace FastFast\Common\Firestore\Examples;

use FastFast\Common\Firestore\FirestoreClient;
use FastFast\Common\Firestore\FirestoreException;

/**
 * Example usage of the FirestoreClient class
 * This file demonstrates all the features of the Firestore client
 */
class FirestoreUsageExample
{
    private FirestoreClient $firestore;

    public function __construct(FirestoreClient $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * Example: Adding a single document
     */
    public function addSingleDocument(): void
    {
        try {
            // Your original data structure
            $orderData = [
                'title' => 'New Order',
                'body' => 'New body order',
                'order_id' => 33,
                'rider_id' => 33,
                'request_id' => 444,
                'data' => [
                    'notification_name' => 'order_request',
                    'status' => 'pending',
                    'address' => [
                        'latitude' => 5.6,
                        'longitude' => 6.7,
                        'formatted_address' => 'fake-address'
                    ],
                    'customer_address' => [
                        'latitude' => 5.6,
                        'longitude' => 6.7,
                        'formatted_address' => 'fake'
                    ],
                    'amount' => 44444,
                    'sub_total' => 333,
                    'delivery_fee' => 333,
                    'order_id' => 111,
                    'orders' => [
                        ['id' => 'ffake', 'name' => 'fakename'],
                        ['id' => 'ffake2', 'name' => 'fakename2'],
                        ['id' => 'ffake3', 'name' => 'fakename3'],
                        ['id' => 'ffake4', 'name' => 'fakename4']
                    ],
                    'time' => "34 minutes",
                    'title' => "Fake seller has an order",
                    'trading_name' => 'Fake name',
                    'reference' => '#Reforder-refence',
                ]
            ];

            // Add document without specifying ID (Firestore will generate one)
            $result = $this->firestore->addDocument('notifications', $orderData);
            echo "Document added with ID: " . basename($result['name']) . "\n";

            // Add document with specific ID
            $result = $this->firestore->addDocument('notifications', $orderData, 'custom-notification-id-123');
            echo "Document added with custom ID: custom-notification-id-123\n";

        } catch (FirestoreException $e) {
            echo "Error adding document: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Deleting a document
     */
    public function deleteDocument(): void
    {
        try {
            $success = $this->firestore->deleteDocument('notifications', 'custom-notification-id-123');
            
            if ($success) {
                echo "Document deleted successfully\n";
            } else {
                echo "Failed to delete document\n";
            }

        } catch (FirestoreException $e) {
            echo "Error deleting document: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Deleting a document asynchronously
     */
    public function deleteDocumentAsync(): void
    {
        try {
            $promise = $this->firestore->deleteDocumentAsync('notifications', 'notification-1');
            
            // You can handle the promise however you want
            $promise->then(
                function ($result) {
                    if ($result['status'] === 'success') {
                        echo "Document {$result['document_id']} deleted successfully (async)\n";
                    } else {
                        echo "Failed to delete document {$result['document_id']}: {$result['error']}\n";
                    }
                },
                function ($exception) {
                    echo "Exception occurred during async delete: " . $exception->getMessage() . "\n";
                }
            );

            // Wait for the promise to resolve
            $result = $promise->wait();
            echo "Async delete completed with status: {$result['status']}\n";

        } catch (FirestoreException $e) {
            echo "Error in async delete: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Deleting multiple documents using async approach
     */
    public function deleteMultipleDocuments(): void
    {
        try {
            $documentIds = [
                'notification-1',
                'notification-2', 
                'notification-3',
                'notification-4'
            ];

            // Delete multiple documents with concurrency limit of 3
            $results = $this->firestore->deleteMultipleDocuments('notifications', $documentIds, 3);

            echo "Bulk deletion completed:\n";
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    echo "✓ Document {$result['document_id']} deleted successfully\n";
                } else {
                    echo "✗ Failed to delete document {$result['document_id']}: {$result['error']}\n";
                }
            }

        } catch (FirestoreException $e) {
            echo "Error in bulk deletion: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Adding multiple documents using async approach
     */
    public function addMultipleDocuments(): void
    {
        try {
            // Prepare multiple documents
            $documents = [
                'notification-1' => [
                    'title' => 'Order #1',
                    'body' => 'First order notification',
                    'order_id' => 1,
                    'rider_id' => 101,
                    'status' => 'pending',
                    'created_at' => new \DateTime()
                ],
                'notification-2' => [
                    'title' => 'Order #2',
                    'body' => 'Second order notification',
                    'order_id' => 2,
                    'rider_id' => 102,
                    'status' => 'confirmed',
                    'created_at' => new \DateTime()
                ],
                // Documents without custom IDs (array indexes will be ignored)
                [
                    'title' => 'Order #3',
                    'body' => 'Third order notification',
                    'order_id' => 3,
                    'rider_id' => 103,
                    'status' => 'delivered',
                    'created_at' => new \DateTime()
                ],
                [
                    'title' => 'Order #4',
                    'body' => 'Fourth order notification',
                    'order_id' => 4,
                    'rider_id' => 104,
                    'status' => 'cancelled',
                    'created_at' => new \DateTime()
                ]
            ];

            // Add multiple documents with concurrency limit of 3
            $results = $this->firestore->addMultipleDocuments('notifications', $documents, 3);

            echo "Bulk operation completed:\n";
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    echo "✓ Document {$result['document_id']} added successfully\n";
                } else {
                    echo "✗ Failed to add document {$result['document_id']}: {$result['error']}\n";
                }
            }

        } catch (FirestoreException $e) {
            echo "Error in bulk operation: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Getting a document
     */
    public function getDocument(): void
    {
        try {
            $document = $this->firestore->getDocument('notifications', 'notification-1');
            
            if ($document) {
                echo "Document retrieved:\n";
                print_r($document);
            } else {
                echo "Document not found\n";
            }

        } catch (FirestoreException $e) {
            echo "Error getting document: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Updating a document
     */
    public function updateDocument(): void
    {
        try {
            $updateData = [
                'status' => 'completed',
                'updated_at' => new \DateTime(),
                'completion_notes' => 'Order delivered successfully'
            ];

            $result = $this->firestore->updateDocument('notifications', 'notification-1', $updateData);
            echo "Document updated successfully\n";

        } catch (FirestoreException $e) {
            echo "Error updating document: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Example: Complex data structures
     */
    public function complexDataExample(): void
    {
        try {
            $complexData = [
                'user' => [
                    'id' => 12345,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'preferences' => [
                        'notifications' => true,
                        'theme' => 'dark',
                        'language' => 'en'
                    ],
                    'addresses' => [
                        [
                            'type' => 'home',
                            'street' => '123 Main St',
                            'city' => 'Anytown',
                            'coordinates' => [
                                'lat' => 40.7128,
                                'lng' => -74.0060
                            ]
                        ],
                        [
                            'type' => 'work',
                            'street' => '456 Business Ave',
                            'city' => 'Corporate City',
                            'coordinates' => [
                                'lat' => 40.7589,
                                'lng' => -73.9851
                            ]
                        ]
                    ]
                ],
                'metadata' => [
                    'created_at' => new \DateTime(),
                    'version' => 1.0,
                    'active' => true,
                    'tags' => ['premium', 'verified', 'frequent-user']
                ]
            ];

            $result = $this->firestore->addDocument('users', $complexData);
            echo "Complex document added with ID: " . basename($result['name']) . "\n";

        } catch (FirestoreException $e) {
            echo "Error adding complex document: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Run all examples
     */
    public function runAllExamples(): void
    {
        echo "=== Firestore Client Examples ===\n\n";

        echo "1. Adding single document:\n";
        $this->addSingleDocument();
        echo "\n";

        echo "2. Adding multiple documents (async):\n";
        $this->addMultipleDocuments();
        echo "\n";

        echo "3. Getting a document:\n";
        $this->getDocument();
        echo "\n";

        echo "4. Updating a document:\n";
        $this->updateDocument();
        echo "\n";

        echo "5. Complex data structure:\n";
        $this->complexDataExample();
        echo "\n";

        echo "6. Deleting a document:\n";
        $this->deleteDocument();
        echo "\n";

        echo "7. Deleting a document asynchronously:\n";
        $this->deleteDocumentAsync();
        echo "\n";

        echo "8. Deleting multiple documents (async):\n";
        $this->deleteMultipleDocuments();
        echo "\n";

        echo "=== Examples completed ===\n";
    }
}
