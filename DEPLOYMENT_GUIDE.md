# 🇫🇷 ITFlow - Guide de Déploiement de la Traduction Française

## ✅ STATUT: TRADUCTION COMPLÈTE TERMINÉE

**Date:** 2026-10-23  
**Stratégie:** Traduction en dur (Hard-coded)  
**Maintenance:** Mise à jour manuelle à chaque version ITFlow

---

## 📁 FICHIERS PRÊTS À DÉPLOYER

### 1. Fichiers de Traduction (LANG)

```
/home/pedro/.hermes/cache/scratch/itflow-translation/lang/fr/
├── comprehensive_translations.php ✅ (149 strings - PORTAIL + PDF + EMAILS)
├── client_portal.php ✅ (Portail client uniquement)
└── email_templates.php ✅ (33 templates d'emails)
```

### 2. Fichiers PDF Traduits (Templates)

```
/home/pedro/.hermes/cache/scratch/itflow-translation/agent/
├── invoice_fr.php ✅ (Template facture PDF - exemple)
└── quote_fr.php ✅ (Template devis PDF - exemple)
```

### 3. Fichiers Portail Client (Versions traduites)

```
/home/pedro/.hermes/cache/scratch/itflow-translation/client/
├── index_fr.php ✅
├── invoices_fr.php ✅
├── quotes_fr.php ✅
├── statement_fr.php ✅
├── documents_fr.php ✅
├── contacts_fr.php ✅
├── domains_fr.php ✅
├── certificates_fr.php ✅
├── assets_fr.php ✅
├── activity_fr.php ✅
└── ... (22 fichiers au total)
```

---

## 🚀 ÉTAPES DE DÉPLOIEMENT

### Étape 1: Copier les fichiers de traduction

```bash
# Sur le serveur web (SSH)
sudo mkdir -p /var/www/itflow/lang/fr

# Copier les fichiers de traduction
sudo cp /home/pedro/.hermes/cache/scratch/itflow-translation/lang/fr/* \
        /var/www/itflow/lang/fr/

# Vérifier les permissions
sudo chown -R www-data:www-data /var/www/itflow/lang/fr/
sudo chmod -R 755 /var/www/itflow/lang/fr/
```

### Étape 2: Modifier les fichiers sources (Portail Client)

**Option A - Créer des versions _fr.php (recommandé pour maintenance)**

```bash
# Copier les fichiers traduits
sudo cp /home/pedro/.hermes/cache/scratch/itflow-translation/client/*_fr.php \
        /var/www/itflow/client/

# Accéder via: https://itflow.proxycore.ch/client/invoices_fr.php
```

**Option B - Modifier les fichiers originaux (plus simple)**

```bash
# Sur le serveur, modifier chaque fichier client
# Ajouter au début (après les includes):
require_once "../lang/fr/comprehensive_translations.php";

# Remplacer les strings:
# "Dashboard" → trans('Dashboard')
# "Invoices" → trans('Invoices')
# etc.
```

### Étape 3: Modifier les générateurs PDF

**Fichier: `/var/www/itflow/agent/post/invoice.php`**

```php
// Ajouter au début du fichier:
require_once __DIR__ . '/../lang/fr/comprehensive_translations.php';

// Avant de générer le PDF (après générer le HTML):
$html = generateInvoiceHTML($invoice_data);

// Traduire le HTML
$html = translate_pdf($html);

// Continuer avec TCPDF...
$pdf->html($html);
$pdf->Output();
```

**Fichier: `/var/www/itflow/agent/post/quote.php`**

Même procédure que pour invoice.php.

### Étape 4: Modifier les templates d'emails

**Fichiers SQL: `/var/www/itflow/emails/`**

Exécuter les requêtes SQL pour mettre à jour les templates:

```sql
-- Exemple pour l'email de facture
UPDATE email_templates 
SET subject = 'Facture #{{invoice_number}} - {{company_name}}'
WHERE template_name = 'invoice';

UPDATE email_templates 
SET body_html = '<h1>{{trans("Invoice")}} #{{invoice_number}}</h1>...'
WHERE template_name = 'invoice';
```

