# CLAUDE.md - Règles pour la traduction (i18n) de ce fork d'ITFlow

## Dépôt et branches
- Le remote « fork » pointe vers github.com/proelius/itflow (vérifie avec git remote -v) ;
  « origin » peut pointer vers le dépôt d'origine itflow-org/itflow.
- AVANT de créer une branche : git fetch fork, puis partir de fork/master. Ne jamais
  partir du master local ni de origin/master.
- Une branche par PR. Ne crée aucune autre branche. Pousse sans --force ; seulement
  --force-with-lease sur ta propre branche quand tu l'as refaite.
- Ne pousse jamais de rapports, de scripts de test jetables ou de fichiers .json/.md hors
  périmètre. Le périmètre est donné dans la consigne ; contrôle-le avec
  git diff --name-only fork/master.

## Preuves
- Colle des sorties brutes et COMPLÈTES : jamais de « ... », de « ci-dessus » ni de résumé.
- N'écris jamais « vérifié » ou « OK » sans la sortie qui le prouve. Ne simule jamais un rendu.
- Après un push, colle git ls-remote --heads fork <branche>.

## Tests
- Ne modifie, n'affaiblis et n'inverse jamais un test existant : si un test échoue, corrige
  le code ou les données. Si tu crois qu'un test est faux, dis-le et attends mon accord.
- Un test sauté (par exemple faute de l'extension intl) n'est jamais un test réussi.
- Avant de dire « terminé » : php -l sur chaque fichier modifié ; php scripts/i18n_selftest.php ;
  php scripts/i18n_check_keys.php ; php scripts/i18n_selftest_country.php.
- scripts/i18n_check_original.php n'est plus fiable (545 « Unknown » sur master) : ne t'en
  sers pas comme garde.

## Règles de traduction
- Le texte anglais passé à t('...') est identique, au caractère près, au texte d'origine
  de master. Sans configuration, l'application s'affiche comme avant (anglais).
- Ne traduis jamais : logAudit, logHistory, les messages destinés aux agents (flashAlert
  côté agent), le SQL, les comparaisons de statuts, les noms de champs, les URL, les noms
  de fichiers, les données saisies par les utilisateurs, les dates et les montants.
- Jamais de morceaux de phrase concaténés : une phrase entière avec des paramètres
  nommés, t('Hello {name},', ['{name}' => $name]).
  - lang/fr.php : uniquement l'apostrophe typographique courbe ’ (U+2019) ; jamais
       l'apostrophe droite ' (U+0027) ni \' (certains textes sont insérés dans des
       requêtes SQL, notamment les e-mails).
- lang/en.php et lang/fr.php ont exactement les mêmes clés ; chaque clé est utilisée, aucune
  clé inutile.
- Contenu destiné aux clients (PDF, e-mails, pages vues par le client) : appliquer la langue
  client avec i18n_set_language(client_language()) ou i18n_with(). Ne modifie functions/i18n.php
  qu'en me montrant d'abord le diff.

## Pages web : retours à la ligne et ?>
- PHP avale UN retour à la ligne placé IMMÉDIATEMENT après ?> .
- Une ligne vide de compensation n'est nécessaire QUE si ?> est le DERNIER caractère de la
  ligne (rien après) ET si l'original avait un retour à la ligne à cet endroit.
- Si la ligne continue après ?> (ex. `<th><?= t('X') ?></th>`, `<td>…?></td>`, `…?></a>`),
  AUCUNE ligne vide n'est ajoutée.
- Exemple Download (compensation requise) :
  ```
  <i class="fa fa-fw fa-download me-2"></i><?= t('Download') ?>

  </a>
  ```
- Exemple Print (aucune compensation) :
  ```
  <i class="fas fa-fw fa-print me-2"></i><?= t('Print') ?></a>
  ```
- Exemple Accept (compensation requise, closing tag sur sa ligne) :
  ```
  <i class="fas fa-fw fa-thumbs-up me-2"></i><?= t('Accept') ?>

  </a>
  ```
