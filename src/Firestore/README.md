# FastFast Firestore Client

A comprehensive PHP client for Google Firestore using Guzzle HTTP with support for async operations.

## Features

1. **Add Document** - Add single documents to Firestore collections
2. **Delete Document** - Remove documents from Firestore collections (sync & async)
3. **Add Multiple Documents** - Bulk add documents using async approach with configurable concurrency
4. **Delete Multiple Documents** - Bulk delete documents using async approach with configurable concurrency
5. **Get Document** - Retrieve documents from Firestore
6. **Update Document** - Update existing documents
7. **Complex Data Support** - Handle nested arrays, objects, timestamps, and various data types

## Installation

The Firestore client is automatically registered in the Laravel service container when you use the FastFastCommonProvider.

## Configuration

Add the following to your Laravel configuration (e.g., `config/firebase.php`):

```php
return [
    'firestore' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'apikey' => env('FIREBASE_API_KEY'),
        'database' => env('FIREBASE_DATABASE', '(default)'), // Optional, defaults to (default)
    ],
];
```

Set your environment variables in `.env`:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_API_KEY=your-firebase-api-key
FIREBASE_DATABASE=(default)  # or your custom database name
```

## Usage

### Basic Usage

```php
use FastFast\Common\Firestore\FirestoreClient;

// Inject via dependency injection or resolve from container
$firestore = app(FirestoreClient::class);

// Add a document
$data = [
    'title' => 'New Order',
    'body' => 'Order notification',
    'order_id' => 123,
    'status' => 'pending'
];

$result = $firestore->addDocument('notifications', $data);
```

### Adding Documents

```php
// Add document with auto-generated ID
$result = $firestore->addDocument('notifications', $data);
$documentId = basename($result['name']);

// Add document with custom ID
$result = $firestore->addDocument('notifications', $data, 'custom-id-123');
```

### Deleting Documents

```php
// Synchronous delete
$success = $firestore->deleteDocument('notifications', 'document-id');

// Asynchronous delete
$promise = $firestore->deleteDocumentAsync('notifications', 'document-id');
$result = $promise->wait(); // Wait for completion

// Bulk async delete
$documentIds = ['doc-1', 'doc-2', 'doc-3', 'doc-4'];
$results = $firestore->deleteMultipleDocuments('notifications', $documentIds, 3);
```

### Bulk Operations (Async)

```php
$documents = [
    'doc-1' => ['title' => 'First', 'status' => 'active'],
    'doc-2' => ['title' => 'Second', 'status' => 'pending'],
    // Documents without custom IDs
    ['title' => 'Third', 'status' => 'completed'],
    ['title' => 'Fourth', 'status' => 'cancelled']
];

// Add multiple documents with concurrency limit of 5
$results = $firestore->addMultipleDocuments('notifications', $documents, 5);

foreach ($results as $result) {
    if ($result['status'] === 'success') {
        echo "✓ Document {$result['document_id']} added\n";
    } else {
        echo "✗ Failed: {$result['error']}\n";
    }
}
```

### Getting Documents

```php
$document = $firestore->getDocument('notifications', 'document-id');

if ($document) {
    // Document exists and is converted back to PHP array
    print_r($document);
} else {
    // Document not found
    echo "Document not found\n";
}
```

### Updating Documents

```php
$updateData = [
    'status' => 'completed',
    'updated_at' => new DateTime(),
    'notes' => 'Processing completed'
];

$result = $firestore->updateDocument('notifications', 'document-id', $updateData);
```

### Complex Data Structures

The client handles complex nested data automatically:

```php
$complexData = [
    'user' => [
        'id' => 12345,
        'name' => 'John Doe',
        'preferences' => [
            'notifications' => true,
            'theme' => 'dark'
        ],
        'addresses' => [
            [
                'type' => 'home',
                'coordinates' => ['lat' => 40.7128, 'lng' => -74.0060]
            ],
            [
                'type' => 'work', 
                'coordinates' => ['lat' => 40.7589, 'lng' => -73.9851]
            ]
        ]
    ],
    'metadata' => [
        'created_at' => new DateTime(),
        'version' => 1.0,
        'active' => true,
        'tags' => ['premium', 'verified']
    ]
];

$firestore->addDocument('users', $complexData);
```

## Supported Data Types

- **Strings** → `stringValue`
- **Integers** → `integerValue`
- **Floats** → `doubleValue` 
- **Booleans** → `booleanValue`
- **null** → `nullValue`
- **DateTime** → `timestampValue`
- **Arrays** → `arrayValue` (sequential) or `mapValue` (associative)
- **Objects/Maps** → `mapValue`

## Testing

Use the included test command to verify your Firestore setup:

```bash
# Run all examples
php artisan fastfast:firestore-test

# Run specific examples
php artisan fastfast:firestore-test --example=add
php artisan fastfast:firestore-test --example=bulk
php artisan fastfast:firestore-test --example=delete
php artisan fastfast:firestore-test --example=delete-async
php artisan fastfast:firestore-test --example=delete-bulk
```

## Error Handling

All methods throw `FirestoreException` on errors:

```php
use FastFast\Common\Firestore\FirestoreException;

try {
    $result = $firestore->addDocument('notifications', $data);
} catch (FirestoreException $e) {
    Log::error('Firestore operation failed: ' . $e->getMessage());
}
```

## Logging

The client automatically logs operations and errors using Laravel's logging system. Check your logs for detailed information about operations.

## Performance Considerations

- **Bulk Operations**: Use `addMultipleDocuments()` and `deleteMultipleDocuments()` for multiple operations simultaneously
- **Concurrency**: Adjust concurrency limit based on your needs (default: 5)
- **Timeouts**: HTTP client has 30s timeout with 10s connection timeout
- **Error Handling**: Failed operations in bulk are logged but don't stop other operations
- **Database Selection**: Support for custom database names beyond the default

## Examples

See `src/Firestore/Examples/FirestoreUsageExample.php` for comprehensive usage examples.
