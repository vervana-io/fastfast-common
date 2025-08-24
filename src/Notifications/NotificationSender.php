<?php

namespace FastFast\Common\Notifications;

use App\Models\Notification as Model_Notification;
use FastFast\Common\Firestore\FirestoreClient;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;

class NotificationSender
{

    public function __construct(
        //private FirestoreClient $firestore,
        private PusherNotification $pusher,
        private FirebaseNotification $fcm,
    )
    {
    }

    public function createNotification($data)
    {
        return Model_Notification::create($data);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendNotification(User $user, $data, $metadata )
    {
        $this->createNotification($data);
        $title = $metadata['title'];
        $body =  $metadata['body'];
        $results = [];
        $results['fcm'] =$this->fcm->sendUserMessage($this->getToken($user), $data, $title, $body);
        $ios = $this->getToken($user, 'ios');
        if (!empty($ios)) {
            $apns = new APNotification($user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller'));
            $results['apns'] = $apns->sendUserMessage($this->getToken($user, 'ios'), $data, $title, $body);
        }
        $results['pusher'] = $this->pusher->sendUserMessage($user,$data, $metadata['event'], $metadata['channel']);

        return $results;
    }

    public function getToken(User $user, $type = 'android') {
        $devices = $user->devices->collect();
        if ($user->device_token && $user->device_type == $type) {
            $devices->push([
                'token' => $user->device_token,
                'type'=> $type
            ]);
        }
        return $devices->where('type', '=', $type)->pluck('token')->toArray();
    }

}