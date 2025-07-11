<?php

namespace FastFast\Common\Notifications;

use App\Models\Admin;
use App\Models\Notification as Model_Notification;
use App\Models\Order;
use App\Models\Personnel;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Pusher\Pusher;
use Pusher\PushNotifications\PushNotifications;
use React\Promise\Deferred;
use function React\Promise\all;

class Notification {

    public function sendUserAPNS($data, User $user, $type = 'customer')
    {
        $client = new CustomAPNNotification($type);
        return $client->sendNotification($user, $data);
    }

    private function getFirebaseInstance()
    {
        return (new Factory)->withServiceAccount(storage_path('app/firebase') .'/fastfast-firebase.json')->createMessaging();
    }

    private function getBeamInstance()
    {
        return new PushNotifications([
            "instanceId" => config('services.pusher-beams.instance_id'),
            "secretKey" => config('services.pusher-beams.secret_key'),
        ]);
    }

    private function getPusherInstance()
    {
        return new Pusher(env('PUSHER_APP_KEY'), env('PUSHER_APP_SECRET'), env('PUSHER_APP_ID'), [
            'cluster' => env('PUSHER_APP_CLUSTER'),
        ]);
    }

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    private function validateFirebaseToken(Messaging $messaging, $device_tokens)
    {
        $result = $messaging->validateRegistrationTokens($device_tokens);
        $resp = [];
        if(isset($result['valid']))
        {
            $resp = $result['valid'];
        }
        return $resp;
    }

    private function generateFirebaseNotification($title, $body)
    {
        return \Kreait\Firebase\Messaging\Notification::create($title, $body);
    }

    public function sendToMultiDevices($device_tokens, $title, $body, $data)
    {
        $messaging = $this->getFirebaseInstance();
        $device_tokens = $this->validateFirebaseToken($messaging, $device_tokens);
        $resp = null;
        if(count($device_tokens) > 0)
        {
            $notification = $this->generateFirebaseNotification($title, $body);
            $message = CloudMessage::new();
            $message = $message->withNotification($notification);
            if(count($data) > 0)
            {
                $message = $message->withData($data);
            }
            $resp = $messaging->sendMulticast($message, $device_tokens)->successes();
        }
        return $resp;
    }

    private function generateBeamToken($id, $is_admin=false)
    {
        if($is_admin == true)
        {
            $id = "admin_id_" . $id;
        }
        else
        {
            $id = "user_id_" . $id;
        }
        $beam_instance = $this->getBeamInstance();
        $beam_token = $beam_instance->generateToken($id);
        return [
            'token' => $beam_token['token'],
            'user_id' => $id,
        ];
    }

    private function manageAdminBeamToken(Admin $admin)
    {
        if(empty($admin->beam_user_id))
        {
            $attempt = $this->generateBeamToken($admin->id, true);
            $token = "";
            if(is_array($attempt))
            {
                if(isset($attempt['token']))
                {
                    Admin::find($admin->id)->update(['beam_token' => $attempt['token'], 'beam_user_id' => $attempt['user_id'], 'beam_expire_at' => now()->addHours(24)]);
                    $token = $attempt['user_id'];
                }
            }
            return $token;
        }
        else
        {
            $exp = new Carbon($admin->beam_expire_at);
            $token = "";
            if($exp->isPast())
            {
                $attempt = $this->generateBeamToken($admin->id, true);
                if(is_array($attempt))
                {
                    if(isset($attempt['token']))
                    {
                        Admin::find($admin->id)->update(['beam_token' => $attempt['token'], 'beam_user_id' => $attempt['user_id'], 'beam_expire_at' => now()->addHours(24)]);
                        $token = $attempt['user_id'];
                    }
                }
            }
            else
            {
                $token = $admin->beam_user_id;
            }
            return $token;
        }
    }
    public function manageUserBeamToken(User $user)
    {
        if(empty($user->beam_user_id))
        {
            $attempt = $this->generateBeamToken($user->id, false);
            $token = "";
            if(is_array($attempt))
            {
                if(isset($attempt['token']))
                {
                    User::find($user->id)->update(['beam_token' => $attempt['token'], 'beam_user_id' => $attempt['user_id'], 'beam_expire_at' => now()->addHours(24)]);
                    $token = $attempt['user_id'];
                }
            }
        }
        else
        {
            $exp = new Carbon($user->beam_expire_at);
            $token = "";
            if($exp->isPast())
            {
                $attempt = $this->generateBeamToken($user->id, false);
                if(is_array($attempt))
                {
                    if(isset($attempt['token']))
                    {
                        User::find($user->id)->update(['beam_token' => $attempt['token'], 'beam_user_id' => $attempt['user_id'], 'beam_expire_at' => now()->addHours(24)]);
                        $token = $attempt['user_id'];
                    }
                }
            }
            else
            {
                $token = $user->beam_user_id;
            }
        }
        return $token;
    }

