<?php

declare(strict_types=1);

namespace Tests\Client;

use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Client;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ClientInterface;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ClientException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\NetworkException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ResponseJsonException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\TimeoutException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\GoogleTranslateType;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\QualityCheck;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface as PsrClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Tests\Client\Stubs\FakeRequest;

/**
 * @internal
 */
#[AllowMockObjectsWithoutExpectations]
final class ClientTest extends TestCase
{
    /**
     * @phpstan-ignore property.uninitialized
     */
    private ClientInterface&MockObject $psrClient;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private Client $client;

    protected function setUp(): void
    {
        $this->psrClient = $this->createMock(ClientInterface::class);
        $this->client = new Client($this->psrClient, 'https://translate.googleapis.com');
    }

    private static function encode(mixed $data): string
    {
        return (string) json_encode($data);
    }

    // --- single() ---

    #[Test]
    public function singleReturnsTranslatedText(): void
    {
        $body = self::encode([
            [['Привет', 'Hello', null, null]],
            null,
            'en',
        ]);
        $this->expectGetRequest('{translate_a/single}', $this->mockResponse($body));

        $result = $this->client->single('Hello', 'ru', [GoogleTranslateType::Translation], 'en');

        $this->assertNotNull($result->translates);
        $this->assertCount(1, $result->translates);
        $this->assertSame('Привет', $result->translates[0]->translatedText);
        $this->assertSame('Hello', $result->translates[0]->originalText);
        $this->assertSame('en', $result->detectedSourceLanguage);
    }

    #[Test]
    public function singleWithNoTranslatesReturnsNull(): void
    {
        $body = self::encode([null, null, 'en']);
        $this->expectGetRequest('{translate_a/single}', $this->mockResponse($body));

        $result = $this->client->single('Hello', 'ru');

        $this->assertNull($result->translates);
    }

    #[Test]
    public function singleParsesQualityCheck(): void
    {
        $body = self::encode([
            [['Привет', 'Hello']],
            null,
            'en',
            null,
            null,
            null,
            null,
            ['<b>Hello</b>', 'Hello'],
        ]);
        $this->expectGetRequest('{translate_a/single}', $this->mockResponse($body));

        $result = $this->client->single('Hello', 'ru');

        $this->assertInstanceOf(QualityCheck::class, $result->qualityCheck);
        $this->assertSame('<b>Hello</b>', $result->qualityCheck->html);
        $this->assertSame('Hello', $result->qualityCheck->text);
    }

    #[Test]
    public function singleParsesConfidence(): void
    {
        $body = self::encode([
            [['Привет', 'Hello']],
            null,
            'en',
            null,
            null,
            null,
            null,
            null,
            [['en'], null, [0.98], ['en']],
        ]);
        $this->expectGetRequest('{translate_a/single}', $this->mockResponse($body));

        $result = $this->client->single('Hello', 'ru');

        $this->assertNotNull($result->confidence);
        $this->assertSame(['en'], $result->confidence->languages);
        $this->assertSame([0.98], $result->confidence->values);
    }

    #[Test]
    public function singleBuildsCorrectUri(): void
    {
        $body = self::encode([[['Привет', 'Hello']]]);

        $this->psrClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with(
                'GET',
                $this->callback(function (string $uri): bool {
                    $this->assertStringContainsString('sl=en', $uri);
                    $this->assertStringContainsString('tl=ru', $uri);
                    $this->assertStringContainsString('q=Hello', $uri);
                    $this->assertStringContainsString('dt=t', $uri);

                    return true;
                }),
            )
            ->willReturn($this->mockResponse($body));

        $this->client->single('Hello', 'ru', [GoogleTranslateType::Translation], 'en');
    }

