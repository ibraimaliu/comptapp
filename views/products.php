<?php
/**
 * Vue: Gestion des Produits/Services
 * Description: Catalogue produits avec gestion de stock
 */

// Vérifier l'accès
if (!isset($_SESSION['company_id'])) {
    redirect('index.php?page=login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits</title>
    <link rel="stylesheet" href="assets/css/products.css">
</head>
<body>
    <div class="products-container">
        <!-- Header -->
        <div class="page-header">
            <h1><i class="fa-solid fa-box"></i> Gestion des Produits & Services</h1>
            <div class="header-actions">
                <button class="btn-primary" onclick="openCreateModal()">
                    <i class="fa-solid fa-plus"></i> Nouveau Produit
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="statTotalProducts">0</div>
                    <div class="stat-label">Total Produits</div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="statLowStock">0</div>
                    <div class="stat-label">Stock Bas</div>
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fa-solid fa-times-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="statOutOfStock">0</div>
                    <div class="stat-label">Rupture</div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-icon"><i class="fa-solid fa-euro-sign"></i></div>
                <div class="stat-content">
                    <div class="stat-value" id="statStockValue">CHF 0</div>
                    <div class="stat-label">Valeur Stock</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Rechercher</label>
                    <input type="text" id="filterSearch" placeholder="Code, nom, code-barres...">
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select id="filterType">
                        <option value="">Tous</option>
                        <option value="product">Produits</option>
                        <option value="service">Services</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Catégorie</label>
                    <select id="filterCategory">
                        <option value="">Toutes</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Statut</label>
                    <select id="filterStatus">
                        <option value="">Tous</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Inactifs</option>
                        <option value="low_stock">Stock bas</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn-filter" onclick="applyFilters()">
                        <i class="fa-solid fa-filter"></i> Filtrer
                    </button>
                    <button class="btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="products-list" id="productsList">
            <div class="loading"><i class="fa-solid fa-spinner"></i> Chargement...</div>
        </div>

        <!-- Create/Edit Modal -->
        <div id="productModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modalTitle">Nouveau Produit</h2>
                    <button class="close-btn" onclick="closeModal()">&times;</button>
                </div>
                <form id="productForm" onsubmit="saveProduct(event)">
                    <div class="form-tabs">
                        <button type="button" class="tab-btn active" onclick="switchTab(0)">Général</button>
                        <button type="button" class="tab-btn" onclick="switchTab(1)">Prix & Stock</button>
                        <button type="button" class="tab-btn" onclick="switchTab(2)">Détails</button>
                    </div>

                    <input type="hidden" id="productId" name="id">

                    <!-- Tab 1: Général -->
                    <div class="tab-content active">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Code Produit *</label>
                                <input type="text" name="code" required>
                                <button type="button" class="btn-secondary" onclick="generateCode()">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i> Générer
                                </button>
                            </div>
                            <div class="form-group">
                                <label>Type *</label>
                                <select name="type" required onchange="toggleStockFields(this.value)">
                                    <option value="product">Produit</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="name" required>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Catégorie</label>
                            <select name="category_id" id="formCategorySelect">
                                <option value="">Aucune</option>
                            </select>
                        </div>
                    </div>

                    <!-- Tab 2: Prix & Stock -->
                    <div class="tab-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Prix Achat (HT)</label>
                                <input type="number" name="purchase_price" step="0.01" value="0.00">
                            </div>
                            <div class="form-group">
                                <label>Prix Vente (HT) *</label>
                                <input type="number" name="selling_price" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Taux TVA (%)</label>
                                <input type="number" name="tva_rate" step="0.01" value="7.70">
                            </div>
                        </div>

                        <div id="stockFields">
                            <h4>Gestion du Stock</h4>
                            <div class="form-check">
                                <input type="checkbox" name="track_stock" id="trackStock" checked>
                                <label for="trackStock">Suivre le stock</label>
                            </div>

                            <div class="form-grid" id="stockInputs">
                                <div class="form-group">
                                    <label>Stock Initial</label>
                                    <input type="number" name="stock_quantity" step="0.01" value="0">
                                </div>
                                <div class="form-group">
                                    <label>Stock Minimum</label>
                                    <input type="number" name="stock_min" step="0.01" value="5">
                                </div>
                                <div class="form-group">
                                    <label>Unité</label>
                                    <select name="unit">
                                        <option value="pce">Pièce</option>
                                        <option value="kg">Kilogramme</option>
                                        <option value="l">Litre</option>
                                        <option value="m">Mètre</option>
                                        <option value="m2">Mètre carré</option>
                                        <option value="heure">Heure</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Détails -->
                    <div class="tab-content">
                        <div class="form-group">
                            <label>Fournisseur Principal</label>
                            <select name="supplier_id" id="formSupplierSelect">
                                <option value="">Aucun</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Code-barres</label>
                            <input type="text" name="barcode">
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="4"></textarea>
                        </div>

                        <div class="form-checks">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="isActive" checked>
                                <label for="isActive">Actif</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_sellable" id="isSellable" checked>
                                <label for="isSellable">Vendable</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="is_purchasable" id="isPurchasable" checked>
                                <label for="isPurchasable">Achetable</label>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Annuler</button>
                        <button type="submit" class="btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Stock Movement Modal -->
        <div id="movementModal" class="modal">
            <div class="modal-content modal-sm">
                <div class="modal-header">
                    <h2>Mouvement de Stock</h2>
                    <button class="close-btn" onclick="closeMovementModal()">&times;</button>
                </div>
                <form id="movementForm" onsubmit="saveMovement(event)">
                    <input type="hidden" id="movementProductId">
                    <input type="hidden" id="movementProductName">

                    <div class="form-group">
                        <label>Type de Mouvement *</label>
                        <select name="type" required>
                            <option value="in">Entrée (+)</option>
                            <option value="out">Sortie (-)</option>
                            <option value="adjustment">Ajustement</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantité *</label>
                        <input type="number" name="quantity" step="0.01" required>
                    </div>

                    <div class="form-group">
                        <label>Coût Unitaire</label>
                        <input type="number" name="unit_cost" step="0.01" value="0">
                    </div>

                    <div class="form-group">
                        <label>Raison *</label>
                        <input type="text" name="reason" required placeholder="Ex: Réception commande, Vente, Inventaire...">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3"></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeMovementModal()">Annuler</button>
                        <button type="submit" class="btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/products.js"></script>
</body>
</html>
