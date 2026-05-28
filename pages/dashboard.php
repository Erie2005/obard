<?php
$pageTitle = 'Dashboard - Stock Management System';
requireLogin();
$db = getDB();
$shopkeeperId = getShopkeeperId();

// Fetch stats
$totalProducts = $db->prepare('SELECT COUNT(*) as cnt FROM product WHERE shopkeeper_id = ?');
$totalProducts->execute([$shopkeeperId]);
$totalProducts = $totalProducts->fetch()['cnt'];

$stockValue = $db->prepare('SELECT COALESCE(SUM(unit_price * quantity_in_stock), 0) as val FROM product WHERE shopkeeper_id = ?');
$stockValue->execute([$shopkeeperId]);
$stockValue = $stockValue->fetch()['val'];

$totalIn = $db->prepare('SELECT COALESCE(SUM(quantity), 0) as total FROM product_in WHERE shopkeeper_id = ?');
$totalIn->execute([$shopkeeperId]);
$totalIn = $totalIn->fetch()['total'];

$totalOut = $db->prepare('SELECT COALESCE(SUM(quantity), 0) as total FROM product_out WHERE shopkeeper_id = ?');
$totalOut->execute([$shopkeeperId]);
$totalOut = $totalOut->fetch()['total'];

$lowStock = $db->prepare('SELECT * FROM product WHERE shopkeeper_id = ? AND quantity_in_stock <= 5 ORDER BY quantity_in_stock ASC');
$lowStock->execute([$shopkeeperId]);
$lowStockProducts = $lowStock->fetchAll();

$recentActivity = $db->prepare("
    SELECT * FROM (
        SELECT pi.quantity, pi.recorded_at, p.name as product_name, 'IN' as type
        FROM product_in pi JOIN product p ON pi.product_id = p.id
        WHERE pi.shopkeeper_id = ?
        UNION ALL
        SELECT po.quantity, po.recorded_at, p.name as product_name, 'OUT' as type
        FROM product_out po JOIN product p ON po.product_id = p.id
        WHERE po.shopkeeper_id = ?
    ) ORDER BY recorded_at DESC LIMIT 10
");
$recentActivity->execute([$shopkeeperId, $shopkeeperId]);
$activities = $recentActivity->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>

<!-- Main Content -->
<main class="lg:ml-64 p-6 lg:p-8 pt-16 lg:pt-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">Overview of your stock management</p>
    </div>

    <?php require __DIR__ . '/../includes/alert.php'; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
        <!-- Total Products -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-boxes-stacked text-blue-600 text-xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Products</p>
                <p class="text-2xl font-bold text-gray-900"><?= $totalProducts ?></p>
            </div>
        </div>

        <!-- Stock Value -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-coins text-green-600 text-xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock Value</p>
                <p class="text-2xl font-bold text-gray-900"><?= formatCurrency($stockValue) ?></p>
            </div>
        </div>

        <!-- Total Stock In -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-14 h-14 bg-amber-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-down text-amber-600 text-xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Stock In</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalIn) ?></p>
            </div>
        </div>

        <!-- Total Stock Out -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-4">
            <div class="w-14 h-14 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-arrow-up text-red-600 text-xl"></i>
            </div>
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Stock Out</p>
                <p class="text-2xl font-bold text-gray-900"><?= number_format($totalOut) ?></p>
            </div>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Low Stock Alerts -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle text-amber-500"></i>
                <h3 class="font-semibold text-gray-900">Low Stock Alerts</h3>
                <?php if (count($lowStockProducts) > 0): ?>
                    <span class="ml-auto bg-amber-100 text-amber-700 text-xs font-semibold px-2.5 py-0.5 rounded-full"><?= count($lowStockProducts) ?></span>
                <?php endif; ?>
            </div>
            <div class="p-6">
                <?php if (empty($lowStockProducts)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-check-circle text-green-400 text-4xl mb-3"></i>
                        <p class="font-medium text-gray-600">All products are well stocked</p>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($lowStockProducts as $p): ?>
                        <li class="flex items-center justify-between py-3">
                            <div>
                                <span class="font-medium text-gray-800"><?= e($p['name']) ?></span>
                                <?php if ($p['category']): ?>
                                    <span class="text-xs text-gray-400 ml-2"><?= e($p['category']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full <?= $p['quantity_in_stock'] == 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?>">
                                <?= $p['quantity_in_stock'] ?> left
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center gap-2">
                <i class="fas fa-clock text-indigo-500"></i>
                <h3 class="font-semibold text-gray-900">Recent Activity</h3>
            </div>
            <div class="p-6">
                <?php if (empty($activities)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-inbox text-4xl mb-3"></i>
                        <p class="font-medium text-gray-600">No recent activity</p>
                    </div>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($activities as $a): ?>
                        <li class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 <?= $a['type'] === 'IN' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?>">
                                <i class="fas fa-arrow-<?= $a['type'] === 'IN' ? 'down' : 'up' ?> text-xs"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-800">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $a['type'] === 'IN' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $a['type'] === 'IN' ? 'Stock In' : 'Stock Out' ?>
                                    </span>
                                    <?= $a['quantity'] ?> x <?= e($a['product_name']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5"><?= timeAgo($a['recorded_at']) ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