    #[Test]
    public function singleUsesAutoWhenNoSourceLanguage(): void
    {
        $body = self::encode([[['Привет', 'Hello']]]);

        $this->psrClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with('GET', $this->stringContains('sl=auto'))
            ->willReturn($this->mockResponse($body));

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function singleParsesModels(): void
    {
        $body = self::encode([
            [[
                'Привет',
                'Hello',
                null,
                null,
                null,
                null,
                null,
                null,
                [[['abc123', 'model.bin']]],
            ]],
        ]);
        $this->expectGetRequest('{translate_a/single}', $this->mockResponse($body));

        $result = $this->client->single('Hello', 'ru');

        $this->assertNotNull($result->translates);
        $models = $result->translates[0]->models;
        $this->assertNotNull($models);
        $this->assertCount(1, $models);
        $this->assertSame('abc123', $models[0]->hash);
        $this->assertSame('model.bin', $models[0]->fileName);
    }

    // --- getSupportedLanguages() ---

    #[Test]
    public function getSupportedLanguagesReturnsSourceAndTargetLanguages(): void
    {
        $body = self::encode([
            'sl' => ['en' => 'English', 'fr' => 'French'],
            'tl' => ['en' => 'English', 'ru' => 'Russian'],
        ]);
        $this->expectGetRequest('{translate_a/l}', $this->mockResponse($body));

        $result = $this->client->getSupportedLanguages();

        $this->assertArrayHasKey('en', $result->languages);
        $this->assertTrue($result->languages['en']->source);
        $this->assertTrue($result->languages['en']->target);

        $this->assertArrayHasKey('fr', $result->languages);
        $this->assertTrue($result->languages['fr']->source);
        $this->assertFalse($result->languages['fr']->target);

        $this->assertArrayHasKey('ru', $result->languages);
        $this->assertFalse($result->languages['ru']->source);
        $this->assertTrue($result->languages['ru']->target);
    }

    #[Test]
    public function getSupportedLanguagesRemovesAutoEntry(): void
    {
        $body = self::encode([
            'sl' => ['auto' => 'Detect language', 'en' => 'English'],
            'tl' => ['en' => 'English'],
        ]);
        $this->expectGetRequest('{translate_a/l}', $this->mockResponse($body));

        $result = $this->client->getSupportedLanguages();

        $this->assertArrayNotHasKey('auto', $result->languages);
    }

    // --- error handling ---

    #[Test]
    public function throwsNetworkExceptionOn5xx(): void
    {
        $this->expectGetRequest('{.}', $this->mockResponse('Server Error', 500, 'Internal Server Error'));
        $this->expectException(NetworkException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function throwsClientExceptionOn4xx(): void
    {
        $this->expectGetRequest('{.}', $this->mockResponse('Bad Request', 400, 'Bad Request'));
        $this->expectException(ClientException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function throwsResponseJsonExceptionOnInvalidJson(): void
    {
        $this->expectGetRequest('{.}', $this->mockResponse('not json'));
        $this->expectException(ResponseJsonException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function throwsNetworkExceptionOnValidJson5xx(): void
    {
        $body = self::encode(['error' => 'Internal Server Error']);
        $this->expectGetRequest('{.}', $this->mockResponse($body, 500, 'Internal Server Error'));
        $this->expectException(NetworkException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function throwsClientExceptionOnValidJson4xx(): void
    {
        $body = self::encode(['error' => 'Bad Request']);
        $this->expectGetRequest('{.}', $this->mockResponse($body, 400, 'Bad Request'));
        $this->expectException(ClientException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function wrapsGenericClientExceptionFromPsrClient(): void
    {
        $exception = new class('generic error') extends RuntimeException implements PsrClientExceptionInterface {};

        $this->psrClient->method('sendRequest')->willThrowException($exception);
        $this->expectException(ClientException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function wrapsNetworkExceptionFromPsrClient(): void
    {
        $fakeRequest = new FakeRequest();
        $networkException = new class('network error', $fakeRequest) extends RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private readonly FakeRequest $fakeRequest)
            {
                parent::__construct($message);
            }

            public function getRequest(): FakeRequest
            {
                return $this->fakeRequest;
            }
        };

        $this->psrClient->method('sendRequest')->willThrowException($networkException);
        $this->expectException(NetworkException::class);

        $this->client->single('Hello', 'ru');
    }

    #[Test]
    public function wrapsRequestExceptionAsTimeoutException(): void
    {
        $fakeRequest = new FakeRequest();
        $requestException = new class('timeout', $fakeRequest) extends RuntimeException implements RequestExceptionInterface {
            public function __construct(string $message, private readonly FakeRequest $fakeRequest)
            {
                parent::__construct($message);
            }

            public function getRequest(): FakeRequest
            {
                return $this->fakeRequest;
            }
        };

        $this->psrClient->method('sendRequest')->willThrowException($requestException);
        $this->expectException(TimeoutException::class);

        $this->client->single('Hello', 'ru');
    }

    // --- helpers ---

    private function mockResponse(string $body, int $statusCode = 200, string $reasonPhrase = 'OK'): ResponseInterface
    {
        $stream = $this->createStub(StreamInterface::class);
        $stream->method('getContents')->willReturn($body);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn($stream);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);

        return $response;
    }

    private function expectGetRequest(string $uriPattern, ResponseInterface $response): void
    {
        $this->psrClient
            ->expects($this->once())
            ->method('sendRequest')
            ->with('GET', $this->matchesRegularExpression($uriPattern))
            ->willReturn($response);
    }
}
