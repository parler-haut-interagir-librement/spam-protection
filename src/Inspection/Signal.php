<?php

declare(strict_types=1);

namespace Phil\SpamProtection\Inspection;

/**
 * One reason the server holds a submission for spam. Each is an observation the server makes itself — a field a
 * person never fills, a token it signed, a delay it measured, the text it received —, never a claim the client
 * makes about itself.
 */
enum Signal: string
{
    /** The hidden field a person never sees was filled. */
    case HoneypotFilled = 'honeypot_filled';

    /** The submission carries no protection token: the form was not rendered by this server, or was stripped. */
    case TokenMissing = 'token_missing';

    /** The token's signature does not match, or it was issued for another form. */
    case TokenForged = 'token_forged';

    /** The token is older than a person filling the form would take. */
    case TokenExpired = 'token_expired';

    /** The token was already used by an earlier submission. */
    case TokenReplayed = 'token_replayed';

    /** The form came back sooner after it was rendered than a person could fill it. */
    case TooFast = 'too_fast';

    /** The proof of work the browser computes is missing or wrong. */
    case ProofInvalid = 'proof_invalid';

    /** The text is not valid UTF-8, which no browser sends. */
    case Malformed = 'malformed';

    /** The text is longer than the form allows. */
    case TooLong = 'too_long';

    /** The text holds more links than the form allows. */
    case TooManyLinks = 'too_many_links';

    /** The text holds a term the application forbids. */
    case ForbiddenTerm = 'forbidden_term';

    /** The text reads as random characters rather than words. */
    case Gibberish = 'gibberish';

    /** The same client submitted the form more often than the limit allows. */
    case RateLimited = 'rate_limited';
}
