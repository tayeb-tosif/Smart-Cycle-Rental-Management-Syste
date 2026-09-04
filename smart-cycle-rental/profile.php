<?php
/**
 * User Profile Management
 * Feature 1: User Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)$_SESSION['user_id'];

// Refresh session data
refreshUserSession($pdo);

$errors = [];
$success = '';

// Handle Profile Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name    = sanitizeInput($_POST['name'] ?? '');
    $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $address = sanitizeInput($_POST['address'] ?? '');

    if (empty($name)) {
        $errors[] = "Name cannot be empty.";
    }
    if (!$email) {
        $errors[] = "A valid Email address is required.";
    }
    if (empty($address)) {
        $errors[] = "Address cannot be empty.";
    }

    if (empty($errors)) {
        // Check if email is taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE Email = :email AND user_id != :uid LIMIT 1");
        $stmt->execute(['email' => $email, 'uid' => $userId]);
        if ($stmt->fetch()) {
            $errors[] = "The email address '{$email}' is already in use by another user.";
        } else {
            $updateStmt = $pdo->prepare("UPDATE user SET Name = :name, Email = :email, Address = :address WHERE user_id = :uid");
            $updateStmt->execute([
                'name'    => $name,
                'email'   => $email,
                'address' => $address,
                'uid'     => $userId
            ]);

            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            setFlashMessage('success', "Profile details updated successfully.");
            header("Location: profile.php");
            exit;
        }
    }
}

// Handle Add Phone Number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_phone') {
    $newPhone = sanitizeInput($_POST['new_phone'] ?? '');
    if (empty($newPhone) || !preg_match('/^[0-9+ -]{7,20}$/', $newPhone)) {
        $errors[] = "Please provide a valid phone number.";
    } else {
        try {
            $stmtPhone = $pdo->prepare("INSERT INTO phoner_number (user_id, Phone) VALUES (:uid, :phone)");
            $stmtPhone->execute(['uid' => $userId, 'phone' => $newPhone]);
            setFlashMessage('success', "Phone number added successfully.");
            header("Location: profile.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "This phone number is already registered to your account.";
        }
    }
}

// Handle Delete Phone Number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_phone') {
    $delPhone = sanitizeInput($_POST['phone_to_delete'] ?? '');
    // Check user has at least one phone before deleting
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM phoner_number WHERE user_id = :uid");
    $countStmt->execute(['uid' => $userId]);
    if ($countStmt->fetchColumn() <= 1) {
        $errors[] = "You must keep at least one contact phone number registered.";
    } else {
        $delStmt = $pdo->prepare("DELETE FROM phoner_number WHERE user_id = :uid AND Phone = :phone");
        $delStmt->execute(['uid' => $userId, 'phone' => $delPhone]);
        setFlashMessage('success', "Phone number removed.");
        header("Location: profile.php");
        exit;
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_new_password'] ?? '';

    $stmtPass = $pdo->prepare("SELECT password FROM user WHERE user_id = :uid");
    $stmtPass->execute(['uid' => $userId]);
    $userRow = $stmtPass->fetch();

    if (!password_verify($currentPass, $userRow['password'])) {
        $errors[] = "Your current password is incorrect.";
    } elseif (strlen($newPass) < 6) {
        $errors[] = "New password must be at least 6 characters long.";
    } elseif ($newPass !== $confirmPass) {
        $errors[] = "New password and confirmation do not match.";
    } else {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $updatePass = $pdo->prepare("UPDATE user SET password = :p WHERE user_id = :uid");
        $updatePass->execute(['p' => $newHash, 'uid' => $userId]);
        setFlashMessage('success', "Password updated successfully.");
        header("Location: profile.php");
        exit;
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM user WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$user = $stmt->fetch();

// Fetch user phone numbers
$stmtPhones = $pdo->prepare("SELECT Phone FROM phoner_number WHERE user_id = :uid ORDER BY Phone");
$stmtPhones->execute(['uid' => $userId]);
$phoneList = $stmtPhones->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "My Profile";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User Profile</h1>
        <p class="page-subtitle">Manage your personal details, phone numbers, and security credentials</p>
    </div>
    <div class="header-action">
        <span class="badge badge-lg <?= $user['User_type'] === 'admin' ? 'badge-danger' : ($user['User_type'] === 'staff' ? 'badge-primary' : 'badge-success') ?>">
            <?= ucfirst(htmlspecialchars($user['User_type'])) ?> Account
        </span>
    </div>
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

<div class="grid grid-3">
    <!-- Left Column: User Summary & Stats -->
    <div class="card">
        <div class="profile-card-summary">
            <div class="profile-big-avatar">
                <?= strtoupper(substr($user['Name'], 0, 1)) ?>
            </div>
            <h3 class="profile-name"><?= htmlspecialchars($user['Name']) ?></h3>
            <p class="text-muted"><?= htmlspecialchars($user['Email']) ?></p>
            <div class="badge badge-outline mt-2">User ID: #<?= htmlspecialchars($user['user_id']) ?></div>
        </div>

        <hr class="card-divider">

        <div class="stat-mini-group">
            <div class="stat-mini-item">
                <span class="stat-mini-label">Wallet Balance</span>
                <span class="stat-mini-val text-success"><?= formatCurrency((float)$user['wallet']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">Total <?= $user['User_type'] === 'customer' ? 'Spent' : 'Revenue' ?></span>
                <span class="stat-mini-val text-primary"><?= formatCurrency((float)$user['revenue']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">National ID (NID)</span>
                <span class="stat-mini-val font-mono"><?= htmlspecialchars($user['NID']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">Date of Birth</span>
                <span class="stat-mini-val"><?= htmlspecialchars($user['DOB']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">Gender</span>
                <span class="stat-mini-val"><?= htmlspecialchars($user['Sex']) ?></span>
            </div>
        </div>
    </div>

    <!-- Center Column: Edit Personal Info & Phones -->
    <div class="card card-span-2">
        <div class="card-header">
            <h3 class="card-title">Edit Personal Information</h3>
        </div>
        <form action="profile.php" method="POST" class="form-grid">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($user['Name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['Email']) ?>" required>
            </div>

            <div class="form-group full-width">
                <label for="address" class="form-label">Address</label>
                <textarea id="address" name="address" class="form-control" rows="2" required><?= htmlspecialchars($user['Address']) ?></textarea>
            </div>

            <div class="form-group full-width">
                <button type="submit" class="btn btn-primary">Save Profile Changes</button>
            </div>
        </form>

        <hr class="card-divider">

        <!-- Multi-valued Phone Numbers (phoner_number table) -->
        <div class="card-header" style="margin-top: 10px;">
            <h3 class="card-title">Registered Phone Numbers</h3>
            <span class="text-muted text-sm">(Database Table: <code>phoner_number</code>)</span>
        </div>

        <div class="phone-list-container">
            <ul class="phone-list">
                <?php foreach ($phoneList as $p): ?>
                    <li class="phone-item">
                        <span class="phone-number">📞 <?= htmlspecialchars($p) ?></span>
                        <form action="profile.php" method="POST" class="inline-form" onsubmit="return confirm('Delete this phone number?');">
                            <input type="hidden" name="action" value="delete_phone">
                            <input type="hidden" name="phone_to_delete" value="<?= htmlspecialchars($p) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove Phone">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>

            <form action="profile.php" method="POST" class="inline-add-phone-form mt-3">
                <input type="hidden" name="action" value="add_phone">
                <div class="input-group">
                    <input type="tel" name="new_phone" class="form-control" placeholder="Add another phone (e.g. 018XXXXXXXX)" required>
                    <button type="submit" class="btn btn-secondary">Add Phone</button>
                </div>
            </form>
        </div>

        <hr class="card-divider">

        <!-- Change Password Section -->
        <div class="card-header" style="margin-top: 10px;">
            <h3 class="card-title">Change Password</h3>
        </div>
        <form action="profile.php" method="POST" class="form-grid">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 characters" required>
            </div>

            <div class="form-group">
                <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required>
            </div>

            <div class="form-group full-width">
                <button type="submit" class="btn btn-outline-primary">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
