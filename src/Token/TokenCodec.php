<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Token;

use InvalidArgumentException;
use SensitiveParameter;
use SodiumException;

/**
 * Writes a token as `payload.signature` — both in URL-safe Base64, the signature an HMAC-SHA256 of the payload
 * under the application's secret — and reads it back. A token read back is one this server issued: a changed
 * scope, date or nonce no longer matches its signature.
 */
final readonly class TokenCodec
{
    /**
     * @throws InvalidArgumentException when the secret is shorter than 16 bytes
     */
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw new InvalidArgumentException('The secret that signs the tokens is at least 16 bytes.');
        }
    }

    public function encode(SubmissionToken $token): string
    {
        $payload = $token->payload();

        return $this->base64($payload).'.'.$this->base64($this->sign($payload));
    }

    /**
     * The token the string stands for, or `null` when it is malformed or its signature does not match.
     */
    public function decode(string $encoded): ?SubmissionToken
    {
        $parts = explode('.', $encoded);

        if (2 !== \count($parts)) {
            return null;
        }

        $payload = $this->unbase64($parts[0]);
        $signature = $this->unbase64($parts[1]);

        if (null === $payload || null === $signature || !hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $fields = explode('|', $payload);

        if (3 !== \count($fields) || 1 !== preg_match('/^\d+$/', $fields[1])) {
            return null;
        }

        try {
            return new SubmissionToken($fields[0], (int) $fields[1], $fields[2]);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret, true);
    }

    private function base64(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function unbase64(string $text): ?string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            return null;
        }
    }
}
