
## 📁 PORTAIL CLIENT (26 fichiers PHP)

### Pages Principales
- client/index.php (289 lignes) - Dashboard
- client/tickets.php (117 lignes) - Liste tickets
- client/ticket.php (377 lignes) - Vue ticket
- client/ticket_add.php (112 lignes) - Créer ticket

### Facturation
- client/invoices.php (103 lignes) - Factures
- client/unpaid_invoices.php (211 lignes) - Impayées
- client/recurring_invoices.php (118 lignes) - Récurrentes
- client/statement.php (162 lignes) - Relevé
- client/quotes.php (93 lignes) - Devis

### Documents & Assets
- client/documents.php (168 lignes)
- client/document.php (200 lignes)
- client/assets.php (97 lignes)
- client/certificates.php (62 lignes)
- client/domains.php (56 lignes)

### Contacts
- client/contacts.php (81 lignes)
- client/contact_add.php (85 lignes)
- client/contact_edit.php (118 lignes)

### Profil & Sécurité
- client/profile.php (524 lignes)
- client/activity.php (126 lignes)
- client/login_reset.php (291 lignes)
- client/login_microsoft.php (192 lignes)

### Fichiers Communs
- client/includes/header.php (208 lignes)
- client/includes/footer.php (97 lignes)
- client/includes/check_login.php (95 lignes)

## 📧 EMAILS AUX CLIENTS

### Templates d'Emails (dans le code PHP)
1. **Nouveau Ticket** - client/post.php
   - Sujet: "ITFlow - New Ticket - {client}: {subject}"
   - Contenu: Confirmation de création avec lien vers le ticket

2. **Mise à Jour Ticket** - agent/post/ticket.php
   - Sujet: "{ticket_subject}<br>Status: {status}"
   - Contenu: Notification de mise à jour avec lien portal

3. **Factures** - Probablement dans post.php ou cron/
   - Facture créée/modifiée/payée
   - Rappels de paiement

4. **Devis** - Notification de nouveau/devis modifié

### Fichiers à Vérifier pour Emails
- client/post.php (68,975 bytes - 1542 lignes)
- agent/post/ticket.php
- cron/mail_queue.php (503 lignes)
- functions/app.php (33,020 bytes)

## 📊 ESTIMATION

**Total lignes à traduire:** ~5,000+ lignes
**Fichiers:** 26 pages client + 4-5 fichiers email
**Temps estimé:** 2-3 heures de traduction
