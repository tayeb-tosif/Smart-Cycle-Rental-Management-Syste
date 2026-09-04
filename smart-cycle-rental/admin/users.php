<?php
/**
 * User & Staff Management (Admin & Staff)
 * Feature 1 & 7: User & Admin Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffOrAdmin();
$isAdmin = $_SESSION['user_type'] === 'admin';

$errors = [];
$success = '';

// Handle Add User / Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    if (!$isAdmin) {
        $errors[] = "Only Admins can create new system users.";
    } else {
        $name     = sanitizeInput($_POST['name'] ?? '');
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $nid      = sanitizeInput($_POST['nid'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = sanitizeInput($_POST['user_type'] ?? 'customer');
        $dob      = sanitizeInput($_POST['dob'] ?? '2000-01-01');
        $sex      = sanitizeInput($_POST['sex'] ?? 'Male');
        $address  = sanitizeInput($_POST['address'] ?? 'Dhaka, Bangladesh');
        $phone    = sanitizeInput($_POST['phone'] ?? '');
        $wallet   = (float)($_POST['wallet'] ?? 0.00);

        if (empty($name) || !$email || empty($nid) || empty($password)) {
            $errors[] = "Name, valid Email, NID, and Password are required.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO user (NID, Email, password, DOB, Address, Sex, Name, User_type, wallet, revenue)
                    VALUES (:nid, :email, :pass, :dob, :address, :sex, :name, :role, :wallet, 0.00)
                ");
                $stmt->execute([
                    'nid'     => $nid,
                    'email'   => $email,
                    'pass'    => $hash,
                    'dob'     => $dob,
                    'address' => $address,
                    'sex'     => $sex,
                    'name'    => $name,
                    'role'    => $role,
                    'wallet'  => $wallet
                ]);
                $newUid = (int)$pdo->lastInsertId();

                if (!empty($phone)) {
                    $stmtPhone = $pdo->prepare("INSERT INTO phoner_number (user_id, Phone) VALUES (:uid, :phone)");
                    $stmtPhone->execute(['uid' => $newUid, 'phone' => $phone]);
                }

                $pdo->commit();
                setFlashMessage('success', "User '{$name}' ({$role}) created successfully.");
                header("Location: users.php");
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                if ($e->getCode() == 23000) {
                    $errors[] = "An account with this Email or NID already exists.";
                } else {
                    $errors[] = "Failed to create user: " . $e->getMessage();
                }
            }
        }
    }
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    if (!$isAdmin) {
        $errors[] = "Only Admins can edit users.";
    } else {
        $editUid = (int)($_POST['user_id'] ?? 0);
        $name    = sanitizeInput($_POST['name'] ?? '');
        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $nid     = sanitizeInput($_POST['nid'] ?? '');
        $role    = sanitizeInput($_POST['user_type'] ?? 'customer');
        $wallet  = (float)($_POST['wallet'] ?? 0.00);
        $address = sanitizeInput($_POST['address'] ?? '');

        if ($editUid <= 0 || empty($name) || !$email || empty($nid)) {
            $errors[] = "Invalid user parameters.";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE user 
                    SET Name = :name, Email = :email, NID = :nid, User_type = :role, wallet = :wallet, Address = :addr 
                    WHERE user_id = :uid
                ");
                $stmt->execute([
                    'name'   => $name,
                    'email'  => $email,
                    'nid'    => $nid,
                    'role'   => $role,
                    'wallet' => $wallet,
                    'addr'   => $address,
                    'uid'    => $editUid
                ]);

                setFlashMessage('success', "User #{$editUid} updated successfully.");
                header("Location: users.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }
}

// Handle Adjust Wallet Balance (Demo top-up / adjustment for viva)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'credit_wallet') {
    $targetUid = (int)($_POST['user_id'] ?? 0);
    $addAmount = (float)($_POST['credit_amount'] ?? 0.00);

    if ($targetUid <= 0 || $addAmount <= 0) {
        $errors[] = "Invalid wallet credit parameters.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE user SET wallet = wallet + :amt WHERE user_id = :uid");
            $stmt->execute(['amt' => $addAmount, 'uid' => $targetUid]);
            setFlashMessage('success', "Added " . formatCurrency($addAmount) . " to User #{$targetUid}'s wallet.");
            header("Location: users.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Credit failed: " . $e->getMessage();
        }
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (!$isAdmin) {
        $errors[] = "Only Admins can delete users.";
    } else {
        $delUid = (int)($_POST['user_id'] ?? 0);
        if ($delUid === (int)$_SESSION['user_id']) {
            $errors[] = "You cannot delete your own logged-in admin account.";
        } else {
            // Check if user has active rental
            $chkActive = $pdo->prepare("SELECT COUNT(*) FROM rental_trans WHERE user_id = :uid AND status = 'Active'");
            $chkActive->execute(['uid' => $delUid]);
            if ($chkActive->fetchColumn() > 0) {
                $errors[] = "Cannot delete this user because they currently have an active bicycle rental.";
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM user WHERE user_id = :uid");
                    $stmt->execute(['uid' => $delUid]);
                    setFlashMessage('success', "User #{$delUid} deleted from database.");
                    header("Location: users.php");
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Delete failed: " . $e->getMessage();
                }
            }
        }
    }
}

// Search & Filter
$search = sanitizeInput($_GET['q'] ?? '');
$roleFilter = sanitizeInput($_GET['role'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.Name LIKE :q OR u.Email LIKE :q OR u.NID LIKE :q OR u.user_id = :qid)";
    $params['q'] = "%{$search}%";
    $params['qid'] = is_numeric($search) ? (int)$search : 0;
}

if (!empty($roleFilter)) {
    $where[] = "u.User_type = :role";
    $params['role'] = $roleFilter;
}

$sql = "
    SELECT 
        u.*,
        GROUP_CONCAT(pn.Phone SEPARATOR ', ') AS phone_numbers,
        (SELECT COUNT(*) FROM rental_trans r WHERE r.user_id = u.user_id) AS total_user_rentals
    FROM user u
    LEFT JOIN phoner_number pn ON u.user_id = pn.user_id
    WHERE " . implode(" AND ", $where) . "
    GROUP BY u.user_id
    ORDER BY u.user_id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = "User & Staff Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User & Staff Management</h1>
        <p class="page-subtitle">Manage customer accounts, create staff operators, adjust demo wallets, and inspect profiles</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="header-action">
            <button type="button" class="btn btn-primary" onclick="toggleAddModal()">+ Add New User / Staff</button>
        </div>
    <?php endif; ?>
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

<!-- Search Filter Bar -->
<div class="filter-card card mb-4">
    <form action="users.php" method="GET" class="filter-form-grid">
        <div class="form-group" style="flex: 2;">
            <label class="form-label">Search Name, Email, NID</label>
            <input type="text" name="q" class="form-control" placeholder="Search by name, email, NID, ID..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">User Role</label>
            <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Roles --</option>
                <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customers Only</option>
                <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff Only</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins Only</option>
            </select>
        </div>

        <div class="form-group" style="align-self: flex-end;">
            <button type="submit" class="btn btn-primary">Filter Users</button>
            <a href="users.php" class="btn btn-secondary ml-1">Reset</a>
        </div>
    </form>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Registered Users (<?= count($users) ?> Total)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User Details</th>
                    <th>NID / DOB</th>
                    <th>Phones (<code>phoner_number</code>)</th>
                    <th>Role</th>
                    <th>Wallet</th>
                    <th>Revenue / Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong>#<?= $u['user_id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($u['Name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($u['Email']) ?></small>
                            </td>
                            <td>
                                <span class="font-mono text-sm"><?= htmlspecialchars($u['NID']) ?></span><br>
                                <small class="text-muted"><?= htmlspecialchars($u['DOB']) ?> (<?= htmlspecialchars($u['Sex']) ?>)</small>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($u['phone_numbers'] ?: 'None') ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $u['User_type'] === 'admin' ? 'badge-danger' : ($u['User_type'] === 'staff' ? 'badge-primary' : 'badge-success') ?>">
                                    <?= ucfirst($u['User_type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="font-bold text-success font-mono"><?= formatCurrency((float)$u['wallet']) ?></span>
                            </td>
                            <td>
                                <span class="font-bold text-primary font-mono"><?= formatCurrency((float)$u['revenue']) ?></span>
                            </td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <button type="button" class="btn btn-xs btn-outline-primary" 
                                        onclick='openEditModal(<?= json_encode($u) ?>)'>Edit</button>

                                    <button type="button" class="btn btn-xs btn-outline-success" 
                                        onclick="openCreditModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars(addslashes($u['Name'])) ?>')">+ ৳</button>

                                    <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                        <form action="users.php" method="POST" class="inline-form" onsubmit="return confirm('Delete user #<?= $u['user_id'] ?> (<?= htmlspecialchars(addslashes($u['Name'])) ?>)?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger">Del</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted text-xs">View Only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal" id="addUserModal" style="display:none;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create System User / Staff</h3>
                <button type="button" class="modal-close" onclick="toggleAddModal()">&times;</button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">National ID (NID) <span class="text-danger">*</span></label>
                            <input type="text" name="nid" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">User Role <span class="text-danger">*</span></label>
                            <select name="user_type" class="form-select" required>
                                <option value="customer" selected>Customer</option>
                                <option value="staff">Staff Operator</option>
                                <option value="admin">System Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Wallet Balance (Tk)</label>
                            <input type="number" step="10" name="wallet" class="form-control" value="100.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="2000-01-01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="sex" class="form-select">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" name="phone" class="form-control" placeholder="017XXXXXXXX">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="Dhaka, Bangladesh">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal" id="editUserModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit User #<span id="editModalUserIdTitle"></span></h3>
                <button type="button" class="modal-close" onclick="toggleEditModal()">&times;</button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="editUserId">

                    <div class="form-group mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">NID <span class="text-danger">*</span></label>
                        <input type="text" name="nid" id="editNid" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Account Role <span class="text-danger">*</span></label>
                        <select name="user_type" id="editRole" class="form-select" required>
                            <option value="customer">Customer</option>
                            <option value="staff">Staff Operator</option>
                            <option value="admin">System Administrator</option>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Wallet Balance (Tk) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="wallet" id="editWallet" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="editAddress" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Credit Demo Wallet -->
<div class="modal" id="creditModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Credit Wallet: <span id="creditUserName"></span></h3>
                <button type="button" class="modal-close" onclick="toggleCreditModal()">&times;</button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="credit_wallet">
                    <input type="hidden" name="user_id" id="creditUserId">

                    <p class="text-sm text-muted">Quickly add demo funds to customer's wallet balance for testing.</p>

                    <div class="form-group mb-3">
                        <label class="form-label">Credit Amount (Tk) <span class="text-danger">*</span></label>
                        <input type="number" step="10" name="credit_amount" class="form-control" value="200" min="10" max="5000" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleCreditModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddModal() {
    const m = document.getElementById('addUserModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function toggleEditModal() {
    const m = document.getElementById('editUserModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function toggleCreditModal() {
    const m = document.getElementById('creditModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function openEditModal(u) {
    document.getElementById('editModalUserIdTitle').innerText = u.user_id;
    document.getElementById('editUserId').value = u.user_id;
    document.getElementById('editName').value = u.Name;
    document.getElementById('editEmail').value = u.Email;
    document.getElementById('editNid').value = u.NID;
    document.getElementById('editRole').value = u.User_type;
    document.getElementById('editWallet').value = u.wallet;
    document.getElementById('editAddress').value = u.Address;
    toggleEditModal();
}

function openCreditModal(uid, name) {
    document.getElementById('creditUserId').value = uid;
    document.getElementById('creditUserName').innerText = name;
    toggleCreditModal();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
