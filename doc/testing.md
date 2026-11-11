# Testing

`tests/Unit` covers tokens and their tampering, the timing bounds, the proof's bit counting, the content rules at
their thresholds, and the inspection's order and uniqueness. Infection keeps the library at 100 % of mutants killed.

```sh
vendor/bin/phpunit packages/spam-protection/tests
```

## Benchmarks

The phpbench benchmarks in `tests/Benchmark/` measure the content rules over texts up to and past the length bound, and the proof-of-work verifier at 12, 16 and 20 bits. From the monorepo root:

```sh
composer bench -- packages/spam-protection/tests/Benchmark
```
