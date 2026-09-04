<?php
/**
 * User Login Page
 * Feature 1: User Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect based on role
if (isLoggedIn()) {
    $role = $_SESSION['user_type'] ?? 'customer';
    if ($role === 'admin' || $role === 'staff') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please provide both your Email address and Password.";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, Name, Email, password, User_type, wallet, revenue FROM user WHERE Email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, initialize session
            $_SESSION['user_id']    = (int)$user['user_id'];
            $_SESSION['user_name']  = $user['Name'];
            $_SESSION['user_email'] = $user['Email'];
            $_SESSION['user_type']  = $user['User_type'];
            $_SESSION['wallet']     = (float)$user['wallet'];
            $_SESSION['revenue']    = (float)$user['revenue'];

            setFlashMessage('success', "Welcome back, " . htmlspecialchars($user['Name']) . "!");

            if ($user['User_type'] === 'admin' || $user['User_type'] === 'staff') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid Email address or Password. Please check your credentials.";
        }
    }
}

$pageTitle = "Sign In";
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">Welcome Back</div>
            <h2 class="auth-title">Sign In to Smart Cycle</h2>
            <p class="auth-subtitle">Access your cycle wallet, active rides, and station network</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="form-grid" novalidate>
            <div class="form-group full-width">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="e.g. customer@cycle.com" required autofocus>
            </div>

            <div class="form-group full-width">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <div class="form-group full-width" style="margin-top: 10px;">
                <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In to Account</button>
            </div>
        </form>

        <!-- University Viva Demo Credentials Box -->
        <div class="demo-accounts-box">
            <div class="demo-header">
                <strong>🎓 Demo Testing Accounts</strong>
            </div>
            <div class="demo-grid">
                <div class="demo-item" onclick="fillLogin('admin@cycle.com', 'admin123')">
                    <span class="demo-role badge badge-danger">Admin</span>
                    <span class="demo-email">admin@cycle.com</span>
                    <small>admin123</small>
                </div>
                <div class="demo-item" onclick="fillLogin('staff@cycle.com', 'staff123')">
                    <span class="demo-role badge badge-primary">Staff</span>
                    <span class="demo-email">staff@cycle.com</span>
                    <small>staff123</small>
                </div>
                <div class="demo-item" onclick="fillLogin('customer@cycle.com', 'customer123')">
                    <span class="demo-role badge badge-success">Customer</span>
                    <span class="demo-email">customer@cycle.com</span>
                    <small>customer123</small>
                </div>
            </div>
        </div>

        <div class="auth-footer">
            <p>New to Smart Cycle? <a href="register.php" class="auth-link">Create a free account</a></p>
        </div>
    </div>
</div>

<script>
function fillLogin(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
