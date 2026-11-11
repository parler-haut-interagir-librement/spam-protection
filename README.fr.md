<!-- phil-dev source-sha256=1e6ab3eb520fe8e18d97324be751eb13a09db6e287f9d28fa8ef3437c0b6d61d -->
# phil/spam-protection

**Repérez le pourriel dans l'envoi d'un formulaire sans captcha, en PHP simple.**

phil/spam-protection vérifie un envoi selon plusieurs signaux. Un jeton signé (HMAC-SHA256) dit où et quand le
formulaire a été rendu, et `TimingPolicy` juge le temps qu'a pris le remplissage. `ProofOfWork` vérifie un petit
calcul qu'a fait le navigateur. `ContentRules` examine le texte à la recherche d'octets qui ne sont pas de l'UTF-8,
de trop de liens, de termes interdits et de charabia. Chaque vérification lève un `Signal`, et une `Inspection` les rassemble. Chaque signal est une
observation que fait le serveur lui-même, jamais une affirmation du client.

Il s'adresse à tout code PHP qui reçoit des formulaires d'inconnus et veut tenir le pourriel à l'écart sans faire
résoudre une énigme aux gens. Il ne produit pas le rendu des formulaires, ne garde pas les jetons utilisés et ne
compte pas les envois. Ces tâches dépendent d'un cadriciel : elles restent hors de la bibliothèque.

Dans une application Symfony, utilisez `phil/spam-protection-bundle`. Il ajoute l'option de formulaire, le
contrôleur Stimulus qui résout la preuve, le stockage des jetons utilisés et la limite de débit. La bibliothèque
elle-même n'a besoin que de `ext-sodium` et de `symfony/string`, et ses classes sont de petites valeurs et des règles
qui retournent des signaux.

## État

| Partie | État |
|---|---|
| `Inspection\Signal`, `Inspection\Inspection` | Implémenté et testé |
| `Token\SubmissionToken`, `Token\TokenCodec` (HMAC-SHA256) | Implémenté et testé |
| `Timing\TimingPolicy` | Implémenté et testé |
| `Proof\ProofOfWork` | Implémenté et testé |
| `Content\ContentRules` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis. |
| [Signaux](doc/fr/signals.md) | Chaque vérification, le signal qu'elle lève, et comment les lancer. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment la bibliothèque est testée. |
| [Référence](doc/fr/reference.md) | Chaque type. |

## Démarrage rapide

Quand le formulaire est rendu, émettez un jeton pour lui et donnez au navigateur son nonce à résoudre :

```php
use Phil\SpamProtection\Token\SubmissionToken;
use Phil\SpamProtection\Token\TokenCodec;

$codec = new TokenCodec($secret);
$token = new SubmissionToken('contact', time(), bin2hex(random_bytes(16)));
$field = $codec->encode($token);        // a hidden field of the form; $token->nonce is the proof's challenge
```

Quand le formulaire revient, vérifiez chaque signal. `decode()` retourne `null` pour un jeton absent, falsifié ou
mal formé, ce qui est exactement ce qu'envoie un robot, et ne vérifie que la signature : comparer la portée du jeton
avec celle du formulaire revient à l'appelant.

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

`phil/spam-protection-bundle` exécute tout ce déroulement, stockage des jetons utilisés et limite de débit compris,
dans son `SubmissionInspector`.

## Prérequis

PHP 8.5 ou plus récent avec `ext-sodium` ; `symfony/string` 8.2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
