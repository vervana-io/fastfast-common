<?php

namespace FastFast\Common\Notifications;

use Illuminate\Support\Collection;
use MrShan0\PHPFirestore\FirestoreClient;

class Firebase
{
    private FirestoreClient $client;
    public function __construct()
    {
        $this->client = new FirestoreClient(config('firebase.firestore.project_id') ,config('firebase.firestore.apikey'));
    }

    public function addDocument(Collection $collection, $data)
    {
        $collection->each(function ($rider) {
            $this->client->addDocument('rider_orders', [

            ]);
        });
    }
}