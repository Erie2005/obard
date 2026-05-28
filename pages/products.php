<?php
$pageTitle = 'Products - Stock Management System';
requireLogin();
$db = getDB();
$shopkeeperId = getShopkeeperId();
$errors = [];
$editProduct = null;

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || strlen($name) < 2) $errors[] = 'Product name is required (min 2 characters)';
        if ($unitPrice < 0) $errors[] = 'Unit price must be a positive number';

        if (empty($errors)) {
            if ($action === 'add') {
                $quantity = max(0, intval($_POST['quantity_in_stock'] ?? 0));
                $stmt = $db->prepare('INSERT INTO product (name, description, category, unit_price, quantity_in_stock, shopkeeper_id) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $description ?: null, $category ?: null, $unitPrice, $quantity, $shopkeeperId]);
                setFlash('success', 'Product "' . $name . '" added successfully!');
            } else {
                $productId = intval($_POST['product_id'] ?? 0);
                $existing = $db->prepare('SELECT id FROM product WHERE id = ? AND shopkeeper_id = ?');
                $existing->execute([$productId, $shopkeeperId]);
                if ($existing->fetch()) {
                    $stmt = $db->prepare('UPDATE product SET name = ?, description = ?, category = ?, unit_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND shopkeeper_id = ?');
                    $stmt->execute([$name, $description ?: null, $category ?: null, $unitPrice, $productId, $shopkeeperId]);
                    setFlash('success', 'Product "' . $name . '" updated successfully!');
                } else {
                    setFlash('danger', 'Product not found');
                }
            }
            header('Location: /index.php?page=products');
            exit;
        }
    } elseif ($action === 'delete') {
        $productId = intval($_POST['product_id'] ?? 0);
        $existing = $db->prepare('SELECT name FROM product WHERE id = ? AND shopkeeper_id = ?');
        $existing->execute([$productId, $shopkeeperId]);
        $prod = $existing->fetch();
        if ($prod) {
            $db->prepare('DELETE FROM product WHERE id = ? AND shopkeeper_id = ?')->execute([$productId, $shopkeeperId]);
            setFlash('success', 'Product "' . $prod['name'] . '" deleted successfully!');
        } else {
            setFlash('danger', 'Product not found');
        }
        header('Location: /index.php?page=products');
        exit;
    }
}

// Check if editing
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare('SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?');
    $stmt->execute([$editId, $shopkeeperId]);
    $editProduct = $stmt->fetch();
}

