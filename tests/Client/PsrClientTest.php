<?php

declare(strict_types=1);

namespace Tests\Client;

use EugeneErg\GoogleInformalIcuI18nTranslator\Client\PsrClient;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
#[AllowMockObjectsWithoutExpectations]
final class PsrClientTest extends TestCase
{
    /**
     * @phpstan-ignore property.uninitialized
     */
    private ClientInterface&MockObject $httpClient;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private RequestFactoryInterface&MockObject $requestFactory;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private StreamFactoryInterface&MockObject $streamFactory;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private RequestInterface&MockObject $request;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private PsrClient $psrClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        $this->psrClient = new PsrClient(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    #[Test]
    public function sendRequestCreatesAndSendsGetRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $this->requestFactory
            ->expects($this->once())
            ->method('createRequest')
            ->with('GET', 'https://example.com/api')
            ->willReturn($this->request);

        $this->request->method('withHeader')->willReturn($this->request);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($this->request)
            ->willReturn($response);

        $result = $this->psrClient->sendRequest('GET', 'https://example.com/api');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function sendRequestAddsNonNullHeaders(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($this->request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $requestWithAccept = $this->createMock(RequestInterface::class);
        $requestWithAccept->method('withHeader')->willReturn($requestWithAccept);

        $this->request
            ->expects($this->once())
            ->method('withHeader')
            ->with('Accept', 'application/json')
            ->willReturn($requestWithAccept);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($requestWithAccept);

        $this->psrClient->sendRequest('GET', 'https://example.com', null, [
            'Accept' => 'application/json',
        ]);
    }

    #[Test]
    public function sendRequestSkipsNullHeaders(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($this->request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->request
            ->expects($this->never())
            ->method('withHeader');

        $this->psrClient->sendRequest('GET', 'https://example.com', null, [
            'X-Optional' => null,
        ]);
    }

    #[Test]
    public function sendRequestAttachesBodyWhenProvided(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $requestWithBody = $this->createMock(RequestInterface::class);
        $requestWithBody->method('withHeader')->willReturn($requestWithBody);

        $this->requestFactory->method('createRequest')->willReturn($this->request);
        $this->request->method('withHeader')->willReturn($this->request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->streamFactory
            ->expects($this->once())
            ->method('createStream')
            ->with('{"key":"value"}')
            ->willReturn($stream);

        $this->request
            ->expects($this->once())
            ->method('withBody')
            ->with($stream)
            ->willReturn($requestWithBody);

        $this->httpClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with($requestWithBody);

        $this->psrClient->sendRequest('POST', 'https://example.com', '{"key":"value"}');
    }

    #[Test]
    public function sendRequestDoesNotAttachBodyWhenNull(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($this->request);
        $this->request->method('withHeader')->willReturn($this->request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->streamFactory->expects($this->never())->method('createStream');
        $this->request->expects($this->never())->method('withBody');

        $this->psrClient->sendRequest('GET', 'https://example.com', null);
    }
}
