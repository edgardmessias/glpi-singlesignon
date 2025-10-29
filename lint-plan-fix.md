# Plan de correction lint

## Objectif
Faire passer `vendor/bin/robo lint` (et donc le job GitHub Actions) sans erreurs.

## Étapes à suivre

### 1. `setup.php`
- Ajouter une ligne vide après le dernier `use`.

### 2. Classes `inc/*.class.php`
Fichiers concernés : `inc/preference.class.php`, `inc/provider.class.php`, `inc/provider_user.class.php`, `inc/toolbox.class.php`.
- Supprimer la ligne vide supplémentaire en fin de fichier (une seule ligne vide terminant le fichier).

### 3. Exceptions des scripts `front/`
Fichiers concernés : `front/callback.php`, `front/picture.send.php`.
- Préfixer les exceptions avec `\` (`\BadRequestHttpException`, `\NotFoundHttpException`, `\SessionExpiredException`) ou ajouter les `use` correspondants.

### 4. `src/Provider.php`
- Remplacer `elseif` par `else if` pour suivre la convention GLPI.
- Retirer les espaces inutiles en fin de ligne (lignes 968, 971, 1024 dans le rapport actuel).

### 5. `src/LoginRenderer.php`
- Revenir à l’indentation GLPI : 3 espaces par niveau, accolades sur la même ligne que la déclaration.
- Laisser des blancs en fin de fichier conformes (une seule ligne vide).

## Vérification finale
Après toutes les corrections, exécuter localement :  
`vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes`
