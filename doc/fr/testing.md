<!-- phil-dev source-sha256=49db2d09de7b5d710665e8aa23f7199bcc0abb6eb3949d21797847cd03a54c29 -->
# Tests

`tests/Unit` couvre les jetons et leur altération, les bornes de durée, le comptage des bits de la preuve, les règles
de contenu à leurs seuils, et l'ordre et l'unicité de l'inspection. Infection tient la bibliothèque à 100 % de
mutants tués.

```sh
vendor/bin/phpunit packages/spam-protection/tests
```

## Bancs d'essai

Les bancs d'essai phpbench de `tests/Benchmark/` mesurent les règles de contenu sur des textes jusqu'à la borne de
longueur et au-delà, ainsi que le vérificateur de preuve de travail à 12, 16 et 20 bits. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/spam-protection/tests/Benchmark
```
