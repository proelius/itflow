# 🇫🇷 ITFlow - Intégration Complète de la Traduction Française

## 🎯 PROBLÈME IDENTIFIÉ

Le sélecteur de langue dans ITFlow **ne traduit pas l'interface**. Il ne change que:
- Le format des dates (JJ/MM/AAAA vs MM/JJ/AAAA)
- Le format des nombres/devises (1 234,56 vs 1,234.56)

**Solution:** Ajouter un système de traduction manuel.

---

## 📁 FICHIERS DE TRADUCTION CRÉÉS

```
/home/pedro/.hermes/cache/scratch/itflow-translation/lang/fr/
├── itflow_fr.po ✅ (Format standard gettext)
├── itflow_fr.php ✅ (Traductions PHP - 80+ strings)
└── itflow_integration.php ✅ (Configuration)
```

---

## 🚀 INSTALLATION (3 étapes)

### Étape 1: Copier les fichiers de traduction

```bash
# Sur le serveur ITFlow
sudo mkdir -p /var/www/itflow/lang/fr
sudo cp /home/pedro/.hermes/cache/scratch/itflow-translation/lang/fr/*         /var/www/itflow/lang/fr/
sudo chown -R www-data:www-data /var/www/itflow/lang/fr/
```

### Étape 2: Activer la traduction dans les includes

**Fichier: `/var/www/itflow/includes/inc_all.php`**

Ajouter après les includes existants:
```php
// Traduction française
require_once "lang/fr/itflow_integration.php";
```

**Fichier: `/var/www/itflow/client/includes/check_login.php`**

Ajouter après les includes:
```php
// Traduction française  
require_once "../../lang/fr/itflow_integration.php";
```

### Étape 3: Traduire les strings (exemples)

**Fichier: `/var/www/itflow/client/index.php`**

```php
// Avant:
<h1>Dashboard</h1>
<a href="invoices.php">Invoices</a>

// Après:
<h1><?php echo __('Dashboard'); ?></h1>
<a href="invoices.php"><?php echo __('Invoices'); ?></a>
```

---

## 📋 LISTE DES STRINGS À TRADUIRE

### Portail Client (priorité haute)

| String originale | Appel de traduction |
|-----------------|---------------------|
| Dashboard | `__('Dashboard')` |
| Invoices | `__('Invoices')` |
| Quotes | `__('Quotes')` |
| Tickets | `__('Tickets')` |
| Documents | `__('Documents')` |
| Contacts | `__('Contacts')` |
| Assets | `__('Assets')` |
| Profile | `__('Profile')` |
| Logout | `__('Logout')` |

### Factures/PDF

| String originale | Appel de traduction |
|-----------------|---------------------|
| Invoice | `__('Invoice')` |
| Invoice Number | `__('Invoice Number')` |
| Due Date | `__('Due Date')` |
| Total | `__('Total')` |
| Bill To | `__('Bill To')` |
| Thank you for your business | `__('Thank you for your business')` |

---

## 🛠️ POUR LES PDF (Factures/Devis)

**Fichier: `/var/www/itflow/agent/post/invoice.php`**

```php
// Ajouter au début:
require_once __DIR__ . '/../lang/fr/itflow_fr.php';

// Avant de générer le PDF:
$html = $this->generateInvoiceHTML($invoice);

// Traduire TOUT le HTML:
$html = translate_html($html);

// Générer le PDF:
$pdf->html($html);
$pdf->Output();
```

---

## 🧪 TESTS

1. **Portail Client:**
   - Se connecter
   - Vérifier les menus traduits
   - Naviguer dans Factures, Tickets, Documents

2. **PDF Facture:**
   - Ouvrir une facture
   - Télécharger le PDF
   - Vérifier que "Invoice" → "Facture"

3. **Sélecteur de langue:**
   - Aller dans Admin → Settings → Localization
   - Choisir "French (France)"
   - Vérifier que le format devise change (CHF)

---

## 📊 STRINGS TRADUITES (80+)

```
Home → Accueil
Dashboard → Tableau de Bord
Invoices → Factures
Quotes → Devis
Tickets → Tickets
Documents → Documents
Contacts → Contacts
Assets → Assets
Profile → Profil
Logout → Déconnexion
Invoice → Facture
Invoice Number → Numéro de Facture
Invoice Date → Date de Facture
Due Date → Date d'Échéance
Amount → Montant
Subtotal → Sous-Total
Tax → Taxe
Total → Total
Paid → Payé
Unpaid → Impayé
Balance Due → Solde Dû
Pay Invoice → Payer la Facture
Download PDF → Télécharger PDF
Item → Article
Description → Description
Quantity → Quantité
Price → Prix
Notes → Notes
Bill To → Facturer à
From → De
To → À
Page → Page
of → sur
Thank you for your business → Merci de votre confiance
Quote → Devis
Quote Number → Numéro de Devis
Valid Until → Valide Jusqu'au
Accept Quote → Accepter le Devis
Decline Quote → Refuser le Devis
Login → Connexion
Email Address → Adresse Email
Password → Mot de passe
Sign In → Se connecter
Search → Rechercher
Save → Enregistrer
Cancel → Annuler
Delete → Supprimer
Edit → Modifier
Add → Ajouter
Close → Fermer
Success → Succès
Error → Erreur
Warning → Avertissement
```

---

## ⚠️ MAINTENANCE

**À chaque mise à jour ITFlow:**

1. Sauvegarder `/var/www/itflow/lang/fr/`
2. Ré-appliquer les modifications dans les includes
3. Ré-appliquer les modifications dans les fichiers sources
4. Tester les PDF et l'interface

**Pour ajouter des traductions:**

1. Modifier `/var/www/itflow/lang/fr/itflow_fr.po`
2. Régénérer `itflow_fr.php` (ou le faire manuellement)
3. Redéployer

---

**Dernière mise à jour:** 2026-10-23  
**Fichiers créés:** 3 fichiers de traduction  
**Strings traduites:** 80+
