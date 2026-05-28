<?php

function isLoggedIn(): bool {
    return isset($_SESSION['shopkeeper_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /index.php?page=login');
        exit;
    }
}

function redirectIfLoggedIn(): void {
    if (isLoggedIn()) {
        header('Location: /index.php?page=dashboard');
        exit;
    }
}

function getShopkeeperId(): int {
    return (int)$_SESSION['shopkeeper_id'];
}

function getShopkeeperName(): string {
    return $_SESSION['shopkeeper_name'] ?? 'Shopkeeper';
}

function getShopkeeperEmail(): string {
    return $_SESSION['shopkeeper_email'] ?? '';
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function formatCurrency(float $amount): string {
    return '$' . number_format($amount, 2);
}

function formatDate(string $date): string {
    return date('M d, Y H:i', strtotime($date));
}

function timeAgo(string $date): string {
    $diff = time() - strtotime($date);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', strtotime($date));
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Must contain an uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Must contain a lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Must contain a number';
    if (!preg_match('/[@$!%*?&#]/', $password)) $errors[] = 'Must contain a special character (@$!%*?&#)';
    return $errors;
}
