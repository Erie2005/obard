<?php
$pageTitle = 'Stock Records - Stock Management System';
requireLogin();
$db = getDB();
$shopkeeperId = getShopkeeperId();
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'stock_in' || $action === 'stock_out') {
        $productId = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $unitPrice = floatval($_POST['unit_price'] ?? 0);
        $contact = trim($_POST['contact'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($productId <= 0) $errors[] = 'Please select a product';
        if ($quantity <= 0) $errors[] = 'Quantity must be at least 1';
        if ($unitPrice < 0) $errors[] = 'Unit price must be a positive number';

        // Verify product exists and belongs to shopkeeper
        $product = $db->prepare('SELECT * FROM product WHERE id = ? AND shopkeeper_id = ?');
        $product->execute([$productId, $shopkeeperId]);
        $product = $product->fetch();

        if (!$product) $errors[] = 'Product not found';

        if ($action === 'stock_out' && $product && $product['quantity_in_stock'] < $quantity) {
            $errors[] = 'Insufficient stock. Available: ' . $product['quantity_in_stock'] . ', Requested: ' . $quantity;
        }

        if (empty($errors)) {
            $db->beginTransaction();
            try {
                if ($action === 'stock_in') {
                    $stmt = $db->prepare('INSERT INTO product_in (product_id, quantity, unit_price, supplier, notes, shopkeeper_id) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$productId, $quantity, $unitPrice, $contact ?: null, $notes ?: null, $shopkeeperId]);
                    $db->prepare('UPDATE product SET quantity_in_stock = quantity_in_stock + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                       ->execute([$quantity, $productId]);
                    setFlash('success', 'Stock-in recorded: ' . $quantity . ' units of "' . $product['name'] . '" added');
                } else {
                    $stmt = $db->prepare('INSERT INTO product_out (product_id, quantity, unit_price, customer, notes, shopkeeper_id) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$productId, $quantity, $unitPrice, $contact ?: null, $notes ?: null, $shopkeeperId]);
                    $db->prepare('UPDATE product SET quantity_in_stock = quantity_in_stock - ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                       ->execute([$quantity, $productId]);
                    setFlash('success', 'Stock-out recorded: ' . $quantity . ' units of "' . $product['name'] . '" removed');
                }
                $db->commit();
            } catch (Exception $ex) {
                $db->rollBack();
                setFlash('danger', 'Failed to record stock movement: ' . $ex->getMessage());
            }
            header('Location: /index.php?page=stock');
            exit;
        }
    }
}

// Fetch products for select dropdowns
$productsList = $db->prepare('SELECT id, name, quantity_in_stock, unit_price FROM product WHERE shopkeeper_id = ? ORDER BY name');
$productsList->execute([$shopkeeperId]);
$products = $productsList->fetchAll();

// Fetch stock records
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

if ($filter === 'in') {
    $query = "SELECT pi.*, p.name as product_name, 'IN' as type FROM product_in pi JOIN product p ON pi.product_id = p.id WHERE pi.shopkeeper_id = ?";
    $params = [$shopkeeperId];
    if ($search) { $query .= " AND (p.name LIKE ? OR pi.supplier LIKE ? OR pi.notes LIKE ?)"; $like = "%$search%"; $params = array_merge($params, [$like, $like, $like]); }
    $query .= " ORDER BY pi.recorded_at DESC";
} elseif ($filter === 'out') {
    $query = "SELECT po.*, p.name as product_name, 'OUT' as type FROM product_out po JOIN product p ON po.product_id = p.id WHERE po.shopkeeper_id = ?";
    $params = [$shopkeeperId];
    if ($search) { $query .= " AND (p.name LIKE ? OR po.customer LIKE ? OR po.notes LIKE ?)"; $like = "%$search%"; $params = array_merge($params, [$like, $like, $like]); }
    $query .= " ORDER BY po.recorded_at DESC";
} else {
    $query = "
        SELECT * FROM (
            SELECT pi.id, pi.product_id, pi.quantity, pi.unit_price, pi.supplier as contact, pi.notes, pi.recorded_at, p.name as product_name, 'IN' as type
            FROM product_in pi JOIN product p ON pi.product_id = p.id WHERE pi.shopkeeper_id = ?
            UNION ALL
            SELECT po.id, po.product_id, po.quantity, po.unit_price, po.customer as contact, po.notes, po.recorded_at, p.name as product_name, 'OUT' as type
            FROM product_out po JOIN product p ON po.product_id = p.id WHERE po.shopkeeper_id = ?
        )";
    $params = [$shopkeeperId, $shopkeeperId];
    if ($search) { $query .= " WHERE product_name LIKE ? OR contact LIKE ? OR notes LIKE ?"; $like = "%$search%"; $params = array_merge($params, [$like, $like, $like]); }
    $query .= " ORDER BY recorded_at DESC";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

$filterTitles = ['all' => 'All Stock Records', 'in' => 'Stock In Records', 'out' => 'Stock Out Records'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>

<main class="lg:ml-64 p-6 lg:p-8 pt-16 lg:pt-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Stock Records</h1>
            <p class="text-gray-500 text-sm mt-1">Record and view stock movements</p>
        </div>
        <div class="flex gap-3">
            <button onclick="openModal('stockin-modal')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors text-sm">
                <i class="fas fa-arrow-down"></i> Stock In
            </button>
            <button onclick="openModal('stockout-modal')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition-colors text-sm">
                <i class="fas fa-arrow-up"></i> Stock Out
            </button>
        </div>
    </div>

    <?php require __DIR__ . '/../includes/alert.php'; ?>

    <?php if (!empty($errors)): ?>
    <div class="animate-fadeIn mb-5 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <i class="fas fa-exclamation-circle mr-1"></i>
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex border-b-2 border-gray-200 mb-6 gap-0">
        <a href="/index.php?page=stock&filter=all<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="px-5 py-3 text-sm font-semibold border-b-2 -mb-[2px] transition-colors <?= $filter === 'all' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
            All Records
        </a>
        <a href="/index.php?page=stock&filter=in<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="px-5 py-3 text-sm font-semibold border-b-2 -mb-[2px] transition-colors <?= $filter === 'in' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
            Stock In
        </a>
        <a href="/index.php?page=stock&filter=out<?= $search ? '&search=' . urlencode($search) : '' ?>"
           class="px-5 py-3 text-sm font-semibold border-b-2 -mb-[2px] transition-colors <?= $filter === 'out' ? 'text-indigo-600 border-indigo-600' : 'text-gray-500 border-transparent hover:text-gray-700' ?>">
            Stock Out
        </a>
    </div>

    <!-- Records Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h3 class="font-semibold text-gray-900"><?= $filterTitles[$filter] ?> (<?= count($records) ?>)</h3>
            <form method="GET" action="/index.php" class="relative w-full sm:w-72">
                <input type="hidden" name="page" value="stock">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search records..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
            </form>
        </div>

        <?php if (empty($records)): ?>
        <div class="text-center py-16 text-gray-400">
            <i class="fas fa-exchange-alt text-5xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-600 mb-2">No stock records yet</h3>
            <p class="text-sm">Record your first stock-in or stock-out transaction</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Product</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Qty</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit Price</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Supplier/Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($records as $i => $r): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3.5 text-sm text-gray-500"><?= $i + 1 ?></td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $r['type'] === 'IN' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <i class="fas fa-arrow-<?= $r['type'] === 'IN' ? 'down' : 'up' ?> mr-1 text-[10px]"></i>
                                <?= $r['type'] === 'IN' ? 'Stock In' : 'Stock Out' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-sm font-medium text-gray-900"><?= e($r['product_name']) ?></td>
                        <td class="px-4 py-3.5 text-sm text-gray-900"><?= $r['quantity'] ?></td>
                        <td class="px-4 py-3.5 text-sm text-gray-600"><?= formatCurrency($r['unit_price']) ?></td>
                        <td class="px-4 py-3.5 text-sm font-semibold text-gray-900"><?= formatCurrency($r['quantity'] * $r['unit_price']) ?></td>
                        <td class="px-4 py-3.5 text-sm text-gray-600"><?= e($r['contact'] ?? $r['supplier'] ?? $r['customer'] ?? '-') ?></td>
                        <td class="px-4 py-3.5 text-sm text-gray-500 max-w-[150px] truncate"><?= e($r['notes'] ?? '-') ?></td>
                        <td class="px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap"><?= formatDate($r['recorded_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Stock In Modal -->
<div id="stockin-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-fadeIn">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-arrow-down text-green-500"></i> Record Stock In
            </h3>
            <button onclick="closeModal('stockin-modal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="/index.php?page=stock">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="stock_in">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Product <span class="text-red-500">*</span></label>
                    <select name="product_id" required onchange="updatePrice(this, 'in')"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= $p['unit_price'] ?>" data-stock="<?= $p['quantity_in_stock'] ?>">
                                <?= e($p['name']) ?> (Stock: <?= $p['quantity_in_stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" required min="1"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="Enter quantity">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Unit Price (USD) <span class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" id="in-price" required step="0.01" min="0"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Supplier</label>
                    <input type="text" name="contact" maxlength="200"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="Supplier name (optional)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" maxlength="500" rows="2"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-y"
                        placeholder="Additional notes (optional)"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" onclick="closeModal('stockin-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-arrow-down"></i> Record Stock In
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Out Modal -->
<div id="stockout-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto animate-fadeIn">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                <i class="fas fa-arrow-up text-red-500"></i> Record Stock Out
            </h3>
            <button onclick="closeModal('stockout-modal')" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <form method="POST" action="/index.php?page=stock">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="stock_out">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Product <span class="text-red-500">*</span></label>
                    <select name="product_id" required onchange="updatePrice(this, 'out')"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none bg-white">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-price="<?= $p['unit_price'] ?>" data-stock="<?= $p['quantity_in_stock'] ?>">
                                <?= e($p['name']) ?> (Stock: <?= $p['quantity_in_stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1" id="out-stock-hint"></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" name="quantity" id="out-quantity" required min="1"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="Enter quantity">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Unit Price (USD) <span class="text-red-500">*</span></label>
                    <input type="number" name="unit_price" id="out-price" required step="0.01" min="0"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Customer</label>
                    <input type="text" name="contact" maxlength="200"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
                        placeholder="Customer name (optional)">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notes</label>
                    <textarea name="notes" maxlength="500" rows="2"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none resize-y"
                        placeholder="Additional notes (optional)"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button type="button" onclick="closeModal('stockout-modal')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-100">Cancel</button>
                <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <i class="fas fa-arrow-up"></i> Record Stock Out
                </button>
            </div>
        </form>
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

function updatePrice(select, type) {
    const opt = select.options[select.selectedIndex];
    const price = opt.dataset.price || '';
    const stock = opt.dataset.stock || '';
    document.getElementById(type + '-price').value = price;
    if (type === 'out') {
        document.getElementById('out-stock-hint').textContent = stock ? 'Available stock: ' + stock : '';
        document.getElementById('out-quantity').max = stock;
    }
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
