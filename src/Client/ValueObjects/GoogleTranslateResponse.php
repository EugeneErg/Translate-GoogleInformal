<?php

declare(strict_types=1);

namespace EugeneErg\GoogleInformalIcuI18nTranslator\Client\ValueObjects;

final readonly class GoogleTranslateResponse
{
    /**
     * @param Translate[] $translates
     */
    public function __construct(
        public array $additional,
        public array|null $translates = null,
        public array|null $dictionary = null,
        public string|null $detectedSourceLanguage = null,
        public array|null $alternativeTranslations = null,
        public float|null $confidenceValue = null,
        public QualityCheck|null $qualityCheck = null,
        public Confidence|null $confidence = null,
    ) {
    }
}
