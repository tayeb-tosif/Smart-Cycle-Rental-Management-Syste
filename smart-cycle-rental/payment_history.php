<?php
/**
 * Customer Payment History
 * Feature 6: Payment & Coupon Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireCustomer();
$userId = (int)$_SESSION['user_id'];

// Fetch all payment records for current user
$sql = "
    SELECT 
        p.payment_id,
        p.trans_time,
        p.Bill,
        p.base_fee,
        p.additional_fee,
        p.discount,
        p.payment_status,
        p.B_id,
        b.QR_code,
        pt.payment_method,
        COALESCE(cd.coupon, 'NONE') AS applied_coupon,
        r.trans_id
    FROM payment p
    JOIN bicycle b ON p.B_id = b.ID
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    LEFT JOIN coupon_details cd ON p.payment_id = cd.payment_id
    LEFT JOIN rental_trans r ON p.payment_id = r.payment_id
    WHERE p.user_id = :uid
    ORDER BY p.payment_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $userId]);
$payments = $stmt->fetchAll();

// Total spent
$totalSpent = 0.00;
foreach ($payments as $p) {
    $totalSpent += (float)$p['Bill'];
}

$pageTitle = "My Payment History";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Payment History</h1>
        <p class="page-subtitle">Receipts, payment methods, and promo discount history for your rentals</p>
    </div>
    <div class="header-action">
        <span class="badge badge-lg badge-outline">Total Spent: <?= formatCurrency($totalSpent) ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Payment Transactions Ledger</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Transaction Time</th>
                    <th>Bicycle</th>
                    <th>Base + Additional</th>
                    <th>Coupon Applied</th>
                    <th>Method</th>
                    <th>Final Bill</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            No payment transactions recorded yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['payment_id'] ?></strong></td>
                            <td><?= formatDateTime($p['trans_time']) ?></td>
                            <td>
                                Cycle #<?= $p['B_id'] ?><br>
                                <small class="font-mono text-muted"><?= htmlspecialchars($p['QR_code']) ?></small>
                            </td>
                            <td>
                                <?= formatCurrency((float)$p['base_fee']) ?> + <?= formatCurrency((float)$p['additional_fee']) ?>
                            </td>
                            <td>
                                <?php if (!empty($p['applied_coupon']) && $p['applied_coupon'] !== 'NONE'): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($p['applied_coupon']) ?> (-<?= formatCurrency((float)$p['discount']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($p['payment_method'] ?? 'Wallet') ?></span>
                            </td>
                            <td class="font-bold text-primary font-mono" style="font-size: 1.05rem;">
                                <?= formatCurrency((float)$p['Bill']) ?>
                            </td>
                            <td>
                                <a href="payment.php?payment_id=<?= $p['payment_id'] ?>" class="btn btn-xs btn-outline-primary">View Invoice ↗</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
