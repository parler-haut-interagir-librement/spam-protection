# Extension points

The library is plain PHP: it declares no service and no port, and its classes are final. Each check is a value the
application configures and calls; the checks that need state are the application's, and their signals are the
library's.

| Point | How |
|---|---|
| The secret of the tokens | The secret given to `TokenCodec`, at least 16 bytes, from the application's secrets |
| How long a form may take | `new TimingPolicy(minimum: …, maximum: …)` |
| The cost of the proof | `new ProofOfWork(difficulty: …)`, from 0 to `ProofOfWork::MAXIMUM_DIFFICULTY` |
| The content rules | `new ContentRules(maximumLinks: …, forbiddenTerms: […], detectGibberish: …, maximumLength: …)`: the terms are the application's list |
| Tokens submitted twice | The application's store of used nonces raises `Signal::TokenReplayed` |
| Rate limiting | The application's rate limiter raises `Signal::RateLimited` |
| The hidden field | The application's form raises `Signal::HoneypotFilled` |
| What a held submission becomes | `Inspection::isSpam()` and `has()`: the application decides — refuse, queue for moderation, ask again |

The signals are a closed enum: a check of the application's own that has no matching signal keeps its result beside
the inspection rather than in it.
