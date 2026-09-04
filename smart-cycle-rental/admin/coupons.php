<?php
/**
 * Coupon Management (Admin)
 * Feature 6: Payment & Coupon Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Only Admin can manage coupons
requireAdmin();

$errors = [];
$success = '';

// Handle Add Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_coupon') {
    $code       = strtoupper(sanitizeInput($_POST['coupon_code'] ?? ''));
    $percentage = (int)($_POST['discount_percentage'] ?? 10);
    $minAmount  = (float)($_POST['min_amount'] ?? 0.00);
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    if (empty($code)) {
        $errors[] = "Coupon code is required.";
    }
    if ($percentage < 1 || $percentage > 100) {
        $errors[] = "Discount percentage must be between 1% and 100%.";
    }
    if ($minAmount < 0) {
        $errors[] = "Minimum amount cannot be negative.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO coupons (coupon_code, discount_percentage, min_amount, is_active)
                VALUES (:code, :pct, :min_amt, :active)
            ");
            $stmt->execute([
                'code'    => $code,
                'pct'     => $percentage,
                'min_amt' => $minAmount,
                'active'  => $isActive
            ]);

            setFlashMessage('success', "Coupon '{$code}' created successfully.");
            header("Location: coupons.php");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "Coupon code '{$code}' already exists.";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Toggle Active Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $code = sanitizeInput($_POST['coupon_code'] ?? '');
    try {
        $stmt = $pdo->prepare("UPDATE coupons SET is_active = IF(is_active = 1, 0, 1) WHERE coupon_code = :code");
        $stmt->execute(['code' => $code]);
        setFlashMessage('success', "Coupon '{$code}' status updated.");
        header("Location: coupons.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Failed to update coupon status: " . $e->getMessage();
    }
}

// Handle Delete Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_coupon') {
    $code = sanitizeInput($_POST['coupon_code'] ?? '');
    try {
        $stmt = $pdo->prepare("DELETE FROM coupons WHERE coupon_code = :code");
        $stmt->execute(['code' => $code]);
        setFlashMessage('success', "Coupon '{$code}' deleted.");
        header("Location: coupons.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Failed to delete coupon: " . $e->getMessage();
    }
}

// Fetch all coupons with usage count
$stmt = $pdo->query("
    SELECT 
        c.*,
        (SELECT COUNT(*) FROM coupon_details cd WHERE cd.coupon = c.coupon_code) AS times_used
    FROM coupons c
    ORDER BY c.created_at DESC
");
$coupons = $stmt->fetchAll();

$pageTitle = "Coupon Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Discount Coupon Management</h1>
        <p class="page-subtitle">Create promotional codes, configure discount percentages, and track customer usage</p>
    </div>
    <div class="header-action">
        <button type="button" class="btn btn-primary" onclick="toggleAddModal()">+ Create New Coupon</button>
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

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Promotional Coupons Catalog</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Coupon Code</th>
                    <th>Discount Rate</th>
                    <th>Minimum Spend</th>
                    <th>Usage Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coupons)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No coupons registered.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($coupons as $cp): ?>
                        <tr>
                            <td>
                                <strong class="font-mono" style="font-size: 1.1rem; color: var(--primary-color);">
                                    🎟 <?= htmlspecialchars($cp['coupon_code']) ?>
                                </strong>
                            </td>
                            <td>
                                <span class="badge badge-success"><?= (int)$cp['discount_percentage'] ?>% OFF</span>
                            </td>
                            <td>
                                <?= (float)$cp['min_amount'] > 0 ? formatCurrency((float)$cp['min_amount']) : '<span class="text-muted">No Minimum</span>' ?>
                            </td>
                            <td>
                                <strong><?= (int)$cp['times_used'] ?></strong> times
                            </td>
                            <td>
                                <?php if ($cp['is_active'] == 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive / Expired</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="coupons.php" method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($cp['coupon_code']) ?>">
                                    <?php if ($cp['is_active'] == 1): ?>
                                        <button type="submit" class="btn btn-xs btn-outline-warning">Deactivate</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-xs btn-outline-success">Activate</button>
                                    <?php endif; ?>
                                </form>

                                <form action="coupons.php" method="POST" class="inline-form" onsubmit="return confirm('Delete coupon <?= htmlspecialchars($cp['coupon_code']) ?>?');">
                                    <input type="hidden" name="action" value="delete_coupon">
                                    <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($cp['coupon_code']) ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Coupon -->
<div class="modal" id="addCouponModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Promo Coupon</h3>
                <button type="button" class="modal-close" onclick="toggleAddModal()">&times;</button>
            </div>
            <form action="coupons.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_coupon">

                    <div class="form-group mb-3">
                        <label class="form-label">Coupon Promo Code <span class="text-danger">*</span></label>
                        <input type="text" name="coupon_code" class="form-control font-mono uppercase" placeholder="e.g. FLASH30" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Discount Percentage (%) <span class="text-danger">*</span></label>
                        <input type="number" name="discount_percentage" class="form-control" min="1" max="100" value="15" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Minimum Bill Amount (Tk)</label>
                        <input type="number" step="1" min="0" name="min_amount" class="form-control" value="0.00">
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_active" id="isActiveCheck" class="form-check-input" value="1" checked>
                        <label for="isActiveCheck" class="form-check-label">Activate coupon immediately upon creation</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Coupon</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddModal() {
    const m = document.getElementById('addCouponModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