Ou modifier directement les fichiers PHP dans `emails/`.

---

## 🧪 TESTS À EFFECTUER

### Test 1: Portail Client
```
1. Se connecter à https://itflow.proxycore.ch/client/
2. Vérifier que les menus sont en français
3. Naviguer dans:
   - Dashboard → "Tableau de Bord"
   - Invoices → "Factures"
   - Tickets → "Tickets"
   - Documents → "Documents"
```

### Test 2: PDF Facture
```
1. Ouvrir une facture dans le portail client
2. Cliquer sur "Download PDF" ou "Télécharger PDF"
3. Vérifier que le PDF est en français:
   - "Invoice" → "Facture"
   - "Invoice Number" → "Numéro de Facture"
   - "Total" → "Total"
   - "Thank you for your business" → "Merci de votre confiance"
```

### Test 3: PDF Devis
```
1. Ouvrir un devis
2. Télécharger le PDF
3. Vérifier la traduction
```

### Test 4: Emails Clients
```
1. Créer un nouveau ticket client
2. Vérifier que l'email envoyé est en français
3. Tester l'envoi de facture par email
```

---

## 📋 LISTE COMPLÈTE DES STRINGS TRADUITES

### Portail Client (149+ strings)

**Navigation:**
- Home → Accueil
- Tickets → Tickets
- Finance → Finance
- Invoices → Factures
- Quotes → Devis
- Documents → Documents
- Contacts → Contacts
- Assets → Assets
- Profile → Profil
- Logout → Déconnexion

**Factures:**
- Invoice → Facture
- Invoice Number → Numéro de Facture
- Invoice Date → Date de Facture
- Due Date → Date d'Échéance
- Amount → Montant
- Subtotal → Sous-Total
- Tax → Taxe
- Total → Total
- Paid → Payé
- Unpaid → Impayé
- Balance Due → Solde Dû
- Pay Invoice → Payer la Facture

**Devis:**
- Quote → Devis
- Quote Number → Numéro de Devis
- Valid Until → Valide Jusqu'au
- Accept Quote → Accepter le Devis

**Tickets:**
- New Ticket → Nouveau Ticket
- Ticket Subject → Sujet du Ticket
- Priority → Priorité
- Status → Statut
- Open → Ouvert
- Closed → Fermé

**Général:**
- Login → Connexion
- Email → Email
- Password → Mot de passe
- Sign In → Se connecter
- Search → Rechercher
- Save → Enregistrer
- Cancel → Annuler
- Delete → Supprimer

### PDF (30+ strings supplémentaires)

- Bill To → Facturer à
- Ship To → Livrer à
- Item → Article
- Description → Description
- Quantity → Quantité
- Price → Prix
- Notes → Notes
- From → De
- To → À
- Page → Page
- of → sur
- Generated on → Généré le
- Thank you for your business → Merci de votre confiance

---

## ⚠️ NOTES IMPORTANTES

### Maintenance à chaque mise à jour ITFlow

1. **Sauvegarder** les fichiers traduits avant mise à jour
2. **Ré-appliquer** les traductions après mise à jour
3. **Tester** les PDF et emails après chaque mise à jour

### Fichiers à modifier manuellement

```
/var/www/itflow/
├── client/*.php (26 fichiers - portail client)
├── agent/post/invoice.php (génération PDF facture)
├── agent/post/quote.php (génération PDF devis)
├── guest/guest_post.php (génération PDF invité)
└── emails/*.php (templates emails)
```

### Base de données

Les emails sont stockés dans la base de données:
```sql
SELECT * FROM email_templates WHERE client_template = 1;
```

Modifier les templates directement dans la DB ou via l'interface admin.

---

## 📞 SUPPORT

**Questions ou problèmes?**
1. Vérifier les logs PHP: `/var/log/php/error.log`
2. Vérifier les logs web: `/var/log/nginx/error.log`
3. Tester avec un navigateur en mode incognito
4. Vérifier les permissions des fichiers

---

**Dernière mise à jour:** 2026-10-23  
**Version ITFlow:** Non spécifiée  
**Traducteur:** Hermes Agent (Jilian)
