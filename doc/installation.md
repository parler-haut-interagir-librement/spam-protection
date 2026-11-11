# Installation

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The declared minimum |
| `ext-sodium` | `*` | The URL-safe base64 of tokens |
| `symfony/string` | `^8.2` | Terms matched without regard to case or accents |

```bash
composer require phil/spam-protection:^0.1@dev
```

In a Symfony application, require `phil/spam-protection-bundle` instead: it requires the library.
