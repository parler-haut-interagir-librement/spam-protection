<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Token;

use InvalidArgumentException;

/**
 * What the server writes into a form when it renders it: the form it belongs to, when it was issued, and a nonce
 * that makes it single-use. It holds nothing about the person filling the form.
 */
final readonly class SubmissionToken
{
    /**
     * @throws InvalidArgumentException when the scope is empty or holds a separator, or the nonce is not hexadecimal
     */
    public function __construct(
        public string $scope,
        public int $issuedAt,
        public string $nonce,
    ) {
        if ('' === $scope || str_contains($scope, '|')) {
            throw new InvalidArgumentException(\sprintf('A form scope is a non-empty string without "|"; "%s" given.', $scope));
        }

        if (1 !== preg_match('/^[0-9a-f]{32}$/', $nonce)) {
            throw new InvalidArgumentException('A nonce is 32 lower-case hexadecimal characters.');
        }
    }

    public function payload(): string
    {
        return $this->scope.'|'.$this->issuedAt.'|'.$this->nonce;
    }
}
