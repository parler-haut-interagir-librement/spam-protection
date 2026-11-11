# Changelog

All notable changes to `phil/spam-protection` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `Inspection\Signal` and `Inspection\Inspection`.
- `Token\SubmissionToken` and `Token\TokenCodec`; the caller compares the token's scope.
- `Timing\TimingPolicy` and `Proof\ProofOfWork`.
- `Content\ContentRules`, which raises `Signal::Malformed` for a text that is not valid UTF-8 and reads it with each
  bad sequence replaced, rather than throwing; bounds the text it reads (`maximumLength`, 20,000 characters by
  default) and raises `Signal::TooLong` beyond it; counts an `https://www.` address as one link, and each of several
  addresses glued together by a comma or a quote; and matches a forbidden term of several words, or with punctuation
  between its words, as those words in a row.
- The documentation in `doc/`, one file per topic, indexed by the README, whose quick start handles a missing or
  forged token, compares the token's scope and checks the proof.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