    public function getAdminsBeamTokens($role=0)
    {
        $admins = Admin::select('id', 'beam_token', 'beam_expire_at');
        if($role > 0)
        {
            $admins = $admins->where('role', '=', $role);
        }
        $admins = $admins->get();
        $ret_arr = [];
        if($admins->count() > 0)
        {
            foreach($admins as $admin)
            {
                $ret_arr[] = $this->manageAdminBeamToken($admin);
            }
        }
        foreach($ret_arr as $key => $value)
        {
            if(empty($value))
            {
                unset($ret_arr[$key]);
            }
        }
        return $ret_arr;
    }

    public function sendBeamMessage($tokens, $title, $body, $data = [])
    {
        $beam_instance = $this->getBeamInstance();
        return $beam_instance->publishToUsers($tokens, [
            "apns" => [
                "aps" => [
                    "alert" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                ],
                'custom' => $data,
            ],
            "fcm" => [
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                'data' => $data,
            ],
            "web" => [
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                'data' => $data,
            ],
        ]);
    }

    public function sendPusherMessage($event, $data=[])
    {
        $pusher_instance = $this->getPusherInstance();
        return $pusher_instance->trigger("FastFast", $event, $data);
    }

    public function sendMessage(User $user, $title, $body, $data, $status = 'created')
    {
        if ($user->devices) {
            $devices = new Collection($user->devices);
            $android = $devices->where('type', '=', 'android')->pluck('device_token');
            if ($android->count() > 0) {
                $device_tokens = $android->toArray();
                $this->sendToMultiDevices($device_tokens, $title, $body, $data);
            }
            $ios = $devices->where('type', '=', 'ios')->pluck('device_token');
            if ($ios->count() > 0) {
                $device_tokens = $ios->toArray();
                $type = $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller');
                //return $this->sendUserAPNS($data, $user, $type);
                $client = new CustomAPNNotification($type);
                $client->sendMultiDeviceNotification($device_tokens, $data);
            }
        }
        if ($user && $user->device_type == 'ios') {
            //return $user->notify(new OrderStatusNotification($status, $data));
            $type = $user->user_type == 1 ? 'customer' : ($user->user_type == 3 ? 'rider' : 'seller');
            return $this->sendUserAPNS($data, $user, $type);
        }
        $messaging = $this->getFirebaseInstance();
        $device_tokens = $this->validateFirebaseToken($messaging, $user->device_token);
        $resp = null;
        if(count($device_tokens) > 0)
        {
            $device_token = $device_tokens[0];
            $notification = $this->generateFirebaseNotification($title, $body);
            $message = CloudMessage::withTarget('token', $user->device_token)->withNotification($notification);
            if(count($data) > 0)
            {
                $message = $message->withData($data);
            }
            $resp = $messaging->send($message);
        }
        return [
            'status' => true,
            'message' => 'Success',
            'data' => $resp,
        ];
    }

    public function createNotification($data)
    {
        return Model_Notification::create($data);
    }


    protected function sendNotification(User $user, $data, $title, $body, $event, $status)
    {
        $this->createNotification($data);
        $seller_beam_device_token = $this->notification->manageUserBeamToken($user);
        $this->sendMessage($user, $title,$body, $data, $status);
        if(!empty($seller_device_token))
        {
            $this->sendBeamMessage($seller_beam_device_token, $title, $body);
            //$this->send_firebase_message_to_single_device($seller_device_token, $title, $body, $not_data, , 'rejected');
        }
        if(!empty($seller_beam_device_token))
        {
            //$this->send_beam_message([$seller_beam_device_token], $title, $body, $not_data);

            $this->sendBeamMessage($seller_beam_device_token, $title, $body);
        }
        $this->sendPusherMessage($event, $data);
        return true;
    }

    protected function sendFranchiseNotification(Order $order, $data, $title, $body, $event)
    {
        $personnel_user_ids = Personnel::select('user_id')->where('franchise_id', '=', $order->franchise_id)->pluck('user_id')->toArray();
        //$personnel_device_tokens = User::select('device_token')->whereIn('id', $personnel_user_ids)->whereNotNull('device_token')->pluck('device_token')->toArray();
        //$personnel_users = User::select('id', 'device_token', 'beam_token')->whereIn('id', $personnel_user_ids)->get();
        $personnelUsers = User::query()->whereIn('id', $personnel_user_ids)->get();
        //$device_tokens = [];
        if($personnelUsers->count() > 0) {
            $promise = $personnelUsers->map(function (User $user) use($data, $title, $body, $order, $event){
                $p = new Deferred();
                $not_data['user_id'] = $user->id;
                $this->createNotification($data);
                $device_tokens = $this->manageUserBeamToken($user);
                $this->sendBeamMessage($device_tokens,$title, $body, $not_data);
                $not_data['franchise_id'] = $order->franchise_id;
                $this->sendMessage($user, $title,$body, $not_data);
                $this->sendPusherMessage($event, $not_data);
                return $p->promise();
            });
            return all($promise);
        }
    }
}