<?php

declare(strict_types=1);

namespace EugeneErg\TranslateGoogleInformal\Client\ValueObjects;

final readonly class QualityCheck
{
    public function __construct(
        public string $html,
        public string $text,
        public array $additional,
    ) {}
}
