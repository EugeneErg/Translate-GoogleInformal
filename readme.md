# google-informal-icu-i18n-translator

A PHP library for translating [ICU message format](https://unicode-org.github.io/icu/userguide/format_parse/messages/) strings using the Google Translate unofficial API. Designed to integrate with the [`eugene-erg/icu-i18n-translator`](https://github.com/EugeneErg/icu-i18n-translator) ecosystem and any PSR-18 HTTP client.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
- [Usage](#usage)
    - [Basic Translation](#basic-translation)
    - [Translation with Language Detection](#translation-with-language-detection)
    - [Check Language Support](#check-language-support)
    - [Using the Low-Level Client Directly](#using-the-low-level-client-directly)
    - [Requesting Specific Data Types](#requesting-specific-data-types)
- [Architecture](#architecture)
- [Configuration](#configuration)
- [Exception Handling](#exception-handling)
- [Response Structure](#response-structure)
- [Comparison with Alternatives](#comparison-with-alternatives)
- [Development](#development)
- [License](#license)

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.2` |
| ext-intl | `*` |
| psr/http-client | `^1.0` |
| psr/http-message | `^2.0` |
| psr/http-factory | `^1.1` |
| psr/simple-cache | `^3.0` |
| eugene-erg/icu-i18n-translator | `^1.0` |

You also need a PSR-18 compatible HTTP client installed (e.g. Guzzle, Symfony HttpClient, or any other). The library does not ship one by default to remain transport-agnostic.

---

## Installation

```bash
composer require eugene-erg/google-informal-icu-i18n-translate
```

Then install a PSR-18 HTTP client. Any of the following will work:

```bash
# Guzzle
composer require guzzlehttp/guzzle guzzlehttp/psr7

# Symfony HttpClient
composer require symfony/http-client nyholm/psr7

# Buzz (lightweight)
composer require kriswallsmith/buzz nyholm/psr7
```

---

## Quick Start

```php
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Client;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\PsrClient;
use EugeneErg\GoogleInformalIcuI18nTranslator\GoogleInformalTranslator;
use EugeneErg\ICUMessageFormatParser\Parser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

// 1. Wire up your PSR-18 HTTP client (example with Guzzle)
$httpClient      = new \GuzzleHttp\Client();
$requestFactory  = new \GuzzleHttp\Psr7\HttpFactory();
$streamFactory   = new \GuzzleHttp\Psr7\HttpFactory();

// 2. Build the transport adapter
$psrClient = new PsrClient($httpClient, $requestFactory, $streamFactory);

// 3. Build the low-level Google client
$client = new Client($psrClient, 'https://translate.googleapis.com');

// 4. Build the translator (requires a PSR-16 cache)
$cache      = new Psr16Cache(new ArrayAdapter());
$translator = new GoogleInformalTranslator($client, new Parser(), $cache);

// 5. Translate
$result = $translator->translate(['Hello, world!'], 'en_EN', 'ru_RU');
// $result = ['Привет, мир!']
```

---

## Core Concepts

### ICU Message Format

[ICU messages](https://unicode-org.github.io/icu/userguide/format_parse/messages/) allow you to express pluralization, gender, and variable substitution in a locale-aware way:

```
You have {count, plural, one {# message} other {# messages}}.
```

This library translates ICU patterns while preserving the structure — variables and formatting directives are never sent through Google Translate. Only the human-readable text fragments are translated.

### Pattern = `array<string|Variable>`

Rather than a plain string, the translator works with a **pattern** — an array of strings and `Variable` objects. This keeps ICU variables intact during translation:

```php
use EugeneErg\IcuI18nTranslator\DataTransferObjects\Variable;

$pattern = ['Hello, ', new Variable(0), '! You have ', new Variable(1), ' messages.'];

$translated = $translator->translate($pattern, 'en_EN', 'ru_RU');
// ['Привет, ', Variable(0), '! У вас ', Variable(1), ' сообщений.']
```

Variable placeholders are encoded as `{{_0_}}`, `{{_1_}}` etc. when sent to Google and restored in the result.

---

## Usage

### Basic Translation

```php
$translated = $translator->translate(
    pattern: ['Good morning!'],
    fromLocale: 'en_EN',
    toLocale: 'de_DE',
);
// ['Guten Morgen!']
```

Locale format is `language_REGION` — the library extracts the region part as the language code sent to Google. Passing just `'en'` or `'ru'` (no underscore) is also supported and will be lowercased.

```php
// Both forms are valid
$translator->translate(['Hello'], 'en', 'ru');
$translator->translate(['Hello'], 'en_EN', 'ru_RU');
```

### Translation with Language Detection

When the source language is unknown, use `translateWithDetect()`. Google will detect it automatically and return it alongside the translation:

```php
use EugeneErg\IcuI18nTranslator\ValueObjects\Translated;

$result = $translator->translateWithDetect(
    pattern: ['Bonjour le monde'],
    toLocale: 'en_EN',
);

echo $result->locale;  // 'fr'
echo $result->pattern[0]; // 'Hello world'
```

### Check Language Support

Before translating you can verify that the target (and optionally source) language is supported by Google Translate. The result is cached for 24 hours via your PSR-16 cache.

```php
// Is Russian a supported target?
$translator->canTranslate('ru_RU');          // true

// Can we translate from English to Russian?
$translator->canTranslate('ru_RU', 'en_EN'); // true

// Unsupported target
$translator->canTranslate('xx_XX');          // false
```

### Using the Low-Level Client Directly

If you need more control over the Google Translate response — alternative translations, dictionary entries, romanization, etc. — use `Client` directly:

```php
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Client;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\GoogleTranslateType;

$response = $client->single(
    text: 'Hello',
    targetLanguage: 'ru',
    types: [
        GoogleTranslateType::Translation,
        GoogleTranslateType::Dictionary,
        GoogleTranslateType::AlternativeTranslations,
    ],
    sourceLanguage: 'en',
);

echo $response->translates[0]->translatedText; // 'Привет'
echo $response->detectedSourceLanguage;        // 'en'
var_dump($response->dictionary);               // raw dictionary data
```

### Requesting Specific Data Types

`GoogleTranslateType` is an enum controlling what data Google returns:

| Case | Value | Description |
|---|---|---|
| `Translation` | `t` | Main translation result |
| `AlternativeTranslations` | `at` | Alternative phrasings |
| `Romanization` | `rm` | Romanized (transliterated) form |
| `Dictionary` | `bd` | Dictionary entries with examples |
| `QualityCheck` | `qc` | Quality check information |
| `Examples` | `ex` | Usage examples |
| `Synonyms` | `ss` | Synonyms |
| `RelatedWords` | `rw` | Related words |
| `Morphology` | `md` | Morphological info |

You can request multiple types at once. Types not requested will have `null` in the corresponding response fields.

### List Supported Languages

```php
$response = $client->getSupportedLanguages();

foreach ($response->languages as $code => $language) {
    echo sprintf(
        "%s: %s (source: %s, target: %s)\n",
        $code,
        $language->name,
        $language->source ? 'yes' : 'no',
        $language->target ? 'yes' : 'no',
    );
}
```

---

## Architecture

```
GoogleInformalTranslator          — TranslatorInterface implementation
│  uses Parser (ICU parser)
│  uses CacheInterface (PSR-16)
└─ Client                         — Google Translate API wrapper
   │  uses ClientInterface
   └─ PsrClient                   — ClientInterface over PSR-18
      │  uses Psr\Http\Client\ClientInterface  (PSR-18)
      │  uses RequestFactoryInterface          (PSR-17)
      └─ uses StreamFactoryInterface           (PSR-17)
```

**`GoogleInformalTranslator`** implements `TranslatorInterface` from `eugene-erg/icu-i18n-translator`. It converts ICU pattern arrays to plain text (escaping ICU syntax via `Parser::quote()`), translates via `Client`, then parses variable placeholders back out of the response.

**`Client`** wraps the unofficial Google Translate endpoint (`translate.googleapis.com`). It maps the raw array response into typed value objects (`GoogleTranslateResponse`, `Translate`, `Confidence`, `QualityCheck`, `Model`) and maps HTTP/PSR errors into its own exception hierarchy.

**`PsrClient`** is a thin adapter that converts the library's simplified `sendRequest(method, uri, body, headers)` interface into proper PSR-7 `RequestInterface` calls, allowing any PSR-18 client to be used as transport.

---

## Configuration

### Custom API URL

By default the library targets `https://translate.googleapis.com`. You can override it — useful for testing, proxying, or regional endpoints:

```php
$client = new Client($psrClient, 'https://your-proxy.example.com');
```

### Cache TTL

Supported languages are cached for 24 hours (`P1D`). The cache key is `GoogleInformalTranslator:getSupportedLanguages`. To change the TTL, extend `GoogleInformalTranslator` and override `getSupportedLanguages()`, or wrap it with your own caching layer.

### Custom HTTP Adapter

Implement `ClientInterface` to use any transport, add authentication, logging, or retry logic:

```php
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class LoggingClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $inner,
        private LoggerInterface $logger,
    ) {}

    public function sendRequest(
        string $method,
        string $uri,
        string|null $body = null,
        array $headers = [],
    ): ResponseInterface {
        $this->logger->debug("[$method] $uri");
        return $this->inner->sendRequest($method, $uri, $body, $headers);
    }
}
```

---

## Exception Hierarchy

```
\RuntimeException
└─ Exception
   ├─ ClientException          — 4xx response or generic PSR-18 error
   │  ├─ NetworkException      — 5xx response or PSR-18 NetworkExceptionInterface
   │  └─ TimeoutException      — PSR-18 RequestExceptionInterface (timeout/abort)
   └─ ResponseJsonException    — response body is not valid JSON (on 2xx)
```

All exceptions live under `EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions`.

```php
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ClientException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\NetworkException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\TimeoutException;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Exceptions\ResponseJsonException;

try {
    $response = $client->single('Hello', 'ru');
} catch (TimeoutException $e) {
    // request timed out or was aborted
} catch (NetworkException $e) {
    // server error (5xx) or network-level failure
} catch (ClientException $e) {
    // client error (4xx) or unexpected HTTP problem
} catch (ResponseJsonException $e) {
    // got a 2xx but body wasn't JSON
}
```

---

## Response Structure

### `GoogleTranslateResponse`

| Property | Type | Description |
|---|---|---|
| `translates` | `Translate[]|null` | One entry per input sentence |
| `dictionary` | `mixed[]|null` | Dictionary data (requires `Dictionary` type) |
| `detectedSourceLanguage` | `string|null` | Detected source language code |
| `alternativeTranslations` | `mixed[]|null` | Alternatives (requires `AlternativeTranslations` type) |
| `confidenceValue` | `float|null` | Detection confidence score |
| `qualityCheck` | `QualityCheck|null` | Quality check HTML and text |
| `confidence` | `Confidence|null` | Per-language confidence data |
| `additional` | `mixed[]` | Undocumented fields from the API |

### `Translate`

| Property | Type | Description |
|---|---|---|
| `translatedText` | `string|null` | Translated text fragment |
| `originalText` | `string|null` | Original text fragment |
| `transliteration` | `string|null` | Transliterated form |
| `models` | `Model[]|null` | Internal model references used |
| `additional` | `mixed[]` | Undocumented fields |

---

## Comparison with Alternatives

| Feature | This library | `stichoza/google-translate-php` | `google/cloud-translate` |
|---|---|---|---|
| **API** | Unofficial (free) | Unofficial (free) | Official (paid) |
| **ICU message format** | ✅ Native support | ❌ | ❌ |
| **Variable preservation** | ✅ | ❌ | ❌ |
| **PSR-18 transport** | ✅ Bring your own | ❌ Uses curl directly | ✅ |
| **PSR-16 cache** | ✅ | ❌ | ❌ |
| **Language detection** | ✅ | ✅ | ✅ |
| **Typed response objects** | ✅ | ❌ plain string | ✅ |
| **Alternative translations** | ✅ | ❌ | ✅ |
| **Dictionary / examples** | ✅ | ❌ | ✅ |
| **Quality check data** | ✅ | ❌ | ❌ |
| **Auth required** | ❌ | ❌ | ✅ API key / OAuth |
| **Rate limits** | Google informal | Google informal | Quota-based billing |
| **PHP version** | `^8.2` | `^8.0` | `^8.0` |
| **Static analysis** | PHPStan level 9 | — | — |
| **Test coverage** | 100% | partial | — |

> **Note:** The unofficial Google Translate API has no SLA and may change without notice. It is suitable for development, tooling, and low-traffic use cases. For production workloads at scale, consider the official Cloud Translation API.

---

## Development

### Setup

```bash
git clone https://github.com/EugeneErg/google-informal-icu-i18n-translator
cd google-informal-icu-i18n-translator
composer install
```

### Running Tests

```bash
./vendor/bin/phpunit
```

With coverage (requires Xdebug or PCOV):

```bash
XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-filter src --coverage-text
```

### Static Analysis

```bash
./vendor/bin/phpstan analyse
```

Level 9 (maximum). Covers both `src/` and `tests/`.

### Code Style

```bash
# Check
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix
./vendor/bin/php-cs-fixer fix
```

### Project Structure

```
src/
├── GoogleInformalTranslator.php       # Main TranslatorInterface implementation
└── Client/
    ├── Client.php                     # Google Translate API wrapper
    ├── ClientInterface.php            # HTTP transport abstraction
    ├── PsrClient.php                  # PSR-18 adapter
    ├── Exceptions/
    │   ├── Exception.php
    │   ├── ClientException.php
    │   ├── NetworkException.php
    │   ├── TimeoutException.php
    │   └── ResponseJsonException.php
    └── ValueObjects/
        ├── GoogleTranslateResponse.php
        ├── GoogleTranslateType.php
        ├── Translate.php
        ├── Confidence.php
        ├── QualityCheck.php
        ├── Model.php
        ├── Language.php
        └── SupportedLanguagesResponse.php

tests/
├── GoogleInformalTranslatorTest.php
└── Client/
    ├── ClientTest.php
    ├── PsrClientTest.php
    └── Stubs/
        └── FakeRequest.php
```

---

## License

MIT — see [LICENSE](LICENSE).