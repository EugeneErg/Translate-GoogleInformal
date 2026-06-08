<?php

declare(strict_types=1);

namespace Tests;

use EugeneErg\GoogleInformalIcuI18nTranslator\Client\Client;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\GoogleTranslateResponse;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Language;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\SupportedLanguagesResponse;
use EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects\Translate;
use EugeneErg\GoogleInformalIcuI18nTranslator\GoogleInformalTranslator;
use EugeneErg\IcuI18nTranslator\DataTransferObjects\Variable;
use EugeneErg\ICUMessageFormatParser\Parser;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

/**
 * @internal
 */
#[AllowMockObjectsWithoutExpectations]
final class GoogleInformalTranslatorTest extends TestCase
{
    /**
     * @phpstan-ignore property.uninitialized
     */
    private Client&MockObject $client;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private Parser&MockObject $parser;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private CacheInterface&MockObject $cache;

    /**
     * @phpstan-ignore property.uninitialized
     */
    private GoogleInformalTranslator $translator;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->parser = $this->createMock(Parser::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->translator = new GoogleInformalTranslator(
            $this->client,
            $this->parser,
            $this->cache,
        );
    }

    // --- translate() ---

    #[Test]
    public function translateReturnsStringParts(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->expects($this->once())
            ->method('single')
            ->willReturn($this->makeResponse('Привет'));

        $result = $this->translator->translate(['Hello'], 'en_EN', 'ru_RU');

        $this->assertSame(['Привет'], $result);
    }

    #[Test]
    public function translatePreservesVariablePlaceholders(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->method('single')
            ->willReturn($this->makeResponse('Привет {{_0_}} мир'));

        $result = $this->translator->translate(['Hello ', new Variable(0), ' world'], 'en_EN', 'ru_RU');

        $this->assertCount(3, $result);
        $this->assertSame('Привет ', $result[0]);
        $this->assertInstanceOf(Variable::class, $result[1]);
        $this->assertSame(0, $result[1]->value);
        $this->assertSame(' мир', $result[2]);
    }

    #[Test]
    public function translateReturnsEmptyArrayOnNullTranslation(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->method('single')
            ->willReturn($this->makeResponse(null));

        $result = $this->translator->translate(['Hello'], 'en_EN', 'ru_RU');

        $this->assertSame([], $result);
    }

    #[Test]
    public function translateExtractsLanguageFromLocale(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->expects($this->once())
            ->method('single')
            ->with(
                text: $this->anything(),
                targetLanguage: 'RU',
                types: $this->anything(),
                sourceLanguage: 'EN',
            )
            ->willReturn($this->makeResponse('Привет'));

        $this->translator->translate(['Hello'], 'en_EN', 'ru_RU');
    }

    #[Test]
    public function translateUsesLowercaseLocaleWhenNoUnderscore(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->expects($this->once())
            ->method('single')
            ->with(
                text: $this->anything(),
                targetLanguage: 'ru',
                types: $this->anything(),
                sourceLanguage: 'en',
            )
            ->willReturn($this->makeResponse('Привет'));

        $this->translator->translate(['Hello'], 'en', 'ru');
    }

    // --- translateWithDetect() ---

    #[Test]
    public function translateWithDetectReturnsDetectedLocale(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->method('single')
            ->willReturn($this->makeResponse('Привет', 'en'));

        $result = $this->translator->translateWithDetect(['Hello'], 'ru_RU');

        $this->assertSame('en', $result->locale);
        $this->assertSame(['Привет'], $result->pattern);
    }

    #[Test]
    public function translateWithDetectReturnsEmptyLocaleWhenNotDetected(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->method('single')
            ->willReturn($this->makeResponse('Привет', null));

        $result = $this->translator->translateWithDetect(['Hello'], 'ru_RU');

        $this->assertSame('', $result->locale);
    }

    // --- canTranslate() ---

    #[Test]
    public function canTranslateReturnsTrueWhenLanguageSupported(): void
    {
        $this->cache->method('has')->willReturn(false);
        $this->cache->method('set');

        $this->client
            ->method('getSupportedLanguages')
            ->willReturn(new SupportedLanguagesResponse(
                languages: [
                    'EN' => new Language('English', source: true, target: true),
                    'RU' => new Language('Russian', source: true, target: true),
                ],
                al: [],
            ));

        $this->assertTrue($this->translator->canTranslate('ru_RU', 'en_EN'));
    }

    #[Test]
    public function canTranslateReturnsFalseWhenTargetNotSupported(): void
    {
        $this->cache->method('has')->willReturn(false);
        $this->cache->method('set');

        $this->client
            ->method('getSupportedLanguages')
            ->willReturn(new SupportedLanguagesResponse(
                languages: [
                    'EN' => new Language('English', source: true, target: false),
                ],
                al: [],
            ));

        $this->assertFalse($this->translator->canTranslate('en_EN'));
    }

    #[Test]
    public function canTranslateReturnsTrueWithoutFromLocale(): void
    {
        $this->cache->method('has')->willReturn(false);
        $this->cache->method('set');

        $this->client
            ->method('getSupportedLanguages')
            ->willReturn(new SupportedLanguagesResponse(
                languages: [
                    'RU' => new Language('Russian', source: false, target: true),
                ],
                al: [],
            ));

        $this->assertTrue($this->translator->canTranslate('ru_RU'));
    }

    #[Test]
    public function canTranslateUsesCacheOnSecondCall(): void
    {
        $supported = new SupportedLanguagesResponse(
            languages: ['ru' => new Language('Russian', source: true, target: true)],
            al: [],
        );

        $this->cache->method('has')->willReturn(true);
        $this->cache->method('get')->willReturn($supported);

        $this->client->expects($this->never())->method('getSupportedLanguages');

        $this->translator->canTranslate('ru_RU');
    }

    // --- patternToText() integration via translate() ---

    #[Test]
    public function translateQuotesStringPartsViaParser(): void
    {
        $this->parser
            ->expects($this->once())
            ->method('quote')
            ->with('Hello')
            ->willReturn('Hello');

        $this->client
            ->expects($this->once())
            ->method('single')
            ->with(text: 'Hello')
            ->willReturn($this->makeResponse('Привет'));

        $this->translator->translate(['Hello'], 'en_EN', 'ru_RU');
    }

    #[Test]
    public function translateInlinesVariableAsPlaceholder(): void
    {
        $this->parser->method('quote')->willReturnArgument(0);

        $this->client
            ->expects($this->once())
            ->method('single')
            ->with(text: '{{_42_}}')
            ->willReturn($this->makeResponse('{{_42_}}'));

        $result = $this->translator->translate([new Variable(42)], 'en_EN', 'ru_RU');

        $this->assertInstanceOf(Variable::class, $result[0]);
        $this->assertSame(42, $result[0]->value);
    }

    // --- helpers ---

    private function makeResponse(string|null $translatedText, string|null $detectedLocale = null): GoogleTranslateResponse
    {
        $translate = new Translate(
            translatedText: $translatedText,
            originalText: null,
            transliteration: null,
            models: null,
            additional: [],
        );

        return new GoogleTranslateResponse(
            additional: [],
            translates: [$translate],
            detectedSourceLanguage: $detectedLocale,
        );
    }
}