// Fetch all products
$search = trim($_GET['search'] ?? '');
if ($search) {
    $stmt = $db->prepare('SELECT * FROM product WHERE shopkeeper_id = ? AND (name LIKE ? OR category LIKE ? OR description LIKE ?) ORDER BY updated_at DESC');
    $like = '%' . $search . '%';
    $stmt->execute([$shopkeeperId, $like, $like, $like]);
} else {
    $stmt = $db->prepare('SELECT * FROM product WHERE shopkeeper_id = ? ORDER BY updated_at DESC');
    $stmt->execute([$shopkeeperId]);
}
$products = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 p-6 lg:p-8 pt-16 lg:pt-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Products</h1>
            <p class="text-gray-500 text-sm mt-1">Manage your product inventory</p>
        </div>
        <button onclick="openModal('add-modal')" 
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors text-sm">
            <i class="fas fa-plus"></i> Add Product
        </button>
    </div>

    <?php require __DIR__ . '/../includes/alert.php'; ?>

    <!-- Products Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h3 class="font-semibold text-gray-900">Product List (<?= count($products) ?>)</h3>
            <form method="GET" action="/index.php" class="relative w-full sm:w-72">
                <input type="hidden" name="page" value="products">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search products..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </form>
        </div>

        <?php if (empty($products)): ?>
        <div class="text-center py-16 text-gray-400">
            <i class="fas fa-box-open text-5xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-600 mb-2">No products yet</h3>
            <p class="mb-4 text-sm">Start by adding your first product</p>
            <button onclick="openModal('add-modal')" 
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-sm">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">In Stock</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($products as $i => $p): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 text-sm text-gray-500"><?= $i + 1 ?></td>
                        <td class="px-4 py-3.5">
                            <div class="font-medium text-gray-900 text-sm"><?= e($p['name']) ?></div>
                            <?php if ($p['description']): ?>
                                <div class="text-xs text-gray-400 mt-0.5 truncate max-w-[200px]"><?= e($p['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5 text-sm text-gray-600"><?= e($p['category'] ?: '-') ?></td>
                        <td class="px-4 py-3.5 text-sm font-medium text-gray-900"><?= formatCurrency($p['unit_price']) ?></td>
                        <td class="px-4 py-3.5 text-sm font-bold text-gray-900"><?= $p['quantity_in_stock'] ?></td>
                        <td class="px-4 py-3.5">
                            <?php if ($p['quantity_in_stock'] == 0): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Out of Stock</span>
                            <?php elseif ($p['quantity_in_stock'] <= 5): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Low Stock</span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                <a href="/index.php?page=products&edit=<?= $p['id'] ?>" 
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button onclick="confirmDelete(<?= $p['id'] ?>, '<?= e(addslashes($p['name'])) ?>')" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-medium transition-colors">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Product Modal -->
<div id="add-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-fadeIn">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Add Product</h3>
            <button onclick="closeModal('add-modal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="/index.php?page=products">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="p-6 space-y-4">
                <?php if (!empty($errors)): ?>
                <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <?= e(implode('. ', $errors)) ?>
                </div>
                <?php endif; ?>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required minlength="2" maxlength="200"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="e.g. Rice (25kg bag)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Category</label>
                    <input type="text" name="category" maxlength="100"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="e.g. Food, Electronics">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Unit Price (USD) <span class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01" min="0"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Initial Quantity</label>
                    <input type="number" name="quantity_in_stock" min="0" value="0"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" maxlength="500" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-y"
                        placeholder="Brief product description (optional)"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" onclick="closeModal('add-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<?php if ($editProduct): ?>
<div id="edit-modal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-fadeIn">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Edit Product</h3>
            <a href="/index.php?page=products" class="text-gray-400 hover:text-gray-600 text-xl">&times;</a>
        </div>
        <form method="POST" action="/index.php?page=products">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required minlength="2" maxlength="200" value="<?= e($editProduct['name']) ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Category</label>
                    <input type="text" name="category" maxlength="100" value="<?= e($editProduct['category'] ?? '') ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Unit Price (USD) <span class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" required step="0.01" min="0" value="<?= $editProduct['unit_price'] ?>"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" maxlength="500" rows="3"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-y"><?= e($editProduct['description'] ?? '') ?></textarea>
                </div>
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Current stock: <strong><?= $editProduct['quantity_in_stock'] ?></strong> units. Use Stock In/Out to change quantity.
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <a href="/index.php?page=products" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100 inline-block">Cancel</a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-save"></i> Update Product
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm animate-fadeIn">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Confirm Delete</h3>
            <button onclick="closeModal('delete-modal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="p-6 text-center">
            <i class="fas fa-trash-alt text-red-500 text-4xl mb-4"></i>
            <h4 class="text-lg font-semibold mb-2">Delete this product?</h4>
            <p class="text-sm text-gray-500 mb-1">You are about to delete: <strong id="delete-product-name"></strong></p>
            <p class="text-sm text-gray-500">This action cannot be undone. All stock records for this product will also be removed.</p>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <button onclick="closeModal('delete-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100">Cancel</button>
            <form method="POST" action="/index.php?page=products" id="delete-form" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" id="delete-product-id" value="">
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.getElementById(id).classList.add('flex');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.getElementById(id).classList.remove('flex');
}

function confirmDelete(id, name) {
    document.getElementById('delete-product-id').value = id;
    document.getElementById('delete-product-name').textContent = name;
    openModal('delete-modal');
}

// Close modal on backdrop click
document.querySelectorAll('[id$="-modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            this.classList.remove('flex');
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
