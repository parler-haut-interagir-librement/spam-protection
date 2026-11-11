# phil/spam-protection

**Spot spam in a form submission without a captcha, in plain PHP.**

phil/spam-protection checks a submission against several signals. A signed token (HMAC-SHA256) says where and when
the form was rendered, and `TimingPolicy` judges the time the filling took. `ProofOfWork` checks a small
computation the browser has done. `ContentRules` looks at the text for bytes that are not UTF-8, too many links,
forbidden terms and gibberish. Each check raises a `Signal`, and an `Inspection` collects them. Every signal is an
observation the server makes itself, never a claim the client makes.

It is for any PHP code that receives forms from strangers and wants to keep spam out without making people solve a
puzzle. It does not render forms, keep used tokens or count submissions. Those depend on a framework, so they
stay out of the library.

In a Symfony application, use `phil/spam-protection-bundle`. It adds the form option, the Stimulus controller that
solves the proof, the store of used tokens and the rate limit. The library itself needs only `ext-sodium` and
`symfony/string`, and its classes are small values and rules that return signals.

## Status

| Part | State |
|---|---|
| `Inspection\Signal`, `Inspection\Inspection` | Implemented and tested |
| `Token\SubmissionToken`, `Token\TokenCodec` (HMAC-SHA256) | Implemented and tested |
| `Timing\TimingPolicy` | Implemented and tested |
| `Proof\ProofOfWork` | Implemented and tested |
| `Content\ContentRules` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements. |
| [Signals](doc/signals.md) | Each check, the signal it raises, and how to run them. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |
| [Reference](doc/reference.md) | Every type. |

## Quick start

When the form is rendered, issue a token for it and give the browser its nonce to solve:

```php
use Phil\SpamProtection\Token\SubmissionToken;
use Phil\SpamProtection\Token\TokenCodec;

$codec = new TokenCodec($secret);
$token = new SubmissionToken('contact', time(), bin2hex(random_bytes(16)));
$field = $codec->encode($token);        // a hidden field of the form; $token->nonce is the proof's challenge
```

When the form comes back, check every signal. `decode()` returns `null` for a missing, forged or malformed token,
which is exactly what a robot sends, and it checks only the signature: comparing the token's scope with the form's
is the caller's job.

```php
use Phil\SpamProtection\Content\ContentRules;
use Phil\SpamProtection\Inspection\Inspection;
use Phil\SpamProtection\Inspection\Signal;
use Phil\SpamProtection\Proof\ProofOfWork;
use Phil\SpamProtection\Timing\TimingPolicy;
use Phil\SpamProtection\Token\SubmissionToken;

$encoded = $submitted['_token'] ?? '';
$token = '' === $encoded ? null : $codec->decode($encoded);
$inspection = Inspection::clean();

if ('' === $encoded) {
    $inspection = $inspection->with(Signal::TokenMissing);
} elseif (!$token instanceof SubmissionToken || 'contact' !== $token->scope) {
    $inspection = $inspection->with(Signal::TokenForged);   // unreadable, or issued for another form
} else {
    if (!new ProofOfWork()->verify($token->nonce, $submitted['_proof'] ?? '')) {
        $inspection = $inspection->with(Signal::ProofInvalid);
    }

    $inspection = $inspection->with(...array_filter([new TimingPolicy()->check($token->issuedAt, time())]));
    // Then claim $token->nonce in the application's store of used tokens: TokenReplayed if it was already there.
}

$inspection = $inspection->with(...new ContentRules(maximumLinks: 2)->inspect($submitted['message'] ?? ''));

if ($inspection->isSpam()) {
    // hold the submission: $inspection->signals says why
}
```

`phil/spam-protection-bundle` runs this whole flow, the store of used tokens and the rate limit included, in its
`SubmissionInspector`.

## Requirements

PHP 8.5 or later with `ext-sodium`; `symfony/string` 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
