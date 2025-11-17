<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;

/**
 * Builder for creating mock HTTP clients with predefined responses
 */
class MockHttpClient
{
    private array $responses = [];

    /**
     * Queue a successful JSON response
     */
    public function addJsonResponse(array $data, int $statusCode = 200): self
    {
        $this->responses[] = new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR)
        );
        return $this;
    }

    /**
     * Queue an HTTP error response
     */
    public function addErrorResponse(int $statusCode = 500, string $body = ''): self
    {
        $this->responses[] = new Response($statusCode, [], $body);
        return $this;
    }

    /**
     * Build the mock HTTP client
     */
    public function build(): ClientInterface
    {
        $mock = new MockHandler($this->responses);
        $handlerStack = HandlerStack::create($mock);
        return new Client(['handler' => $handlerStack]);
    }
}
