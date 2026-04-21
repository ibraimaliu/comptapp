/**
 * Script: Gestion des Produits
 * Description: CRUD produits et mouvements de stock
 * Version: 1.0
 */

let currentTab = 0;

// Charger les données au démarrage
document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    loadStatistics();
    loadCategories();
    loadSuppliers();

    // Event listeners
    document.getElementById('filterSearch').addEventListener('input', debounce(applyFilters, 500));
    document.getElementById('trackStock').addEventListener('change', function() {
        document.getElementById('stockInputs').style.display = this.checked ? 'grid' : 'none';
    });
});

/**
 * Charger les produits
 */
function loadProducts() {
    const filters = {
        search: document.getElementById('filterSearch').value,
        type: document.getElementById('filterType').value,
        category_id: document.getElementById('filterCategory').value,
        is_active: document.getElementById('filterStatus').value === 'active' ? 1 : '',
        low_stock: document.getElementById('filterStatus').value === 'low_stock'
    };

    const params = new URLSearchParams();
    if (filters.search) params.append('search', filters.search);
    if (filters.type) params.append('type', filters.type);
    if (filters.category_id) params.append('category_id', filters.category_id);
    if (filters.is_active) params.append('is_active', filters.is_active);
    if (filters.low_stock) params.append('low_stock', 'true');

    fetch(`assets/ajax/products.php?action=list&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayProducts(data.products);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Erreur de chargement', 'error');
        });
}

/**
 * Afficher les produits
 */
function displayProducts(products) {
    const container = document.getElementById('productsList');

    if (!products || products.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-box-open" style="font-size: 3em; color: #ddd;"></i>
                <p>Aucun produit trouvé</p>
            </div>
        `;
        return;
    }

    let html = '<div class="products-table">';
    html += `
        <div class="table-header">
            <div>Code</div>
            <div>Nom</div>
            <div>Type</div>
            <div>Prix Vente</div>
            <div>Stock</div>
            <div>Statut</div>
            <div>Actions</div>
        </div>
    `;

    products.forEach(product => {
        const stockClass = product.track_stock == 1
            ? (product.stock_quantity <= 0 ? 'badge-danger' : product.stock_quantity <= product.stock_min ? 'badge-warning' : 'badge-success')
            : '';

        const stockBadge = product.track_stock == 1
            ? `<span class="badge ${stockClass}">${parseFloat(product.stock_quantity).toFixed(2)} ${product.unit}</span>`
            : '<span class="badge badge-secondary">N/A</span>';

        const typeIcons = {
            product: '<i class="fa-solid fa-box"></i>',
            service: '<i class="fa-solid fa-handshake"></i>',
            bundle: '<i class="fa-solid fa-boxes-stacked"></i>'
        };

        html += `
            <div class="table-row">
                <div><strong>${escapeHtml(product.code)}</strong></div>
                <div>
                    <div>${escapeHtml(product.name)}</div>
                    ${product.category_name ? `<small class="text-muted">${escapeHtml(product.category_name)}</small>` : ''}
                </div>
                <div>${typeIcons[product.type] || ''} ${product.type}</div>
                <div><strong>CHF ${parseFloat(product.selling_price).toFixed(2)}</strong></div>
                <div>${stockBadge}</div>
                <div>
                    <span class="badge ${product.is_active == 1 ? 'badge-success' : 'badge-secondary'}">
                        ${product.is_active == 1 ? 'Actif' : 'Inactif'}
                    </span>
                </div>
                <div class="action-buttons">
                    ${product.track_stock == 1 ? `
                        <button class="btn-action" onclick="openMovementModal(${product.id}, '${escapeHtml(product.name)}')" title="Mouvement de stock">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </button>
                    ` : ''}
                    <button class="btn-action" onclick="editProduct(${product.id})" title="Modifier">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <button class="btn-action" onclick="deleteProduct(${product.id})" title="Supprimer">
                        <i class="fa-solid fa-trash" style="color: #dc3545;"></i>
                    </button>
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Charger les statistiques
 */
function loadStatistics() {
    fetch('assets/ajax/products.php?action=statistics')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.statistics) {
                const stats = data.statistics;
                document.getElementById('statTotalProducts').textContent = stats.total_products || 0;
                document.getElementById('statLowStock').textContent = stats.low_stock_count || 0;
                document.getElementById('statOutOfStock').textContent = stats.out_of_stock_count || 0;
            }
        });

    fetch('assets/ajax/products.php?action=stock_value')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.value) {
                document.getElementById('statStockValue').textContent =
                    'CHF ' + parseFloat(data.value.total_cost_value || 0).toFixed(2);
            }
        });
}

/**
 * Charger les catégories
 */
function loadCategories() {
    fetch('assets/ajax/categories.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select1 = document.getElementById('filterCategory');
                const select2 = document.getElementById('formCategorySelect');

                data.categories.forEach(cat => {
                    const option1 = new Option(cat.name, cat.id);
                    const option2 = new Option(cat.name, cat.id);
                    select1.add(option1);
                    select2.add(option2.cloneNode(true));
                });
            }
        });
}

/**
 * Charger les fournisseurs
 */
function loadSuppliers() {
    fetch('assets/ajax/contacts.php?action=list&type=supplier')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('formSupplierSelect');
                data.contacts.forEach(supplier => {
                    select.add(new Option(supplier.name, supplier.id));
                });
            }
        });
}

/**
 * Ouvrir modal création
 */
function openCreateModal() {
    document.getElementById('productModal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Nouveau Produit';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    switchTab(0);
}

/**
 * Fermer modal
 */
function closeModal() {
    document.getElementById('productModal').classList.remove('active');
}

/**
 * Changer d'onglet
 */
function switchTab(index) {
    currentTab = index;

    // Update tabs
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i === index);
    });

    // Update content
    document.querySelectorAll('.tab-content').forEach((content, i) => {
        content.classList.toggle('active', i === index);
    });
}

/**
 * Toggle des champs de stock
 */
function toggleStockFields(type) {
    const stockFields = document.getElementById('stockFields');
    stockFields.style.display = type === 'product' ? 'block' : 'none';
}

/**
 * Générer un code produit
 */
function generateCode() {
    fetch('assets/ajax/products.php?action=generate_code')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.code) {
                document.querySelector('[name="code"]').value = data.code;
            }
        });
}

/**
 * Sauvegarder un produit
 */
function saveProduct(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = {
        action: document.getElementById('productId').value ? 'update' : 'create'
    };

    formData.forEach((value, key) => {
        if (key === 'track_stock' || key === 'is_active' || key === 'is_sellable' || key === 'is_purchasable') {
            data[key] = formData.get(key) ? 1 : 0;
        } else {
            data[key] = value;
        }
    });

    fetch('assets/ajax/products.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produit enregistré', 'success');
            closeModal();
            loadProducts();
            loadStatistics();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Modifier un produit
 */
function editProduct(id) {
    fetch(`assets/ajax/products.php?action=read&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.product) {
                const product = data.product;
                document.getElementById('productModal').classList.add('active');
                document.getElementById('modalTitle').textContent = 'Modifier le Produit';
                document.getElementById('productId').value = product.id;

                // Remplir le formulaire
                document.querySelector('[name="code"]').value = product.code;
                document.querySelector('[name="name"]').value = product.name;
                document.querySelector('[name="description"]').value = product.description || '';
                document.querySelector('[name="type"]').value = product.type;
                document.querySelector('[name="category_id"]').value = product.category_id || '';
                document.querySelector('[name="purchase_price"]').value = product.purchase_price;
                document.querySelector('[name="selling_price"]').value = product.selling_price;
                document.querySelector('[name="tva_rate"]').value = product.tva_rate;
                document.querySelector('[name="stock_quantity"]').value = product.stock_quantity;
                document.querySelector('[name="stock_min"]').value = product.stock_min;
                document.querySelector('[name="unit"]').value = product.unit;
                document.querySelector('[name="supplier_id"]').value = product.supplier_id || '';
                document.querySelector('[name="barcode"]').value = product.barcode || '';
                document.querySelector('[name="notes"]').value = product.notes || '';
                document.getElementById('trackStock').checked = product.track_stock == 1;
                document.getElementById('isActive').checked = product.is_active == 1;
                document.getElementById('isSellable').checked = product.is_sellable == 1;
                document.getElementById('isPurchasable').checked = product.is_purchasable == 1;

                toggleStockFields(product.type);
                switchTab(0);
            }
        });
}

