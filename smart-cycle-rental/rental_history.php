<?php
/**
 * Customer Rental History
 * Feature 4 & 5: Cycle Rental & Return Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireCustomer();
$userId = (int)$_SESSION['user_id'];

// Fetch all rentals for the current customer
$sql = "
    SELECT 
        r.trans_id,
        r.B_id,
        r.payment_id,
        r.start_time,
        r.end_time,
        r.pickup_station,
        r.return_station,
        r.status,
        TIMESTAMPDIFF(MINUTE, r.start_time, COALESCE(r.end_time, NOW())) AS duration_minutes,
        b.QR_code,
        p.Bill,
        p.payment_status,
        pt.payment_method
    FROM rental_trans r
    JOIN bicycle b ON r.B_id = b.ID
    LEFT JOIN payment p ON r.payment_id = p.payment_id
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    WHERE r.user_id = :uid
    ORDER BY r.trans_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $userId]);
$rentals = $stmt->fetchAll();

$pageTitle = "My Rental History";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Rental Trips</h1>
        <p class="page-subtitle">Complete history of all bicycle rental journeys and duration logs</p>
    </div>
    <div class="header-action">
        <a href="cycles.php" class="btn btn-primary">+ Rent a Cycle</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Rental Transactions</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Trans ID</th>
                    <th>Bicycle</th>
                    <th>Pickup Station & Time</th>
                    <th>Return Station & Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Total Bill</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <p>No rental history found. Start your first green ride today!</p>
                            <a href="cycles.php" class="btn btn-primary mt-2">Browse Cycles</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): 
                        $duration = (int)$r['duration_minutes'];
                    ?>
                        <tr>
                            <td><strong>#<?= $r['trans_id'] ?></strong></td>
                            <td>
                                <strong>Cycle #<?= $r['B_id'] ?></strong><br>
                                <small class="font-mono text-muted"><?= htmlspecialchars($r['QR_code']) ?></small>
                            </td>
                            <td>
                                📍 <?= htmlspecialchars($r['pickup_station'] ?? 'Station') ?><br>
                                <small class="text-muted"><?= formatDateTime($r['start_time']) ?></small>
                            </td>
                            <td>
                                <?php if ($r['status'] === 'Active'): ?>
                                    <span class="text-warning font-bold">● In Transit</span>
                                <?php else: ?>
                                    📍 <?= htmlspecialchars($r['return_station'] ?? '—') ?><br>
                                    <small class="text-muted"><?= formatDateTime($r['end_time']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-bold"><?= formatDuration($duration) ?></span>
                            </td>
                            <td>
                                <?= getStatusBadge($r['status']) ?>
                            </td>
                            <td>
                                <?php if ($r['Bill'] !== null): ?>
                                    <span class="font-bold text-primary"><?= formatCurrency((float)$r['Bill']) ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($r['payment_method'] ?? 'Paid') ?></small>
                                <?php else: ?>
                                    <span class="text-warning">In Progress</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['payment_id']): ?>
                                    <a href="payment.php?payment_id=<?= $r['payment_id'] ?>" class="btn btn-xs btn-outline-primary">Receipt ↗</a>
                                <?php elseif ($r['status'] === 'Active'): ?>
                                    <a href="return.php" class="btn btn-xs btn-danger">Return Now</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
