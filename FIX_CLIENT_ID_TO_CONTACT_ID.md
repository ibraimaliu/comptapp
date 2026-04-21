# ✅ Correction de l'Erreur "Unknown column 'client_id'"

## 🐛 Problème Rencontré

**Erreur:**
```
Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'i.client_id' in 'ON'
```

Se produit lors de l'affichage de la liste des factures dans la page Comptabilité.

## 🔍 Cause du Problème

Les tables `invoices` et `quotes` utilisent la colonne `contact_id` dans la base de données, mais les modèles PHP utilisaient `client_id`.

**Nom correct:**
- ✅ `contact_id` (dans la base de données)
- ❌ `client_id` (utilisé dans les modèles)

---

## ✅ Corrections Appliquées

### Fichiers Modifiés

**1. models/Invoice.php**
- Propriété renommée: `public $client_id;` → `public $contact_id;`
- Requêtes SQL corrigées:
  - `LEFT JOIN contacts c ON i.client_id = c.id` → `ON i.contact_id = c.id`
  - `client_id = :client_id` → `contact_id = :contact_id`
- Toutes les références `$this->client_id` → `$this->contact_id`
- Toutes les références `$row['client_id']` → `$row['contact_id']`
- Bindings corrigés: `:client_id` → `:contact_id`

**2. models/Quote.php**
- Même type de corrections (remplacement global)

---

## 📊 Structure Correcte

**Table invoices:**
- id
- company_id
- invoice_number
- **contact_id** ✅ (pas client_id)
- date
- due_date
- status
- total_amount
- created_at

**Table quotes:**
- id
- company_id
- quote_number
- **contact_id** ✅ (pas client_id)
- date
- valid_until
- status
- total_amount
- created_at

---

## ✅ Test de la Correction

### Via l'Interface

1. Se connecter à l'application
2. Aller dans **Comptabilité**
3. La liste des transactions devrait s'afficher sans erreur ✅
4. Les factures et devis devraient être visibles

### Si vous avez des factures existantes

La liste devrait afficher:
- Numéro de facture
- Nom du client (via LEFT JOIN sur contacts)
- Date
- Montant
- Statut

---

## 📝 Impact sur les APIs

Si vous utilisez les APIs pour créer/modifier des factures ou devis, utilisez maintenant:
- ✅ `contact_id` (nouveau)
- ❌ `client_id` (ancien - ne fonctionne plus)

**Exemple JSON:**
```json
{
  "action": "create",
  "invoice_number": "FACT-2025-001",
  "contact_id": 123,
  "date": "2025-01-21",
  "total_amount": 1000.50
}
```

---

**Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Statut:** ✅ CORRIGÉ
