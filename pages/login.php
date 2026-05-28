<?php
$pageTitle = 'Login - Stock Management System';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) $errors[] = 'Email is required';
        elseif (!validateEmail($email)) $errors[] = 'Enter a valid email address';
        if (empty($password)) $errors[] = 'Password is required';

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare('SELECT * FROM shopkeeper WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['shopkeeper_id'] = $user['id'];
                $_SESSION['shopkeeper_name'] = $user['full_name'];
                $_SESSION['shopkeeper_email'] = $user['email'];
                // Regenerate session to prevent fixation
                session_regenerate_id(true);
                header('Location: /index.php?page=dashboard');
                exit;
            } else {
                $errors[] = 'Invalid email or password';
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 px-4 py-8">
    <div class="bg-white rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-md animate-fadeIn">
        <!-- Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-2xl mb-4">
                <i class="fas fa-store text-indigo-600 text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Welcome Back</h1>
            <p class="text-gray-500 mt-1">Sign in to manage your stock</p>
        </div>

        <!-- Errors -->
        <?php if (!empty($errors)): ?>
        <div class="animate-fadeIn mb-5 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <i class="fas fa-exclamation-circle mr-1"></i>
            <?= e(implode('. ', $errors)) ?>
        </div>
        <?php endif; ?>

        <!-- Flash messages -->
        <?php require __DIR__ . '/../includes/alert.php'; ?>

        <!-- Form -->
        <form method="POST" action="/index.php?page=login" novalidate>
            <?= csrfField() ?>

            <div class="mb-5">
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    placeholder="Enter your email" required autocomplete="email">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Password <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all pr-10"
                        placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" onclick="togglePassword('password')" 
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <p class="text-center mt-6 text-sm text-gray-500">
            Don't have an account? 
            <a href="/index.php?page=register" class="text-indigo-600 hover:underline font-semibold">Create Account</a>
        </p>

        <!-- Demo credentials -->
        <div class="mt-5 p-4 bg-sky-50 border border-sky-200 rounded-lg text-xs text-sky-700">
            <strong><i class="fas fa-info-circle"></i> Demo Credentials:</strong><br>
            Email: <code class="bg-sky-100 px-1 rounded">admin@shop.com</code><br>
            Password: <code class="bg-sky-100 px-1 rounded">Admin@123</code>
        </div>
    </div>
</div>

<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
