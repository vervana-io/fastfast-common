<?php

namespace FastFast\Common\Firestore;

use FastFast\Common\Http\HttpClientOptions;
use FastFast\Common\Http\RequestFactory;
use Google\Auth\FetchAuthTokenCache;
use Google\Auth\FetchAuthTokenInterface;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use Kreait\Firebase\Exception\RuntimeException;

class Factory
{
    private HttpFactory $httpFactory;

    private HttpClientOptions $httpClientOptions;
    public function __construct()
    {
        $this->httpFactory = new HttpFactory();
        $this->httpClientOptions = HttpClientOptions::default();
    }

    /**
     * @param array<non-empty-string, mixed>|null $config
     * @param array<callable(callable): callable>|null $middlewares
     */
    public function createApiClient(?array $config = null, ?array $middlewares = null): Client
    {
        $config ??= [];
        $middlewares ??= [];

        $config = [...$this->httpClientOptions->guzzleConfig(), ...$config];

        $handler = HandlerStack::create($config['handler'] ?? null);

        if ($this->httpLogMiddleware !== null) {
            $handler->push($this->httpLogMiddleware, 'http_logs');
        }

        if ($this->httpDebugLogMiddleware !== null) {
            $handler->push($this->httpDebugLogMiddleware, 'http_debug_logs');
        }

        foreach ($this->httpClientOptions->guzzleMiddlewares() as $middleware) {
            $handler->push($middleware['middleware'], $middleware['name']);
        }

        foreach ($middlewares as $middleware) {
            $handler->push($middleware);
        }

        $credentials = $this->getGoogleAuthTokenCredentials();

        if (!$credentials instanceof FetchAuthTokenInterface) {
            throw new RuntimeException('Unable to create an API client without credentials');
        }

        $projectId = $this->getProjectId();
        $cachePrefix = 'kreait_firebase_'.$projectId;

        $credentials = new FetchAuthTokenCache($credentials, ['prefix' => $cachePrefix], $this->authTokenCache ?? $this->defaultCache);
        $authTokenHandler = HttpHandlerFactory::build(new Client($config));

        $handler->push(new AuthTokenMiddleware($credentials, $authTokenHandler));

        $config['handler'] = $handler;
        $config['auth'] = 'google_auth';

        return new Client($config);
    }

    public function createFirestoreInstance()
    {
        $reqFactory = new RequestFactory(
            $this->httpFactory,
            $this->httpFactory,
        );
        $documentClient = new FirestoreAPIClient(
            $this->createApiClient(),
            '',
            $reqFactory,
        )
    }
}