<?php $currentPage = $_GET['page'] ?? 'dashboard'; ?>

<!-- Mobile Toggle -->
<button onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')" 
    class="lg:hidden fixed top-4 left-4 z-50 bg-gray-900 text-white p-2.5 rounded-lg shadow-lg">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-gray-900 text-white z-40 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col">
    <!-- Logo -->
    <div class="px-5 py-6 border-b border-gray-700">
        <h2 class="text-xl font-bold flex items-center gap-3">
            <i class="fas fa-store text-indigo-400 text-2xl"></i>
            <span>StockPro</span>
        </h2>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 py-4">
        <a href="/index.php?page=dashboard" 
           class="sidebar-link flex items-center gap-3 px-5 py-3 text-gray-300 hover:bg-white/5 hover:text-white transition-colors border-l-[3px] border-transparent <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span>Dashboard</span>
        </a>
        <a href="/index.php?page=products" 
           class="sidebar-link flex items-center gap-3 px-5 py-3 text-gray-300 hover:bg-white/5 hover:text-white transition-colors border-l-[3px] border-transparent <?= $currentPage === 'products' ? 'active' : '' ?>">
            <i class="fas fa-boxes-stacked w-5 text-center"></i>
            <span>Products</span>
        </a>
        <a href="/index.php?page=stock" 
           class="sidebar-link flex items-center gap-3 px-5 py-3 text-gray-300 hover:bg-white/5 hover:text-white transition-colors border-l-[3px] border-transparent <?= $currentPage === 'stock' ? 'active' : '' ?>">
            <i class="fas fa-exchange-alt w-5 text-center"></i>
            <span>Stock Records</span>
        </a>
    </nav>

    <!-- User Footer -->
    <div class="px-5 py-4 border-t border-gray-700">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 rounded-full bg-indigo-500 flex items-center justify-center font-bold text-sm">
                <?= strtoupper(substr(getShopkeeperName(), 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold truncate"><?= e(getShopkeeperName()) ?></div>
                <div class="text-xs text-gray-400 truncate"><?= e(getShopkeeperEmail()) ?></div>
            </div>
        </div>
        <a href="/index.php?page=logout" 
           class="flex items-center justify-center gap-2 w-full py-2 px-3 text-sm text-gray-300 border border-gray-600 rounded-lg hover:bg-gray-800 transition-colors">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
