<!-- phil-dev source-sha256=be7dac207d8bb25c44db6ed002549cbb1edc7e8eb1871f9f2d51c5971f3665c3 -->
# Installation

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le minimum déclaré |
| `ext-sodium` | `*` | Le base64 compatible avec les URL des jetons |
| `symfony/string` | `^8.2` | Les termes comparés sans égard à la casse ni aux accents |

```bash
composer require phil/spam-protection:^0.1@dev
```

Dans une application Symfony, requérez plutôt `phil/spam-protection-bundle` : il requiert la bibliothèque.
