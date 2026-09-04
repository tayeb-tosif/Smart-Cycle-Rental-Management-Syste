<?php
/**
 * Payment Invoice & Receipt View
 * Feature 6: Payment & Coupon Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/pricing.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_type'];

$paymentId = (int)($_GET['payment_id'] ?? 0);

if ($paymentId <= 0) {
    setFlashMessage('danger', "Invalid payment invoice ID.");
    header("Location: dashboard.php");
    exit;
}

// Fetch complete payment details with joined rental, user, payment_type, coupon_details, and bicycle
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
        p.user_id,
        u.Name AS customer_name,
        u.Email AS customer_email,
        u.NID AS customer_nid,
        pt.payment_method,
        COALESCE(cd.coupon, 'NONE') AS applied_coupon,
        r.trans_id,
        r.start_time,
        r.end_time,
        r.pickup_station,
        r.return_station,
        TIMESTAMPDIFF(MINUTE, r.start_time, r.end_time) AS duration_minutes,
        b.QR_code
    FROM payment p
    JOIN user u ON p.user_id = u.user_id
    JOIN bicycle b ON p.B_id = b.ID
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    LEFT JOIN coupon_details cd ON p.payment_id = cd.payment_id
    LEFT JOIN rental_trans r ON p.payment_id = r.payment_id
    WHERE p.payment_id = :pid
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['pid' => $paymentId]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlashMessage('danger', "Payment invoice not found.");
    header("Location: dashboard.php");
    exit;
}

// Customers can only see their own invoices; Staff & Admin can view any
if ($userRole === 'customer' && (int)$invoice['user_id'] !== $userId) {
    setFlashMessage('danger', "Access denied.");
    header("Location: dashboard.php");
    exit;
}

$duration = max(1, (int)$invoice['duration_minutes']);

$pageTitle = "Payment Invoice #{$invoice['payment_id']}";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header no-print">
    <div>
        <h1 class="page-title">Payment Receipt & Invoice</h1>
        <p class="page-subtitle">Official payment receipt for Rental Transaction #<?= htmlspecialchars($invoice['trans_id'] ?? '—') ?></p>
    </div>
    <div class="header-action">
        <button onclick="window.print();" class="btn btn-secondary">🖨 Print Receipt</button>
        <?php if ($userRole === 'customer'): ?>
            <a href="dashboard.php" class="btn btn-primary">Return to Dashboard</a>
        <?php else: ?>
            <a href="admin/payments.php" class="btn btn-primary">Back to Payment Ledger</a>
        <?php endif; ?>
    </div>
</div>

<div class="invoice-container">
    <div class="card invoice-card">
        <!-- Invoice Header -->
        <div class="invoice-header-row">
            <div>
                <div class="brand-logo" style="margin-bottom: 8px;">
                    <span class="logo-icon">🚲</span>
                    <span class="logo-text">Smart<strong>Cycle</strong></span>
                </div>
                <p class="text-muted text-sm">
                    Sustainable Campus & City Cycle Sharing Network<br>
                    CSE Database Management System Project
                </p>
            </div>
            <div class="text-right">
                <span class="badge badge-lg badge-success"><?= htmlspecialchars($invoice['payment_status']) ?></span>
                <h3 class="invoice-id-text font-mono mt-2">INVOICE #<?= str_pad($invoice['payment_id'], 5, '0', STR_PAD_LEFT) ?></h3>
                <p class="text-muted text-sm">Date: <?= formatDateTime($invoice['trans_time']) ?></p>
            </div>
        </div>

        <hr class="card-divider">

        <!-- Billed Customer & Rental Details Grid -->
        <div class="grid grid-2 mb-4">
            <div class="invoice-meta-box">
                <span class="text-muted text-xs font-bold uppercase">Customer Information</span>
                <h4 class="mt-1"><?= htmlspecialchars($invoice['customer_name']) ?></h4>
                <p class="text-sm text-muted mb-0">Email: <?= htmlspecialchars($invoice['customer_email']) ?></p>
                <p class="text-sm text-muted mb-0">NID: <?= htmlspecialchars($invoice['customer_nid']) ?></p>
            </div>
            <div class="invoice-meta-box">
                <span class="text-muted text-xs font-bold uppercase">Ride Particulars</span>
                <p class="text-sm mb-1 mt-1"><strong>Bicycle:</strong> Cycle #<?= $invoice['B_id'] ?> (<code><?= htmlspecialchars($invoice['QR_code']) ?></code>)</p>
                <p class="text-sm mb-1"><strong>Pickup:</strong> 📍 <?= htmlspecialchars($invoice['pickup_station'] ?? 'Station') ?> (<?= formatDateTime($invoice['start_time']) ?>)</p>
                <p class="text-sm mb-1"><strong>Return:</strong> 📍 <?= htmlspecialchars($invoice['return_station'] ?? 'Station') ?> (<?= formatDateTime($invoice['end_time']) ?>)</p>
                <p class="text-sm mb-0"><strong>Total Ride Time:</strong> <?= formatDuration($duration) ?></p>
            </div>
        </div>

        <!-- Line Item Breakdown Table -->
        <div class="table-responsive mb-4">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fee Breakdown Description</th>
                        <th>Rate / Units</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Base Rental Fare</strong>
                            <div class="text-muted text-xs">Standard cycle unlock and first 30 minutes of ride</div>
                        </td>
                        <td>Fixed Base</td>
                        <td class="text-right"><?= formatCurrency((float)$invoice['base_fee']) ?></td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Duration Fare (Additional Time)</strong>
                            <div class="text-muted text-xs">Computed at 5 Tk per 30 minutes (<?= ceil($duration / PRICING_MINUTES_PER_SLOT) ?> slot(s))</div>
                        </td>
                        <td><?= $duration ?> Minutes</td>
                        <td class="text-right"><?= formatCurrency((float)$invoice['additional_fee']) ?></td>
                    </tr>
                    <?php if ((float)$invoice['discount'] > 0): ?>
                        <tr class="text-success">
                            <td>
                                <strong>Promo Coupon Discount (<code><?= htmlspecialchars($invoice['applied_coupon']) ?></code>)</strong>
                            </td>
                            <td>Applied Promo</td>
                            <td class="text-right">- <?= formatCurrency((float)$invoice['discount']) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="invoice-total-row">
                        <td colspan="2" class="text-right"><strong>Total Paid Amount:</strong></td>
                        <td class="text-right font-bold text-primary" style="font-size: 1.25rem;">
                            <?= formatCurrency((float)$invoice['Bill']) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payment Method Details -->
        <div class="invoice-footer-box">
            <div class="grid grid-2">
                <div>
                    <span class="text-muted text-xs font-bold uppercase">Payment Method</span>
                    <p class="font-bold text-primary mb-0 mt-1">
                        💳 <?= htmlspecialchars($invoice['payment_method'] ?? 'Wallet') ?>
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-muted text-xs font-bold uppercase">Transaction ID</span>
                    <p class="font-mono text-sm mb-0 mt-1">TXN-<?= str_pad($invoice['payment_id'], 8, '0', STR_PAD_LEFT) ?></p>
                </div>
            </div>
        </div>

        <div class="invoice-note text-center mt-4 text-muted text-sm">
            Thank you for riding green with Smart Cycle Rental! 🚲<br>
            <small>Computer Science & Engineering Database Management System Project</small>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
