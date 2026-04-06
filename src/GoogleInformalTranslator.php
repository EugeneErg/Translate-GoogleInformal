<?php

declare(strict_types=1);

namespace EugeneErg\TranslateGoogleInformal;

use EugeneErg\ICUMessageFormatParser\Parser;
use EugeneErg\Translate\DataTransferObjects\Variable;
use EugeneErg\Translate\Translators\Contracts\TranslatorInterface;
use EugeneErg\Translate\ValueObjects\Translated;
use EugeneErg\TranslateGoogleInformal\Client\Client;
use EugeneErg\TranslateGoogleInformal\Client\ValueObjects\GoogleTranslateType;

readonly class GoogleInformalTranslator implements TranslatorInterface
{
    public function __construct(private Client $client, private Parser $parser) {}

    /**
     * @param array<string|Variable> $pattern
     *
     * @return array<string|Variable>
     */
    public function translate(
        array $pattern,
        string $fromLocale,
        string $toLocale,
        ?string $context = null,
    ): array {
        $result = $this->client->single(
            text: $this->patternToText($pattern),
            targetLanguage: $this->localeToLanguage($toLocale),
            types: [GoogleTranslateType::Translation],
            sourceLanguage: $this->localeToLanguage($fromLocale),
        );

        return $this->parseString($result->translates[0]->translatedText);
    }

    public function translateWithDetect(
        array $pattern,
        string $toLocale,
        ?string $context = null,
    ): Translated {
        $result = $this->client->single(
            text: $this->patternToText($pattern),
            targetLanguage: $this->localeToLanguage($toLocale),
            types: [GoogleTranslateType::Translation],
        );

        return new Translated(
            locale: $result->detectedSourceLanguage,
            pattern: $this->parseString($result->translates[0]->translatedText),
        );
    }

    public function canTranslate(string $toLocale, ?string $fromLocale = null): bool
    {
        // todo cache $this->client->getSupportedLanguages()
        $fromLanguage = null === $fromLocale ? null : $this->localeToLanguage($fromLocale);
        $toLanguage = $this->localeToLanguage($toLocale);
        $fromCheck = null === $fromLanguage;
        $toCheck = false;

        foreach ($this->client->getSupportedLanguages()->languages as $language => $options) {
            $fromCheck = $fromCheck || ($language === $fromLanguage && $options->source);
            $toCheck = $toCheck || ($language === $toLanguage && $options->target);

            if ($fromCheck && $toCheck) {
                return true;
            }
        }

        return false;
    }

    private function parseString(string $text): array
    {
        $result = [];
        $parts = preg_split('{(\{\{_\d+_\}\})}', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($parts as $part) {
            if ('' !== $part) {
                $result[] = preg_match('{^\{\{_(\d+)_\}\}$}', $part, $matches)
                    ? new Variable((int) $matches[1])
                    : \MessageFormatter::formatMessage('EN', $part, []);
            }
        }

        return $result;
    }

    /**
     * @param array<string|Variable> $pattern
     */
    private function patternToText(array $pattern): string
    {
        $result = '';

        foreach ($pattern as $value) {
            $result .= $value instanceof Variable ? "{{_{$value->value}_}}" : $this->parser->quote($value);
        }

        return $result;
    }

    private function localeToLanguage(string $locale): string
    {
        $countyLanguage = explode('_', $locale, 2);

        return $countyLanguage[1] ?? strtolower($locale);
    }
}
