# CLAUDE.md - Règles i18n ITFlow

## Règles absolues

### 1. Texte anglais identique à master
Le texte anglais passé à `t()` doit être **IDENTIQUE, au caractère près**, au texte d'origine de master.

**Exemple correct:**
```php
t('Incorrect username or password.')  // Texte exact de master
```

**Exemple incorrect:**
```php
t('Failed login')  // Texte modifié - REJETÉ
t('Incorrect username or password')  // Point manquant - REJETÉ
```

### 2. Aucune conversion hors périmètre validé
Ne convertir QUE les textes explicitement validés (Phase 1: login.php + top_nav.php).

**Convertisseable:**
- placeholder, title, alt, aria-label (attributs HTML)
- Textes visibles dans le HTML

**Ne PAS convertir:**
- Textes dans logAudit()
- JavaScript (code JS, pas les attributs)
- SQL (requêtes, noms de colonnes)
- Valeurs techniques d'attributs (href, src, data-*)
- Phrases concaténées (ex: "Client " . t('Email'))
- Noms de champs, valeurs de formulaire
- Identifiants, URLs
- Données stockées en base (statuts, catégories)
- E-mails et PDF (signaler au lieu de convertir)

### 3. Preuves brutes obligatoires
Ne jamais écrire « vérifié » sans sortie brute collée.

**Exemple:**
```
✅ php -l login.php: No syntax errors detected
✅ git diff master -- login.php: [diff complet collé]
✅ i18n_check_original.php: SUCCESS: All t() texts match master
```

### 4. Pas de simulation de rendu
Ne jamais simuler un rendu PHP. Si impossible de lancer PHP, le dire explicitement.

**Exemple:**
```
⚠️ Rendu réel impossible: nécessite MariaDB active et configuration complète
Alternative: script de test minimal avec mocks
```

### 5. Signaler les cas ambiguës
Au lieu de convertir un cas ambigu, le signaler.

**Exemple:**
```
⚠️ Cas ambigu: "Client " dans placeholder de login.php
- Est-ce un préfixe à traduire?
- Ou une concaténation de variable?
→ En attente de clarification
```

## Périmètre actuel

### Phase 1 (en attente de validation par moi)
- ✅ login.php (28 appels t())
- ✅ top_nav.php (4 appels t())

### Phase 2 (en attente)
- ⏸️ Portail client (à valider)
- ⏸️ PDF (à valider)
- ⏸️ E-mails (à valider)

## Scripts de vérification

### i18n_check_original.php
Vérifie que chaque t('X') correspond exactement au texte de master.

```bash
php scripts/i18n_check_original.php
```

**Sortie:** Affiche le nombre de fichiers vérifiés et de t() calls, puis SUCCESS ou FAILED avec la liste des erreurs.

### i18n_extract_texts.php
Extrait tous les textes visibles traduisibles.

```bash
php scripts/i18n_extract_texts.php
```

**Sortie:** Affiche les textes visibles trouvés et ceux exclus (contenant des variables).

## Workflow

1. **Avant conversion:** Vérifier le texte exact dans master
2. **Pendant conversion:** Utiliser t('texte exact')
3. **Après conversion:** 
   - php -l sur chaque fichier
   - i18n_check_original.php
   - git diff pour montrer les changements
4. **Avant Phase 2:** Obtenir accord explicite

## Validation requise avant toute conversion supplémentaire