/**
 * Supprimer un produit
 */
function deleteProduct(id) {
    if (!confirm('Supprimer ce produit ? Cette action est irréversible.')) return;

    fetch('assets/ajax/products.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'delete', id: id})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produit supprimé', 'success');
            loadProducts();
            loadStatistics();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Ouvrir modal mouvement de stock
 */
function openMovementModal(productId, productName) {
    document.getElementById('movementModal').classList.add('active');
    document.getElementById('movementProductId').value = productId;
    document.getElementById('movementProductName').value = productName;
    document.getElementById('movementForm').reset();
}

/**
 * Fermer modal mouvement
 */
function closeMovementModal() {
    document.getElementById('movementModal').classList.remove('active');
}

/**
 * Sauvegarder un mouvement
 */
function saveMovement(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = {
        action: 'movement',
        product_id: document.getElementById('movementProductId').value,
        type: formData.get('type'),
        quantity: parseFloat(formData.get('quantity')),
        unit_cost: parseFloat(formData.get('unit_cost') || 0),
        reason: formData.get('reason'),
        notes: formData.get('notes')
    };

    fetch('assets/ajax/products.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Mouvement enregistré', 'success');
            closeMovementModal();
            loadProducts();
            loadStatistics();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Appliquer les filtres
 */
function applyFilters() {
    loadProducts();
}

/**
 * Réinitialiser les filtres
 */
function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterStatus').value = '';
    loadProducts();
}

/**
 * Utilitaires
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed; top: 20px; right: 20px;
        background: ${colors[type]}; color: white;
        padding: 15px 20px; border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000; max-width: 400px;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => notification.remove(), 3000);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
