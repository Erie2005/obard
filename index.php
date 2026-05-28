<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

// Initialize database on first run
initializeDatabase();

// Simple router
$page = $_GET['page'] ?? 'login';

// Handle API requests
if (str_starts_with($page, 'api/')) {
    $apiFile = __DIR__ . '/api/' . str_replace('api/', '', $page) . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
    } else {
        jsonResponse(['error' => 'API endpoint not found'], 404);
    }
    exit;
}

// Allowed pages
$allowedPages = ['login', 'register', 'dashboard', 'products', 'stock', 'logout'];

if (!in_array($page, $allowedPages)) {
    $page = 'login';
}

// Handle logout
if ($page === 'logout') {
    session_destroy();
    header('Location: /index.php?page=login');
    exit;
}

// Auth guards
$publicPages = ['login', 'register'];
if (!in_array($page, $publicPages)) {
    requireLogin();
}
if (in_array($page, $publicPages) && isLoggedIn()) {
    header('Location: /index.php?page=dashboard');
    exit;
}

// Load the page
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    echo 'Page not found';
}
