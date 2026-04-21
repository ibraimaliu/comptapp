-- ============================================
-- Installation: Module Gestion des Stocks
-- Version: 1.0
-- Description: Gestion inventaire, produits et mouvements
-- ============================================

USE gestion_comptable;

-- ============================================
-- Table: products
-- Description: Catalogue produits/services
-- ============================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL COMMENT 'Code article unique',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('product','service','bundle') NOT NULL DEFAULT 'product',
  `category_id` int(11) DEFAULT NULL,

  -- Prix
  `purchase_price` decimal(10,2) DEFAULT 0.00 COMMENT 'Prix achat HT',
  `selling_price` decimal(10,2) NOT NULL COMMENT 'Prix vente HT',
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70,
  `currency` varchar(3) NOT NULL DEFAULT 'CHF',

  -- Stock
  `stock_quantity` decimal(10,2) DEFAULT 0.00,
  `stock_min` decimal(10,2) DEFAULT 0.00 COMMENT 'Seuil alerte',
  `stock_max` decimal(10,2) DEFAULT NULL COMMENT 'Stock maximum',
  `unit` varchar(20) DEFAULT 'pce' COMMENT 'Unité: pce, kg, m, l, etc.',

  -- Options
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_sellable` tinyint(1) NOT NULL DEFAULT 1,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT 1,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,

  -- Informations complémentaires
  `supplier_id` int(11) DEFAULT NULL COMMENT 'Fournisseur principal',
  `barcode` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,

  -- Métadonnées
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_code` (`company_id`, `code`),
  KEY `idx_products_company` (`company_id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_supplier` (`supplier_id`),
  KEY `idx_products_barcode` (`barcode`),

  CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: stock_movements
-- Description: Historique des mouvements de stock
-- ============================================
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('in','out','adjustment','transfer','return') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,

  -- Référence
  `reference_type` enum('purchase','sale','invoice','supplier_invoice','manual') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID de la transaction source',

  -- Détails
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_company` (`company_id`),
  KEY `idx_stock_movements_product` (`product_id`),
  KEY `idx_stock_movements_date` (`movement_date`),
  KEY `idx_stock_movements_type` (`type`),

  CONSTRAINT `fk_stock_movements_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: product_suppliers
-- Description: Relation produits-fournisseurs (plusieurs fournisseurs par produit)
-- ============================================
CREATE TABLE IF NOT EXISTS `product_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_code` varchar(50) DEFAULT NULL COMMENT 'Référence fournisseur',
  `purchase_price` decimal(10,2) NOT NULL,
  `min_order_qty` decimal(10,2) DEFAULT 1.00,
  `delivery_time_days` int(11) DEFAULT NULL,
  `is_preferred` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_supplier` (`product_id`, `supplier_id`),
  KEY `idx_product_suppliers_product` (`product_id`),
  KEY `idx_product_suppliers_supplier` (`supplier_id`),

  CONSTRAINT `fk_product_suppliers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_suppliers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: stock_alerts
-- Description: Alertes de stock (bas, élevé, rupture)
-- ============================================
CREATE TABLE IF NOT EXISTS `stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock','expiring') NOT NULL,
  `alert_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quantity` decimal(10,2) NOT NULL,
  `threshold` decimal(10,2) DEFAULT NULL,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,

  PRIMARY KEY (`id`),
  KEY `idx_stock_alerts_company` (`company_id`),
  KEY `idx_stock_alerts_product` (`product_id`),
  KEY `idx_stock_alerts_status` (`status`),
  KEY `idx_stock_alerts_date` (`alert_date`),

  CONSTRAINT `fk_stock_alerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_alerts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_alerts_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vue: Valeur du stock par produit
-- ============================================
CREATE OR REPLACE VIEW v_stock_value AS
SELECT
  p.id AS product_id,
  p.company_id,
  p.code,
  p.name,
  p.stock_quantity,
  p.purchase_price,
  p.selling_price,
  (p.stock_quantity * p.purchase_price) AS stock_value_cost,
  (p.stock_quantity * p.selling_price) AS stock_value_selling,
  p.unit,
  p.category_id,
  c.name AS category_name
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE p.track_stock = 1 AND p.is_active = 1;

-- ============================================
-- Vue: Produits en rupture ou stock faible
-- ============================================
CREATE OR REPLACE VIEW v_low_stock_products AS
SELECT
  p.id AS product_id,
  p.company_id,
  p.code,
  p.name,
  p.stock_quantity,
  p.stock_min,
  p.unit,
  CASE
    WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
    WHEN p.stock_quantity <= p.stock_min THEN 'low_stock'
    ELSE 'ok'
  END AS stock_status,
  s.name AS supplier_name,
  s.email AS supplier_email
FROM products p
LEFT JOIN contacts s ON p.supplier_id = s.id
WHERE p.track_stock = 1
  AND p.is_active = 1
  AND p.stock_quantity <= p.stock_min
ORDER BY p.stock_quantity ASC;

-- ============================================
-- Vue: Historique mouvements avec détails
-- ============================================
CREATE OR REPLACE VIEW v_stock_movements_detailed AS
SELECT
  sm.id,
  sm.company_id,
  sm.product_id,
  p.code AS product_code,
  p.name AS product_name,
  sm.movement_date,
  sm.type,
  sm.quantity,
  sm.unit_cost,
  sm.total_cost,
  sm.reference_type,
  sm.reference_id,
  sm.reason,
  u.username AS created_by_name,
  sm.created_at
FROM stock_movements sm
INNER JOIN products p ON sm.product_id = p.id
LEFT JOIN users u ON sm.created_by = u.id
ORDER BY sm.movement_date DESC;

-- ============================================
-- Trigger: Mise à jour stock après mouvement
-- ============================================
DELIMITER //

CREATE TRIGGER trg_update_stock_after_movement
AFTER INSERT ON stock_movements
FOR EACH ROW
BEGIN
  DECLARE current_stock DECIMAL(10,2);

  -- Récupérer le stock actuel
  SELECT stock_quantity INTO current_stock
  FROM products
  WHERE id = NEW.product_id;

  -- Mettre à jour le stock selon le type de mouvement
  IF NEW.type IN ('in', 'return') THEN
    UPDATE products
    SET stock_quantity = stock_quantity + NEW.quantity
    WHERE id = NEW.product_id;
  ELSEIF NEW.type IN ('out', 'transfer') THEN
    UPDATE products
    SET stock_quantity = stock_quantity - NEW.quantity
    WHERE id = NEW.product_id;
  ELSEIF NEW.type = 'adjustment' THEN
    UPDATE products
    SET stock_quantity = NEW.quantity
    WHERE id = NEW.product_id;
  END IF;

  -- Vérifier si une alerte doit être créée
  SELECT stock_quantity INTO current_stock
  FROM products
  WHERE id = NEW.product_id;

  -- Alerte stock bas
  IF current_stock <= (SELECT stock_min FROM products WHERE id = NEW.product_id) THEN
    INSERT INTO stock_alerts (company_id, product_id, alert_type, quantity, threshold, status)
    VALUES (
      NEW.company_id,
      NEW.product_id,
      CASE WHEN current_stock <= 0 THEN 'out_of_stock' ELSE 'low_stock' END,
      current_stock,
      (SELECT stock_min FROM products WHERE id = NEW.product_id),
      'active'
    )
    ON DUPLICATE KEY UPDATE
      alert_date = CURRENT_TIMESTAMP,
      quantity = current_stock,
      status = 'active';
  END IF;
END//

DELIMITER ;

-- ============================================
-- Indexes pour performance
-- ============================================
CREATE INDEX IF NOT EXISTS idx_products_stock_quantity ON products(stock_quantity);
CREATE INDEX IF NOT EXISTS idx_products_active ON products(is_active);
CREATE INDEX IF NOT EXISTS idx_stock_movements_reference ON stock_movements(reference_type, reference_id);

-- ============================================
-- Données de test (optionnel)
-- ============================================
/*
-- Exemples de produits
INSERT INTO products (company_id, code, name, type, purchase_price, selling_price, stock_quantity, stock_min, unit, created_at)
VALUES
  (1, 'PROD-001', 'Ordinateur portable HP', 'product', 800.00, 1200.00, 15, 5, 'pce', NOW()),
  (1, 'PROD-002', 'Souris sans fil', 'product', 15.00, 35.00, 50, 10, 'pce', NOW()),
  (1, 'SERV-001', 'Consultation informatique', 'service', 0.00, 120.00, 0, 0, 'heure', NOW()),
  (1, 'PROD-003', 'Câble HDMI', 'product', 5.00, 15.00, 8, 15, 'pce', NOW());
*/

-- ============================================
-- Fin de l'installation
-- ============================================
SELECT 'Installation module Gestion des Stocks terminée avec succès!' AS message;
