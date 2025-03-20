<?php

namespace BenTools\MeilisearchOdm\Tests\Fixtures;

use Meilisearch\Client;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_shift;

final class MeiliMock
{
    private static self $instance;
    private(set) MockHttpClient $http;
    private(set) Client $client;
    private array $responses = [];

    private function __construct()
    {
        $this->http = new MockHttpClient(fn () => array_shift($this->responses));
        $this->client = new Client('http://localhost', httpClient: new Psr18Client($this->http));
    }

    public function willRespond(ResponseInterface $response, ResponseInterface ...$responses): void
    {
        foreach ([$response, ...$responses] as $response) {
            $this->responses[] = $response;
        }
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }
}
