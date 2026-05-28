<?php
$pageTitle = 'Register - Stock Management System';
$errors = [];
$fullName = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($fullName) || strlen($fullName) < 2) $errors[] = 'Full name must be at least 2 characters';
        if (empty($email)) $errors[] = 'Email is required';
        elseif (!validateEmail($email)) $errors[] = 'Enter a valid email address';
        if (!empty($phone) && !preg_match('/^\+?[0-9]{7,15}$/', $phone)) $errors[] = 'Invalid phone number format';

        $pwdErrors = validatePassword($password);
        if (!empty($pwdErrors)) $errors = array_merge($errors, $pwdErrors);
        if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';

        if (empty($errors)) {
            $db = getDB();
            $stmt = $db->prepare('SELECT id FROM shopkeeper WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already registered';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO shopkeeper (full_name, email, phone, password) VALUES (?, ?, ?, ?)');
                $stmt->execute([$fullName, $email, $phone ?: null, $hash]);

                $_SESSION['shopkeeper_id'] = (int)$db->lastInsertId();
                $_SESSION['shopkeeper_name'] = $fullName;
                $_SESSION['shopkeeper_email'] = $email;
                session_regenerate_id(true);

                header('Location: /index.php?page=dashboard');
                exit;
            }
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 px-4 py-8">
    <div class="bg-white rounded-2xl shadow-2xl p-8 sm:p-10 w-full max-w-md animate-fadeIn">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-2xl mb-4">
                <i class="fas fa-store text-indigo-600 text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Create Account</h1>
            <p class="text-gray-500 mt-1">Register to start managing your stock</p>
        </div>

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

        <form method="POST" action="/index.php?page=register" novalidate>
            <?= csrfField() ?>

            <div class="mb-4">
                <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Full Name <span class="text-red-500">*</span>
                </label>
                <input type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    placeholder="Enter your full name" required>
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    placeholder="Enter your email" required autocomplete="email">
            </div>

            <div class="mb-4">
                <label for="phone" class="block text-sm font-semibold text-gray-700 mb-1.5">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?= e($phone) ?>"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
                    placeholder="+250780000000">
                <p class="text-xs text-gray-400 mt-1">Optional. Format: +250XXXXXXXXX</p>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Password <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" id="password" name="password"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all pr-10"
                        placeholder="Create a strong password" required>
                    <button type="button" onclick="togglePassword('password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Min 8 chars: uppercase, lowercase, number, special character</p>
            </div>

            <div class="mb-6">
                <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-1.5">
                    Confirm Password <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all pr-10"
                        placeholder="Confirm your password" required>
                    <button type="button" onclick="togglePassword('confirm_password')"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-eye" id="confirm_password-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2 text-sm">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <p class="text-center mt-6 text-sm text-gray-500">
            Already have an account?
            <a href="/index.php?page=login" class="text-indigo-600 hover:underline font-semibold">Sign In</a>
        </p>
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
