<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Tests\Unit;

use InvalidArgumentException;
use Phil\SpamProtection\Token\SubmissionToken;
use Phil\SpamProtection\Token\TokenCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmissionToken::class)]
#[CoversClass(TokenCodec::class)]
final class TokenTest extends TestCase
{
    private const string NONCE = '0123456789abcdef0123456789abcdef';

    private const string SECRET = 'a secret of sixteen bytes or more';

    public function testATokenIsWrittenAndReadBack(): void
    {
        $codec = new TokenCodec(self::SECRET);
        $token = new SubmissionToken('contact', 1_700_000_000, self::NONCE);
        $encoded = $codec->encode($token);

        self::assertSame('contact|1700000000|'.self::NONCE, $token->payload());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]{43}$/', $encoded);
        self::assertEquals($token, $codec->decode($encoded));
    }

    public function testAnotherSecretDoesNotReadIt(): void
    {
        $encoded = new TokenCodec(self::SECRET)->encode(new SubmissionToken('contact', 1, self::NONCE));

        self::assertNull(new TokenCodec('another secret, sixteen bytes')->decode($encoded));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tampered(): iterable
    {
        $codec = new TokenCodec(self::SECRET);
        $valid = $codec->encode(new SubmissionToken('contact', 1, self::NONCE));
        [$payload, $signature] = explode('.', $valid);
        $other = $codec->encode(new SubmissionToken('signup', 1, self::NONCE));

        yield 'empty' => [''];
        yield 'no signature' => [$payload];
        yield 'three parts' => [$valid.'.x'];
        yield 'another payload' => [explode('.', $other)[0].'.'.$signature];
        yield 'not base64' => ['@@@.'.$signature];
        yield 'signature not base64' => [$payload.'.@@@'];
        yield 'signed but not a token' => [self::sign('only-two|parts')];
        yield 'signed with a date that is not a number' => [self::sign('contact|soon|'.self::NONCE)];
        yield 'signed with a bad nonce' => [self::sign('contact|1|nonce')];
        yield 'signed with a negative date' => [self::sign('contact|-1|'.self::NONCE)];
        yield 'signed with a date with a suffix' => [self::sign('contact|12x|'.self::NONCE)];
    }

    #[DataProvider('tampered')]
    public function testATamperedTokenIsNotRead(string $encoded): void
    {
        self::assertNull(new TokenCodec(self::SECRET)->decode($encoded));
    }

    public function testTheSecretIsAtLeastSixteenBytes(): void
    {
        new TokenCodec(str_repeat('s', 16));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The secret that signs the tokens is at least 16 bytes.');

        new TokenCodec(str_repeat('s', 15));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'empty scope' => ['', self::NONCE, 'A form scope is a non-empty string without "|"; "" given.'];
        yield 'scope with a separator' => ['a|b', self::NONCE, '"a|b" given.'];
        yield 'short nonce' => ['contact', 'abc', 'A nonce is 32 lower-case hexadecimal characters.'];
        yield 'upper-case nonce' => ['contact', strtoupper(self::NONCE), 'A nonce is 32'];
        yield 'nonce with a suffix' => ['contact', self::NONCE.'0', 'A nonce is 32'];
        yield 'nonce with a prefix' => ['contact', '0'.self::NONCE, 'A nonce is 32'];
    }

    #[DataProvider('invalidTokens')]
    public function testAnInvalidTokenIsRefused(string $scope, string $nonce, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SubmissionToken($scope, 1, $nonce);
    }

    private static function sign(string $payload): string
    {
        $base64 = static fn (string $bytes): string => sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return $base64($payload).'.'.$base64(hash_hmac('sha256', $payload, self::SECRET, true));
    }
}
