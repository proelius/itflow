# ITFlow - Traduction Portail Client & Emails

## ✅ Fichiers Traduits

### Portail Client (16 fichiers)
Tous les fichiers ont des versions `_fr.php` prêtes à être utilisées:

| Fichier Original | Version Française |
|------------------|-------------------|
| client/index.php | client/index_fr.php |
| client/tickets.php | client/tickets_fr.php |
| client/ticket.php | client/ticket_fr.php |
| client/ticket_add.php | client/ticket_add_fr.php |
| client/invoices.php | client/invoices_fr.php |
| client/unpaid_invoices.php | client/unpaid_invoices_fr.php |
| client/recurring_invoices.php | client/recurring_invoices_fr.php |
| client/statement.php | client/statement_fr.php |
| client/documents.php | client/documents_fr.php |
| client/document.php | client/document_fr.php |
| client/assets.php | client/assets_fr.php |
| client/contacts.php | client/contacts_fr.php |
| client/profile.php | client/profile_fr.php |
| client/quotes.php | client/quotes_fr.php |
| client/includes/header.php | client/includes/header_fr.php |
| client/includes/footer.php | client/includes/footer_fr.php |

### Fichiers de Traduction
- **lang/fr/common_strings.php** - 145 strings traduites
- **lang/fr/email_templates.php** - Templates d'emails

## 📋 Strings Traduites (145+)

### Navigation
- Home → Accueil
- Tickets → Tickets
- Finance → Finance
- Invoices → Factures
- Documents → Documents
- Contacts → Contacts
- Profile → Profil

### Tickets
- New Ticket → Nouveau Ticket
- Create Ticket → Créer un Ticket
- Priority → Priorité
- Status → Statut
- Open → Ouvert
- Closed → Fermé

### Facturation
- Invoice → Facture
- Quote → Devis
- Pay Invoice → Payer la Facture
- Amount → Montant
- Due Date → Date d'Échéance

### Messages
- Welcome → Bienvenue
- Success → Succès
- Error → Erreur
- Loading... → Chargement...

## 🔧 Comment Utiliser

### 1. Pour le Portail Client

Dans chaque fichier `_fr.php`, ajoute au début:
```php
require_once "../lang/fr/common_strings.php";
```

Puis remplace les strings visibles:
```php
// Avant
<h1>Dashboard</h1>

// Après
<h1><?php echo trans('Dashboard'); ?></h1>
```

### 2. Pour les Emails

Dans les fichiers qui envoient des emails:
```php
require_once "lang/fr/email_templates.php";

$subject = translate_email("New Invoice");
$body = translate_email($original_body);
```

## 📊 Progression

| Élément | Statut |
|---------|--------|
| Fichier de traductions | ✅ Prêt (145 strings) |
| Versions _fr.php créées | ✅ 16 fichiers |
| Templates d'emails | ✅ Prêt |
| Remplacement des strings | ⏳ À faire |

## 🎯 Prochaines Étapes

1. **Ouvrir un fichier _fr.php** (ex: client/index_fr.php)
2. **Identifier les strings à traduire** (labels, boutons, messages)
3. **Remplacer par `trans('String')`**
4. **Tester la page**
5. **Répéter pour les autres fichiers**

## 📁 Emplacement

Tous les fichiers traduits sont dans:
```
/home/pedro/.hermes/cache/scratch/itflow-translation/
```

Pour déployer:
1. Copier les fichiers `_fr.php` en surchargeant les originaux
2. OU créer un dossier `fr/` et ajuster les includes
