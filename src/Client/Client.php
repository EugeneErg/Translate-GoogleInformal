<?php

declare(strict_types=1);

namespace EugeneErg\GoogleInformalIcuI18nTranslator\Client;

use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ClientException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\NetworkException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ResponseJsonException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\TimeoutException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Confidence;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\GoogleTranslateResponse;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\GoogleTranslateType;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Language;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Model;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\QualityCheck;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\SupportedLanguagesResponse;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Translate;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Throwable;

use function is_array;
use function is_float;
use function is_string;

use const JSON_THROW_ON_ERROR;

readonly class Client
{
    public function __construct(
        private PsrClient $psrClient,
        private string $apiUrl,
    ) {
    }

    /**
     * @param mixed[] $data
     * @param array<int|string> $remove
     *
     * @return mixed[]
     */
    private static function makeAdditional(array $data, array $remove): array
    {
        foreach ($remove as $item) {
            unset($data[$item]);
        }

        return $data;
    }

    /**
     * @param GoogleTranslateType[] $types
     */
    public function single(
        string $text,
        string $targetLanguage,
        array $types = [],
        string|null $sourceLanguage = null,
    ): GoogleTranslateResponse {
        $uri = $this->makeUri('translate_a/single', [
            'client' => 'gtx',
            'sl' => $sourceLanguage ?? 'auto',
            'tl' => $targetLanguage,
            'dt' => array_column($types, 'value'),
            'q' => $text,
        ]);
        $result = $this->sendRequest($uri);

        return new GoogleTranslateResponse(
            additional: self::makeAdditional($result, [0, 2, 5, 6, 7, 8]),
            translates: isset($result[0]) && is_array($result[0])
                ? array_map(static function (mixed $translate): Translate {
                    /** @var mixed[] $translate */
                    $translate = is_array($translate) ? $translate : [];
                    $modelsRaw = isset($translate[8]) && is_array($translate[8]) ? $translate[8] : null;

                    /** @var Model[]|null $models */
                    $models = $modelsRaw !== null
                        ? array_merge(...array_map(static function (mixed $models): array {
                            /** @var mixed[] $models */
                            $models = is_array($models) ? $models : [];

                            return array_map(static function (mixed $model): Model {
                                /** @var mixed[] $model */
                                $model = is_array($model) ? $model : [];

                                return new Model(
                                    hash: isset($model[0]) && is_string($model[0]) ? $model[0] : '',
                                    fileName: isset($model[1]) && is_string($model[1]) ? $model[1] : '',
                                    additional: self::makeAdditional($model, [0, 1]),
                                );
                            }, $models);
                        }, $modelsRaw))
                        : null;

                    return new Translate(
                        translatedText: isset($translate[0]) && is_string($translate[0]) ? $translate[0] : null,
                        originalText: isset($translate[1]) && is_string($translate[1]) ? $translate[1] : null,
                        transliteration: isset($translate[3]) && is_string($translate[3]) ? $translate[3] : null,
                        models: $models,
                        additional: self::makeAdditional($translate, [0, 1, 3, 8]),
                    );
                }, $result[0])
                : null,
            dictionary: isset($result[1]) && is_array($result[1]) ? $result[1] : null,
            detectedSourceLanguage: isset($result[2]) && is_string($result[2]) ? $result[2] : null,
            alternativeTranslations: isset($result[5]) && is_array($result[5]) ? $result[5] : null,
            confidenceValue: isset($result[6]) && is_float($result[6]) ? $result[6] : null,
            qualityCheck: isset($result[7]) && is_array($result[7]) && $result[7] !== []
                ? new QualityCheck(
                    html: is_string($result[7][0]) ? $result[7][0] : '',
                    text: is_string($result[7][1]) ? $result[7][1] : '',
                    additional: self::makeAdditional($result[7], [0, 1]),
                )
                : null,
            confidence: isset($result[8]) && is_array($result[8])
                ? new Confidence(
                    languages: array_values(array_filter(
                        is_array($result[8][0]) ? $result[8][0] : [],
                        'is_string',
                    )),
                    values: array_values(array_filter(
                        is_array($result[8][2]) ? $result[8][2] : [],
                        'is_float',
                    )),
                    languages2: array_values(array_filter(
                        is_array($result[8][3]) ? $result[8][3] : [],
                        'is_string',
                    )),
                    additional: self::makeAdditional($result[8], [0, 2, 3]),
                )
                : null,
        );
    }

    public function getSupportedLanguages(): SupportedLanguagesResponse
    {
        $result = $this->sendRequest($this->makeUri('translate_a/l', ['client' => 'gtx']));
        $languages = [];

        $sl = isset($result['sl']) && is_array($result['sl']) ? $result['sl'] : [];
        $tl = isset($result['tl']) && is_array($result['tl']) ? $result['tl'] : [];

        foreach ($sl as $language => $name) {
            if (is_string($language) && is_string($name)) {
                $languages[$language] = new Language(
                    name: $name,
                    source: true,
                    target: isset($tl[$language]),
                );
            }
        }

        foreach ($tl as $language => $name) {
            if (is_string($language) && is_string($name)) {
                $languages[$language] ??= new Language(name: $name, source: false, target: true);
            }
        }

        unset($languages['auto']);

        return new SupportedLanguagesResponse(
            languages: $languages,
            al: isset($result['al']) && is_array($result['al']) ? $result['al'] : [],
        );
    }

    /**
     * @return mixed[]
     */
    private function sendRequest(string $uri): array
    {
        try {
            $response = $this->psrClient->sendRequest(method: 'GET', uri: $uri, headers: ['Accept' => 'application/json']);
        } catch (NetworkExceptionInterface $exception) {
            throw new NetworkException($exception->getMessage(), previous: $exception);
        } catch (RequestExceptionInterface $exception) {
            throw new TimeoutException($exception->getMessage(), previous: $exception);
        } catch (ClientExceptionInterface $exception) {
            throw new ClientException($exception->getMessage(), previous: $exception);
        }

        $content = $response->getBody()->getContents();
        $statusCode = $response->getStatusCode();

        try {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            if ($statusCode >= 400) {
                $this->handleErrorResponse($statusCode, $response->getReasonPhrase(), $exception);
            }

            throw new ResponseJsonException('Failed to decode response body', previous: $exception);
        }

        if ($statusCode >= 400) {
            throw $this->handleErrorResponse($statusCode, $content);
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function handleErrorResponse(int $statusCode, string $content, Throwable|null $previous = null): ClientException
    {
        return $statusCode >= 500
            ? new NetworkException($content, previous: $previous)
            : new ClientException($content, previous: $previous);
    }

    /**
     * @param array<string, string|string[]> $parameters
     */
    private function makeUri(string $path, array $parameters = []): string
    {
        return $this->apiUrl . '/' . $path . ($parameters === [] ? '' : '?' . $this->httpBuildQuery($parameters));
    }

    /**
     * @param array<string, string|string[]> $parameters
     */
    private function httpBuildQuery(array $parameters, string $separator = '&'): string
    {
        $result = [];

        foreach ($parameters as $key => $value) {
            $key = urlencode($key);

            if (is_string($value)) {
                $result[] = $key . '=' . urlencode($value);
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $result[] = $key . '=' . urlencode($item);
                }
            }
        }

        return implode($separator, $result);
    }
}
