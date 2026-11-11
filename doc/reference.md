# Reference

| Type | Kind | Role |
|---|---|---|
| `Inspection\Signal` | string-backed enum | One reason a submission is held |
| `Inspection\Inspection` | `final readonly class` | The signals of one submission |
| `Token\SubmissionToken` | `final readonly class` | Scope, issue time and nonce of a rendered form |
| `Token\TokenCodec` | `final readonly class` | Signs and reads tokens with HMAC-SHA256 |
| `Timing\TimingPolicy` | `final readonly class` | The minimum and maximum filling time |
| `Proof\ProofOfWork` | `final readonly class` | The proof's difficulty and its check |
| `Content\ContentRules` | `final readonly class` | Length, links, forbidden terms and gibberish |
