<!-- phil-dev source-sha256=5da75b924c83120d4485065b1e76233cc354371ac64bc34b555cf192562d27e1 -->
# Signaux

| Vérification | Type | Signaux |
|---|---|---|
| Un champ caché qu'une personne ne remplit jamais | l'application | `HoneypotFilled` |
| Où et quand le formulaire a été rendu | `TokenCodec::decode()`, puis l'appelant | `TokenMissing` (aucun jeton), `TokenForged` (`decode()` retourne `null` : une signature qui ne correspond pas, un jeton mal formé ; ou l'appelant constate que `$token->scope` n'est pas la portée du formulaire) |
| Le temps qu'a pris le remplissage | `TimingPolicy::check($issuedAt, $now)` | `TooFast`, `TokenExpired` |
| Un jeton envoyé deux fois | le stockage des jetons utilisés de l'application | `TokenReplayed` |
| La preuve de travail | `ProofOfWork::verify($nonce, $solution)` | `ProofInvalid` |
| Le texte | `ContentRules::inspect($text)` | `Malformed`, `TooLong`, `TooManyLinks`, `ForbiddenTerm`, `Gibberish` |
| La fréquence des envois d'un client | le limiteur de débit de l'application | `RateLimited` |

`Inspection::clean()->with(...$signals)` les rassemble, chacun une fois, dans l'ordre où ils ont été levés ;
`isSpam()` est vrai dès qu'il y en a un.

## Jetons

Un `SubmissionToken` contient la portée du formulaire, la seconde de son émission et un nonce hexadécimal de
32 caractères. `TokenCodec` l'écrit sous la forme `payload.signature`, les deux en base64 compatible avec les URL,
signé avec HMAC-SHA256 sous un secret d'au moins 16 octets, et le relit en temps constant : tout ce qu'il ne peut pas
vérifier donne `null`. `decode()` ne sait pas quel formulaire est envoyé : l'appelant compare donc `$token->scope` avec
la portée du formulaire et lève `TokenForged` quand elles diffèrent. Un jeton émis pour un formulaire n'est pas
accepté pour un autre.

## Durée

`new TimingPolicy(minimum: 3, maximum: 3600)` : un envoi plus tôt que le minimum donne `TooFast`, plus tard que le
maximum `TokenExpired`, les deux bornes comprises dans l'intervalle permis.

## Preuve de travail

`new ProofOfWork(difficulty: 12)` : le navigateur trouve le plus petit nombre `n` tel que le SHA-256 de `nonce:n`
commence par `difficulty` bits à zéro ; le serveur le vérifie avec un seul calcul d'empreinte. Une solution ne
comporte que des chiffres décimaux, au plus 12. Une difficulté de 0 n'exige rien ; le maximum est
`ProofOfWork::MAXIMUM_DIFFICULTY`, 24.

## Contenu

`new ContentRules(maximumLinks: 3, forbiddenTerms: ['casino'], detectGibberish: true, maximumLength: 20_000)` :

- **l'encodage** : un texte qui n'est pas de l'UTF-8 valide, ce qu'aucun navigateur n'envoie, lève `Malformed` ; il est
  lu malgré tout, chaque séquence invalide remplacée comme le fait `mb_scrub()`, pour que les autres signaux soient
  levés aussi et que les octets d'un robot n'arrêtent jamais l'inspection ;
- **la longueur** se compte en caractères ; un texte plus long que `maximumLength` lève `TooLong`, et seuls ses
  `maximumLength` premiers caractères sont lus pour les liens, les termes et le charabia : le coût d'une inspection
  est ainsi borné, quoi qu'on envoie. La valeur par défaut est `ContentRules::DEFAULT_MAXIMUM_LENGTH`, 20 000, et
  `null` ne fixe aucune borne ;
- **les liens** sont les adresses `http://`, `https://` et `www.`, chacune comptée une fois : une adresse commence là
  où aucune lettre, aucun chiffre, aucun point ni aucune barre oblique ne la précède. Ainsi `https://www.a.example`
  est un seul lien et `https://a.example,https://b.example` en est deux ; `null` n'en compte aucun ;
- **les termes** correspondent comme mots entiers, ou suites entières de mots, sans égard à la casse, aux accents ni à
  ce qui sépare les mots : `casino` correspond à `Casino` et à `CASÍNO`, pas à `casinos` ; `free money` correspond à
  `free money` et à `Free, money!`, pas à `free moneys`. Un terme est stocké comme ses mots joints par une espace
  (`free-money` devient `free money`), et un terme sans lettre ni chiffre est refusé ;
- **le charabia** est un mot de 12 lettres ou plus sans voyelle, ou de 20 lettres ou plus dont moins d'une sur huit
  est une voyelle, `y` comptant comme voyelle.
