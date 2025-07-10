<?php

namespace FastFast\Common\Notifications;

use App\Models\User;
use Pushok\AuthProvider;
use Pushok\Client;
use Pushok\InvalidPayloadException;
use Pushok\Notification;
use Pushok\Payload;
use Pushok\Payload\Alert;
use function App\Notifications\config;


class CustomAPNNotification
{
  private $options;

  public function __construct($user_type = "seller")
  {
    $app_bundle_id = match ($user_type) {
      'seller' => config('broadcasting.connections.apn.seller_bundle_id'),
      'rider' => config('broadcasting.connections.apn.rider_bundle_id'),
      'customer' => config('broadcasting.connections.apn.customer_bundle_id'),
      default => config('broadcasting.connections.apn.seller_bundle_id'),
    };

    $this->options = [
      'key_id' => config('broadcasting.connections.apn.key_id'),
      'team_id' => config('broadcasting.connections.apn.team_id'),
      'app_bundle_id' => $app_bundle_id,
      'private_key_path' => config('broadcasting.connections.apn.private_key_path'),
      'production' => match ($user_type) {
        'seller' => true,
        default => config('broadcasting.connections.apn.production')
      },
    ];
  }

    /**
     * @throws InvalidPayloadException
     * @throws \Exception
     */
    public function sendNotification(User $user, $data = [])
  {
    $authProvider = AuthProvider\Token::create($this->options);
    $client = new Client($authProvider, $this->options['production']);

    $alert = Alert::create()
      ->setTitle($data['title'])
      ->setBody($data['body']);

    $payload = Payload::create()
      ->setAlert($alert);
    $payload->setSound('default');

    $payload->setCustomValue('type', $data['title']);
    $payload->setCustomValue('data', $data['body']);
    $notification = new Notification($payload, $user->device_token);

    $client->addNotification($notification);
    return $client->push();
  }


  public function sendMultiDeviceNotification($deviceTokens, $data)
  {
      $authProvider = AuthProvider\Token::create($this->options);
      $client = new Client($authProvider, $this->options['production']);

      $alert = Alert::create()
          ->setTitle($data['title'])
          ->setBody($data['body']);

      $payload = Payload::create()
          ->setAlert($alert);
      $payload->setSound('default');

      $payload->setCustomValue('type', $data['title']);
      $payload->setCustomValue('data', $data);
      foreach ($deviceTokens as $token) {
          $notification = new Notification($payload, $token);
          $client->addNotification($notification);
          $client->push();
      }
  }
}
