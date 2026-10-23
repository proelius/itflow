# ITFlow - Traduction Française

## 📁 Structure

```
/home/pedro/.hermes/cache/scratch/itflow-translation/
├── lang/
│   └── fr/
│       └── common_strings.php    ← Fichier de traductions
├── login_fr.php                   ← Exemple de page traduite
└── TRANSLATION_GUIDE.md           ← Ce fichier
```

## 🔧 Comment Utiliser

### 1. Inclure le fichier de traductions

Dans chaque fichier PHP que tu veux traduire, ajoute au début :

```php
require_once "lang/fr/common_strings.php";
```

### 2. Remplacer les strings par `trans()`

**Avant :**
```php
echo "Email Address";
echo "<h1>Login</h1>";
```

**Après :**
```php
echo trans("Email Address");
echo "<h1>" . trans("Login") . "</h1>";
```

### 3. Fonction disponible

```php
trans($key)   // Retourne la traduction ou la string originale si non trouvée
__($key)      // Alias plus court
```

## 📋 Strings Traduites Actuellement

### Page de Connexion (login.php)
- Email Address → Adresse Email
- Login Key → Clé de Connexion
- Password → Mot de passe
- Continue → Continuer
- Sign In → Se connecter
- Agent Login → Connexion Agent
- Client Login → Connexion Client
- Two-Factor Authentication → Authentification à Deux Facteurs
- Remember me for → Se souvenir de moi pendant
- Forgot your password? → Mot de passe oublié ?
- Incorrect email or password → Email ou mot de passe incorrect
- Your IP address has been blocked → Votre adresse IP a été bloquée
- This action has been logged → Cette action a été enregistrée

### Strings Communes
- Save → Enregistrer
- Cancel → Annuler
- Delete → Supprimer
- Edit → Modifier
- Add → Ajout
- Close → Fermer
- Search → Rechercher
- Success → Succès
- Error → Erreur
- Warning → Avertissement
- Tickets → Tickets
- New → Nouveau
- Closed → Fermé
- Priority → Priorité
- Assignee → Assigné à

### Paramètres de Localisation
- Localization → Localisation
- Language → Langue
- Currency → Devise
- Timezone → Fuseau horaire
- Select a Locale → Sélectionner une langue
- Select a Timezone → Sélectionner un fuseau horaire

## 🚀 Pour Traduire d'Autres Pages

### Pages Principales à Traduire

1. **admin/settings_localization.php** (73 lignes)
2. **admin/users.php** (244 lignes)
3. **agent/tickets.php** (567 lignes)
4. **agent/dashboard.php** (1241 lignes)
5. **client/tickets.php** (118 lignes)

### Étapes

1. **Ouvrir le fichier** que tu veux traduire
2. **Identifier toutes les strings visibles** (labels, boutons, messages)
3. **Ajouter les traductions** dans `lang/fr/common_strings.php`
4. **Remplacer les strings** par `trans("...")`
5. **Tester** la page traduite

## ⚠️ Limitations Actuelles

- ✅ **Traductions de base** : login, boutons, messages d'erreur
- ⚠️ **Strings dynamiques** : Variables PHP non traduites (ex: `$config_app_name`)
- ⚠️ **Messages SQL** : Requêtes et messages de la DB non traduits
- ⚠️ **JavaScript** : Strings dans les fichiers .js non traduites
- ⚠️ **Emails** : Templates d'emails dans la base de données non traduits

## 📝 Exemple Complet

Fichier modifié : `login_fr.php` (dans le dossier scratch)

Ce fichier montre comment :
1. Inclure le fichier de traductions
2. Utiliser la fonction `trans()`
3. Gérer les fallbacks (si traduction manquante)

## 🔄 Synchroniser avec ITFlow Officiel

Si tu veux contribuer au projet officiel :

1. **Créer un dossier** `language/fr/` dans le repo ITFlow
2. **Structurer** par module (admin/, agent/, client/)
3. **Soumettre une PR** à https://github.com/itflow-org/itflow
4. **Suivre** le guide CONTRIBUTING.md

## 📊 Progression

| Page | Lignes | Traduit ? |
|------|--------|-----------|
| login.php | 820 | ✅ Exemple créé |
| admin/settings_localization.php | 73 | ⏳ À traduire |
| admin/users.php | 244 | ⏳ À traduire |
| agent/tickets.php | 567 | ⏳ À traduire |
| agent/dashboard.php | 1241 | ⏳ À traduire |
| client/tickets.php | 118 | ⏳ À traduire |

**Total estimé :** 3000+ lignes de code à traduire

## 🎯 Prochaines Étapes

1. **Traduire admin/settings_localization.php** (page de configuration)
2. **Traduire agent/tickets.php** (interface principale)
3. **Traduire client/tickets.php** (portail client)
4. **Ajouter les traductions JavaScript**
5. **Traduire les templates d'emails**

## 📞 Besoin d'Aide ?

Pour traduire une page spécifique, demande-moi :
> "Traduis la page [nom_du_fichier] pour moi"

Je vais :
1. Extraire toutes les strings à traduire
2. Les ajouter au fichier `common_strings.php`
3. Créer une version modifiée du fichier avec `trans()`
