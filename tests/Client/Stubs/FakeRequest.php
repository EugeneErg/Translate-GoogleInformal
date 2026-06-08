<?php

declare(strict_types=1);

namespace Tests\Client\Stubs;

use LogicException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class FakeRequest implements RequestInterface
{
    /**
     * @return string[][]
     */
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        return [];
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function hasHeader(string $name): bool
    {
        return false;
    }

    public function getHeaderLine(string $name): string
    {
        return '';
    }

    public function withHeader(string $name, mixed $value): static
    {
        return $this;
    }

    public function withAddedHeader(string $name, mixed $value): static
    {
        return $this;
    }

    public function withoutHeader(string $name): static
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        throw new LogicException('not implemented');
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }

    public function getRequestTarget(): string
    {
        return '/';
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        throw new LogicException('not implemented');
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }
}
