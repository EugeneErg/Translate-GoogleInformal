<?php

declare(strict_types=1);

namespace EugeneErg\TranslateGoogleInformal\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * @throws ClientExceptionInterface
     */
    public function sendRequest(string $method, string $uri, ?string $body = null, array $headers = []): ResponseInterface;
}
