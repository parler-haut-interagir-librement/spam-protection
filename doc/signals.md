# Signals

| Check | Type | Signals |
|---|---|---|
| A hidden field a person never fills | the application | `HoneypotFilled` |
| Where and when the form was rendered | `TokenCodec::decode()`, then the caller | `TokenMissing` (no token), `TokenForged` (`decode()` returns `null`: a signature that does not match, a malformed token; or the caller finds that `$token->scope` is not the form's scope) |
| How long the form took to fill | `TimingPolicy::check($issuedAt, $now)` | `TooFast`, `TokenExpired` |
| A token submitted twice | the application's store of used tokens | `TokenReplayed` |
| The proof of work | `ProofOfWork::verify($nonce, $solution)` | `ProofInvalid` |
| The text | `ContentRules::inspect($text)` | `Malformed`, `TooLong`, `TooManyLinks`, `ForbiddenTerm`, `Gibberish` |
| How often a client submits | the application's rate limiter | `RateLimited` |

`Inspection::clean()->with(...$signals)` collects them, each once, in the order they were raised; `isSpam()` is
true as soon as there is one.

## Tokens

A `SubmissionToken` holds the form's scope, the second it was issued and a 32-character hexadecimal nonce.
`TokenCodec` writes it as `payload.signature`, both in URL-safe base64, signed with HMAC-SHA256 under a secret of at
least 16 bytes, and reads it back in constant time: anything it cannot verify is `null`. `decode()` does not know
which form is being submitted, so the caller compares `$token->scope` with the form's scope and raises
`TokenForged` when they differ: a token issued for one form is not accepted for another.

## Timing

`new TimingPolicy(minimum: 3, maximum: 3600)`: a submission sooner than the minimum is `TooFast`, later than the
maximum `TokenExpired`, both bounds included in the allowed range.

## Proof of work

`new ProofOfWork(difficulty: 12)`: the browser finds the smallest number `n` such that the SHA-256 of `nonce:n`
starts with `difficulty` zero bits; the server checks it with one hash. A solution is decimal digits only, at most
12. A difficulty of 0 requires nothing; the most is `ProofOfWork::MAXIMUM_DIFFICULTY`, 24.

## Content

`new ContentRules(maximumLinks: 3, forbiddenTerms: ['casino'], detectGibberish: true, maximumLength: 20_000)`:

- **encoding**: a text that is not valid UTF-8, which no browser sends, raises `Malformed`; it is still read, each
  bad sequence replaced as `mb_scrub()` replaces it, so that the other signals are raised as well and a robot's bytes
  never stop the inspection;
- **length** is counted in characters; a text longer than `maximumLength` raises `TooLong`, and only its first
  `maximumLength` characters are read for links, terms and gibberish, so the cost of an inspection is bounded
  whatever is submitted; the default is `ContentRules::DEFAULT_MAXIMUM_LENGTH`, 20,000, and `null` sets no bound;
- **links** are `http://`, `https://` and `www.` addresses, each counted once: an address starts where no letter,
  digit, dot or slash comes before it, so `https://www.a.example` is one link and `https://a.example,https://b.example`
  is two; `null` counts none;
- **terms** match as whole words, or whole sequences of words, without regard to case, accents or what separates the
  words: `casino` matches `Casino` and `CASÍNO`, not `casinos`; `free money` matches `free money` and `Free, money!`,
  not `free moneys`; a term is stored as its words joined by one space (`free-money` becomes `free money`), and a
  term with no letter or digit is refused;
- **gibberish** is a word of 12 letters or more without a vowel, or of 20 letters or more of which fewer than one in
  eight are vowels, `y` counting as one.
