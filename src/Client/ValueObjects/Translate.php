<?php

declare(strict_types=1);

namespace EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects;

final readonly class Translate
{
    /**
     * @param Model[] $models
     * @param mixed[] $additional
     */
    public function __construct(
        public string|null $translatedText,
        public string|null $originalText,
        public string|null $transliteration,
        public array|null $models,
        public array $additional,
    ) {
    }
}
