# Fix: Table 'products' Manquante

## Problème

Lors de l'accès à la page "Produits et Stock", l'erreur suivante apparaissait :

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'gestion_comptable.products' doesn't exist
```

Les produits ne se chargeaient pas dans la partie inférieure de la page.

## Cause

Les tables du module "Gestion des Stocks" n'avaient pas été installées dans la base de données. Le fichier SQL d'installation (`install_inventory.sql`) existait mais n'avait jamais été exécuté.

## Solution Appliquée

### 1. Installation des Tables

**Script créé :** `install_inventory_tables.php`

Exécution :
```bash
php install_inventory_tables.php
```

**Tables créées :**

| Table | Description | Lignes |
|-------|-------------|--------|
| `products` | Catalogue produits/services | 0 |
| `stock_movements` | Historique mouvements de stock | 0 |
| `product_suppliers` | Relation produits-fournisseurs | 0 |
| `stock_alerts` | Alertes de stock (bas/rupture) | 0 |

**Vues créées :**

| Vue | Description |
|-----|-------------|
| `v_stock_value` | Valeur du stock par produit |
| `v_low_stock_products` | Produits en rupture ou stock faible |
| `v_stock_movements_detailed` | Historique mouvements avec détails |

**Triggers créés :**

| Trigger | Description |
|---------|-------------|
| `trg_update_stock_after_movement` | Mise à jour automatique du stock après mouvement |

### 2. Structure de la Table `products`

```sql
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Code article unique',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('product','service','bundle') NOT NULL DEFAULT 'product',
  `category_id` int(11) DEFAULT NULL,

  -- Prix
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70,
  `currency` varchar(3) NOT NULL DEFAULT 'CHF',

  -- Stock
  `stock_quantity` decimal(10,2) DEFAULT 0.00,
  `stock_min` decimal(10,2) DEFAULT 0.00,
  `stock_max` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pce',

  -- Options
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_sellable` tinyint(1) NOT NULL DEFAULT 1,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT 1,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,

  -- Informations complémentaires
  `supplier_id` int(11) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,

  -- Métadonnées
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_code` (`company_id`, `code`),
  KEY `idx_products_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. Fonctionnalités du Module Stock

#### Gestion des Produits
- ✅ Catalogue produits/services/packs
- ✅ Prix d'achat et de vente HT
- ✅ TVA configurable (défaut 7.7% Suisse)
- ✅ Multi-devises (défaut CHF)
- ✅ Codes-barres
- ✅ Images produits
- ✅ Catégorisation

#### Gestion du Stock
- ✅ Suivi quantités en temps réel
- ✅ Seuils d'alerte (min/max)
- ✅ Unités configurables (pièce, kg, m, l, etc.)
- ✅ Historique complet des mouvements
- ✅ Types de mouvements : entrée, sortie, ajustement, transfert, retour

#### Alertes Automatiques
- ✅ Stock bas (quantité ≤ seuil min)
- ✅ Rupture de stock (quantité = 0)
- ✅ Surstock (quantité > max)
- ✅ Déclenchement automatique via triggers

#### Relations
- ✅ Plusieurs fournisseurs par produit
- ✅ Prix d'achat par fournisseur
- ✅ Fournisseur préféré
- ✅ Délais de livraison
- ✅ Quantités minimales de commande

### 4. Trigger Automatique

Le trigger `trg_update_stock_after_movement` se déclenche automatiquement après chaque insertion dans `stock_movements` :

**Actions :**
1. Met à jour `stock_quantity` dans `products` selon le type de mouvement :
   - **IN / RETURN** : `stock_quantity + quantity`
   - **OUT / TRANSFER** : `stock_quantity - quantity`
   - **ADJUSTMENT** : `stock_quantity = quantity` (correction absolue)

2. Crée automatiquement une alerte dans `stock_alerts` si :
   - Stock ≤ 0 → Alerte "out_of_stock"
   - Stock ≤ stock_min → Alerte "low_stock"

### 5. Vérification

Pour vérifier l'installation :

```bash
php check_tables.php
```

Pour voir la structure d'une table :
```sql
DESCRIBE products;
```

Pour compter les produits :
```sql
SELECT COUNT(*) FROM products WHERE company_id = 1;
```

## Scripts Créés

| Script | Description |
|--------|-------------|
| `install_inventory_tables.php` | Installation automatique des tables |
| `check_tables.php` | Vérification des tables dans la BDD |

## Fichiers Source

| Fichier | Description |
|---------|-------------|
| `install_inventory.sql` | Définition SQL complète du module |
| `models/Product.php` | Modèle PHP pour les produits |
| `models/StockMovement.php` | Modèle PHP pour les mouvements |
| `views/products.php` | Interface de gestion produits |
| `assets/ajax/products.php` | API AJAX pour les produits |
| `assets/js/products.js` | JavaScript côté client |

## Utilisation

### Créer un Produit

```php
require_once 'models/Product.php';

