<!-- phil-dev source-sha256=cb03708758bedd8a3a59debf910a628f9dd3932a66571d145e576a31b5e705d8 -->
# Points d'extension

La bibliothèque est en PHP simple : elle ne déclare ni service ni port, et ses classes sont finales. Chaque
vérification est une valeur que l'application configure et appelle ; les vérifications qui ont besoin d'un état
appartiennent à l'application, et leurs signaux à la bibliothèque.

| Point | Comment |
|---|---|
| Le secret des jetons | Le secret donné à `TokenCodec`, d'au moins 16 octets, tiré des secrets de l'application |
| Combien de temps un formulaire peut prendre | `new TimingPolicy(minimum: …, maximum: …)` |
| Le coût de la preuve | `new ProofOfWork(difficulty: …)`, de 0 à `ProofOfWork::MAXIMUM_DIFFICULTY` |
| Les règles de contenu | `new ContentRules(maximumLinks: …, forbiddenTerms: […], detectGibberish: …, maximumLength: …)` : les termes sont la liste de l'application |
| Les jetons envoyés deux fois | Le stockage des nonces utilisés de l'application lève `Signal::TokenReplayed` |
| La limite de débit | Le limiteur de débit de l'application lève `Signal::RateLimited` |
| Le champ caché | Le formulaire de l'application lève `Signal::HoneypotFilled` |
| Ce que devient un envoi retenu | `Inspection::isSpam()` et `has()` : l'application décide — refuser, mettre en file de modération, redemander |

Les signaux sont une enum fermée : une vérification propre à l'application qui n'a pas de signal correspondant garde
son résultat à côté de l'inspection plutôt que dedans.
