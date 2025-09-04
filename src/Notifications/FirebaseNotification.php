<?php

namespace FastFast\Common\Notifications;

use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Psr\Cache\CacheItemPoolInterface;
use Beste\Json;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

class FirebaseNotification
{
    private \Kreait\Firebase\Contract\Messaging $fcm;
    private Factory $factory;

    public function __construct()
    {
        $this->getFactory();
        $this->fcm = $this->getFirebaseInstance();
    }

    private function getFactory()
    {
        $factory = new Factory();
        $factory->withHttpClientOptions()
        if (app()->environment('testing')) {
            // This is the key to mocking Google Auth. We create an in-memory cache
            // and pre-populate it with a fake, non-expired access token.
            // The google/auth library will find this token in the cache and use it,
            // preventing it from making a real outbound HTTP request for a token.
            $cache = new ArrayAdapter();
            $cacheKey = 'google_auth_token_'.md5(Json::encode(storage_path('app/firebase') .'/fastfast-firebase.json'));
            $item = $cache->getItem($cacheKey);
            $item->set(Json::encode(['access_token' => 'fake-test-token', 'expires_at' => time() + 3600]));
            $cache->save($item);


            $http = HttpClientOptions::default()
                ->withGuzzleConfigOption('base_uri', env('FIREBASE_TEST_ENDPOINT', 'http://localhost:8080'))
                ->withGuzzleConfigOption('verify', false);

            // Rewrite all outgoing requests (absolute URLs) to WireMock host
            $wiremockBase = env('FIREBASE_TEST_ENDPOINT', 'http://localhost:8080');
            $rewriteToWiremock = function (callable $handler) use ($wiremockBase) {
                return function (\Psr\Http\Message\RequestInterface $request, array $options) use ($handler, $wiremockBase) {
                    $wm = new \GuzzleHttp\Psr7\Uri($wiremockBase);
                    $uri = $request->getUri()
                        ->withScheme($wm->getScheme())
                        ->withHost($wm->getHost())
                        ->withPort($wm->getPort());
                    if ($wm->getPath() !== '' && $wm->getPath() !== '/') {
                        $uri = $uri->withPath(rtrim($wm->getPath(), '/') . $request->getUri()->getPath());
                    }
                    $request = $request->withUri($uri)->withHeader('Host', $wm->getHost());
                    return $handler($request, $options);
                };
            };

            $stack = \GuzzleHttp\HandlerStack::create();
            $stack->push($rewriteToWiremock, 'wiremock_rewrite');
            $http = $http->withGuzzleConfigOption('handler', $stack);

            $factory = $factory
                ->withHttpClientOptions($http)
                ->withAuthTokenCache($cache);
        }

        $factory = $factory->withServiceAccount(storage_path('app/firebase') .'/fastfast-firebase.json');

        $this->factory = $factory;
    }

    private function getFirebaseInstance()
    {
        return $this->factory->createMessaging();
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

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendUserMessage($devices, $data, $title, $body): Messaging\MulticastSendReport
    {
        $notification = $this->generateFirebaseNotification($title, $body);
        $messages = [];
        $cm = CloudMessage::new()->withData($data)->withNotification($notification);
        foreach ($devices as $device) {
            $tokens = $device['tokens'];
            $id = $device['id'];
            foreach ($tokens as $token) {
                $messages[] = $cm->toToken($token);
            }
        }

        return $this->fcm->sendAll($messages);
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

    /**
     * @throws MessagingException
     * @throws FirebaseException
     */
    public function sendRidersNotifications($order, $riders, $devices, $requests, $data, $metadata): Messaging\MulticastSendReport
    {
        $title = $metadata['title'];
        $body = $metadata['body'];
        $notification = $this->generateFirebaseNotification($title, $body);
        $messages = [];

        foreach ($riders as $rider) {
            $message = [
                'user_id' => $rider->user_id,
                'order_id' => $order->id,
                'rider_id' => $rider->id,
                'request_id' => $requests->where('rider_id', $rider->id)->first()->id,
                'title' => $title,
                'body' => $body,
                'data' => json_encode($data),
            ];
            $cm = CloudMessage::new()->withData($message)->withNotification($notification);
            $tokens = $devices[$rider->user_id];
            foreach ($tokens as $token) {
                $messages[] = $cm->toToken($token);
            }
        }

        return $this->fcm->sendAll($messages);
    }
}


use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging\CloudMessage;
use PHPUnit\Framework\TestCase;
use WireMock\Client\WireMock;

final class FirebaseMessagingTest extends TestCase
{
    private WireMock $wireMock;
    private $messaging;

    protected function setUp(): void
    {
        // Set up the WireMock client and reset stubs
        $this->wireMock = WireMock::create('localhost', 8080);
        $this->wireMock->reset();

        // Step 2: Configure kreait/firebase-php using HttpClientOptions
        $options = HttpClientOptions::default()
            ->withGuzzleConfigOption('base_uri', 'http://localhost:8080/')
            ->withGuzzleConfigOption('http_errors', false);

        $factory = (new Factory())
            ->withServiceAccount(__DIR__.'/test-credentials.json')
            ->withHttpClientOptions($options);

        $this->messaging = $factory->createMessaging();
    }

    /** @test */
    public function it_sends_a_message_successfully(): void
    {
        // Define the WireMock stub
        $this->wireMock->stubFor(WireMock::post(WireMock::urlMatching('/v1/projects/.+/messages:send'))
            ->willReturn(WireMock::okJson('{
                "name": "projects/test-project/messages/12345"
            }')));

        $message = CloudMessage::withTarget('token', 'some_valid_device_token');

        // The method should not throw an exception on success.
        $this->messaging->send($message);
        $this->expectNotToPerformAssertions();
    }

    /** @test */
    public function it_handles_a_messaging_api_error(): void
    {
        $this->expectException(MessagingException::class);

        // Define the WireMock stub for a specific API error response
        $this->wireMock->stubFor(WireMock::post(WireMock::urlMatching('/v1/projects/.+/messages:send'))
            ->willReturn(WireMock::aResponse()
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json')
                ->withBody('{
                    "error": {
                        "status": "NOT_FOUND",
                        "message": "The topic provided does not exist.",
                        "details": []
                    }
                }')));

        $message = CloudMessage::withTarget('topic', 'non-existent-topic');

        $this->messaging->send($message);
    }
}
