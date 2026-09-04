<?php
/**
 * Customer Registration Page
 * Feature 1: User Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];
$name = '';
$nid = '';
$email = '';
$dob = '';
$address = '';
$sex = 'Male';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitizeInput($_POST['name'] ?? '');
    $nid      = sanitizeInput($_POST['nid'] ?? '');
    $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $rawEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $dob      = sanitizeInput($_POST['dob'] ?? '');
    $address  = sanitizeInput($_POST['address'] ?? '');
    $sex      = sanitizeInput($_POST['sex'] ?? 'Male');
    $phone    = sanitizeInput($_POST['phone'] ?? '');

    // Validation checks
    if (empty($name)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($nid)) {
        $errors[] = "National ID (NID) is required.";
    }
    if (!$email) {
        $errors[] = "A valid Email address is required.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    if ($password !== $confirmPassword) {
        $errors[] = "Password and Confirmation Password do not match.";
    }
    if (empty($dob)) {
        $errors[] = "Date of Birth is required.";
    } else {
        $birthDate = strtotime($dob);
        $minAgeDate = strtotime('-12 years'); // Minimum 12 years age
        if ($birthDate > $minAgeDate) {
            $errors[] = "Users must be at least 12 years of age to register.";
        }
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($phone) || !preg_match('/^[0-9+ -]{7,20}$/', $phone)) {
        $errors[] = "Please provide a valid contact Phone number.";
    }

    // Check unique email and NID in database
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id, Email, NID FROM user WHERE Email = :email OR NID = :nid LIMIT 1");
        $stmt->execute(['email' => $email, 'nid' => $nid]);
        $existing = $stmt->fetch();

        if ($existing) {
            if (strtolower($existing['Email']) === strtolower($email)) {
                $errors[] = "An account with this Email already exists. Please log in instead.";
            }
            if ($existing['NID'] === $nid) {
                $errors[] = "An account with this NID already exists.";
            }
        }
    }

    // Insert user if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into USER table
            $stmtUser = $pdo->prepare("
                INSERT INTO user (NID, Email, password, DOB, Address, Sex, Name, User_type, wallet, revenue)
                VALUES (:nid, :email, :password, :dob, :address, :sex, :name, 'customer', 0.00, 0.00)
            ");
            $stmtUser->execute([
                'nid'      => $nid,
                'email'    => $email,
                'password' => $hashedPassword,
                'dob'      => $dob,
                'address'  => $address,
                'sex'      => $sex,
                'name'     => $name
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Insert phone into PHONER_NUMBER table
            $stmtPhone = $pdo->prepare("
                INSERT INTO phoner_number (user_id, Phone)
                VALUES (:uid, :phone)
            ");
            $stmtPhone->execute([
                'uid'   => $newUserId,
                'phone' => $phone
            ]);

            $pdo->commit();

            setFlashMessage('success', "Registration successful! You can now log in with your credentials.");
            header("Location: login.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed due to a server error. Details: " . $e->getMessage();
        }
    }
}

$pageTitle = "Customer Registration";
include __DIR__ . '/includes/header.php';
?>

<div class="auth-card-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-badge">Join Smart Cycle</div>
            <h2 class="auth-title">Create an Account</h2>
            <p class="auth-subtitle">Rent cycles across campus and the city seamlessly</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <ul class="error-list">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="form-grid" novalidate>
            <div class="form-group full-width">
                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="e.g. Md. Tanvir Ahmed" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?: ($rawEmail ?? '')) ?>" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="017XXXXXXXX" required>
            </div>

            <div class="form-group">
                <label for="nid" class="form-label">National ID (NID) <span class="text-danger">*</span></label>
                <input type="text" id="nid" name="nid" class="form-control" value="<?= htmlspecialchars($nid) ?>" placeholder="e.g. 1998404040404" required>
            </div>

            <div class="form-group">
                <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" id="dob" name="dob" class="form-control" value="<?= htmlspecialchars($dob) ?>" required>
            </div>

            <div class="form-group">
                <label for="sex" class="form-label">Gender <span class="text-danger">*</span></label>
                <select id="sex" name="sex" class="form-select" required>
                    <option value="Male" <?= $sex === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $sex === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $sex === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-group full-width">
                <label for="address" class="form-label">Current Address <span class="text-danger">*</span></label>
                <textarea id="address" name="address" class="form-control" rows="2" placeholder="e.g. House 12, Road 4, Badda, Dhaka" required><?= htmlspecialchars($address) ?></textarea>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
            </div>

            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type your password" required>
            </div>

            <div class="form-group full-width" style="margin-top: 10px;">
                <button type="submit" class="btn btn-primary btn-block btn-lg">Create Customer Account</button>
            </div>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="login.php" class="auth-link">Sign In here</a></p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