$product = new Product($db);
$product->company_id = $_SESSION['company_id'];
$product->code = 'PROD-001';
$product->name = 'Ordinateur portable';
$product->type = 'product';
$product->purchase_price = 800.00;
$product->selling_price = 1200.00;
$product->stock_quantity = 10;
$product->stock_min = 5;
$product->unit = 'pce';

if ($product->create()) {
    echo "Produit créé avec succès";
}
```

### Ajouter un Mouvement de Stock

```php
require_once 'models/StockMovement.php';

$movement = new StockMovement($db);
$movement->company_id = $_SESSION['company_id'];
$movement->product_id = 1;
$movement->type = 'in'; // Entrée
$movement->quantity = 20;
$movement->unit_cost = 800.00;
$movement->total_cost = 16000.00;
$movement->reason = 'Achat fournisseur';
$movement->created_by = $_SESSION['user_id'];

if ($movement->create()) {
    echo "Mouvement enregistré";
    // Le stock est mis à jour automatiquement par le trigger
}
```

### Consulter les Produits en Stock Faible

```sql
SELECT * FROM v_low_stock_products
WHERE company_id = 1 AND stock_status = 'low_stock'
ORDER BY stock_quantity ASC;
```

## Résolution de Problèmes

### Problème : "Table already exists"

Si vous réexécutez le script et que les tables existent déjà :
```
✓ Tables déjà installées (pas de problème)
```

### Problème : Trigger ne fonctionne pas

Vérifier que le trigger existe :
```sql
SHOW TRIGGERS LIKE 'stock_movements';
```

Tester manuellement :
```sql
INSERT INTO stock_movements
(company_id, product_id, type, quantity, created_by)
VALUES (1, 1, 'in', 10, 1);

-- Vérifier que stock_quantity a augmenté
SELECT stock_quantity FROM products WHERE id = 1;
```

### Problème : Produits ne s'affichent toujours pas

1. Vérifier que les tables existent :
   ```bash
   php check_tables.php
   ```

2. Vérifier les permissions :
   ```sql
   SHOW GRANTS FOR CURRENT_USER;
   ```

3. Vérifier le modèle PHP :
   ```bash
   ls -la models/Product.php
   ```

4. Vérifier les logs Apache/PHP pour d'autres erreurs

## Migration pour Autres Environnements

Pour installer sur un autre serveur :

```bash
mysql -u root -p gestion_comptable < install_inventory.sql
```

Ou via PHP :
```bash
php install_inventory_tables.php
```

## Compatibilité

- ✅ MySQL 5.7+
- ✅ MariaDB 10.2+
- ✅ PHP 7.4+
- ✅ Encodage UTF-8

## Limitations

- Les quantités acceptent jusqu'à 2 décimales
- Les prix acceptent jusqu'à 2 décimales
- Le code produit est limité à 50 caractères
- Le nom du produit est limité à 255 caractères

## Prochaines Étapes

1. ✅ Tables installées
2. ⏳ Ajouter des produits de test (optionnel)
3. ⏳ Configurer les catégories de produits
4. ⏳ Créer des fournisseurs dans les contacts
5. ⏳ Tester les mouvements de stock

---

**Résolution :** ✅ Complète
**Test :** ✅ Tables créées et vérifiées
**Documentation :** ✅ À jour
**Date :** 2025-01-13
