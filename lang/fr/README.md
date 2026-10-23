# Traduction Française - ITFlow

## 📁 Structure

```
lang/fr/
├── itflow_fr.php           # Traductions PHP (utilisé en production)
├── itflow_integration.php  # Configuration et helpers
└── README.md               # Cette documentation
```

## 🎯 Utilisation

### Dans les fichiers PHP

```php
<?php
// Charger les traductions
require_once __DIR__ . '/../../lang/fr/itflow_integration.php';

// Utiliser une string traduite
echo __('Dashboard');  // Affiche: Tableau de Bord
```

### Pour les PDF

```php
<?php
// Traduire le HTML avant génération PDF
$html = '<h1>Invoice</h1>';
$html = translate_html($html);  // <h1>Facture</h1>
```

## 📊 Statistiques

- **Strings traduites:** 100+
- **Date de création:** 2026-10-23
- **Auteur:** Hermes Agent (Jilian) - Proxycore

## 🔄 Ajouter une nouvelle string

1. Ajouter dans `$itflow_translations` dans `itflow_fr.php`
2. Utiliser `__('String English')` dans le fichier source
3. Commit et push

## 🔗 Liens

- [[02-Translation-System]] - Documentation technique complète
- [[07-Workflow-Complete]] - Workflow de traduction
