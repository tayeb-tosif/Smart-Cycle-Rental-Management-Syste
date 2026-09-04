<?php
/**
 * Main Role Dispatcher & Customer Dashboard
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/pricing.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

// Route staff/admin to admin portal
if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'staff') {
    header("Location: admin/dashboard.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
refreshUserSession($pdo);

// Check if customer has an active ongoing rental
$activeRental = getActiveRental($userId, $pdo);

// Customer stats
$userStmt = $pdo->prepare("
    SELECT 
        wallet, 
        revenue,
        (SELECT COUNT(*) FROM rental_trans WHERE user_id = :uid1 AND status = 'Completed') AS completed_rides,
        (SELECT COUNT(*) FROM payment WHERE user_id = :uid2) AS total_payments
    FROM user 
    WHERE user_id = :uid3
");
$userStmt->execute(['uid1' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
$userStats = $userStmt->fetch();

// Station Availability Snapshot
$stations = $pdo->query("
    SELECT 
        s.Location, 
        s.capacity, 
        COALESCE(a.cycle_availability, 0) AS cycle_availability,
        (
            SELECT COUNT(*) FROM bicycle b 
            LEFT JOIN condition_type c ON b.ID = c.B_id
            WHERE b.location = s.Location 
              AND b.user_id IS NULL
              AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))
        ) AS ready_count
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    ORDER BY s.Location ASC
")->fetchAll();

// Recent Customer Rentals
$recentStmt = $pdo->prepare("
    SELECT 
        r.*, 
        b.QR_code, 
        p.Bill, 
        pt.payment_method
    FROM rental_trans r
    JOIN bicycle b ON r.B_id = b.ID
    LEFT JOIN payment p ON r.payment_id = p.payment_id
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    WHERE r.user_id = :uid
    ORDER BY r.trans_id DESC
    LIMIT 5
");
$recentStmt->execute(['uid' => $userId]);
$recentTrips = $recentStmt->fetchAll();

$pageTitle = "Customer Dashboard";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h1>
        <p class="page-subtitle">Ready to ride? Explore nearby cycle stations or manage your active trips</p>
    </div>
    <div class="header-action">
        <a href="cycles.php" class="btn btn-primary btn-lg">🚲 Rent a Bicycle</a>
    </div>
</div>

<!-- Active Trip Notification Card (if currently riding) -->
<?php if ($activeRental): 
    $startTimestamp = strtotime($activeRental['start_time']);
    $mins = max(1, (int) ceil((time() - $startTimestamp) / 60));
?>
    <div class="card active-ride-banner mb-4">
        <div class="active-banner-content">
            <div class="active-banner-icon">🚴</div>
            <div class="active-banner-info">
                <div class="d-flex align-items-center gap-2">
                    <span class="pulsing-dot"></span>
                    <h3 class="mb-0">Active Rental Trip in Progress</h3>
                </div>
                <p class="text-muted mb-0 mt-1">
                    Bicycle #<?= $activeRental['B_id'] ?> (<code><?= htmlspecialchars($activeRental['QR_code']) ?></code>) • 
                    Picked up at 📍 <?= htmlspecialchars($activeRental['pickup_station']) ?> • 
                    Started <?= formatDateTime($activeRental['start_time']) ?>
                </p>
            </div>
            <div class="active-banner-actions">
                <a href="my_rental.php" class="btn btn-outline-primary">Track Trip Live</a>
                <a href="return.php" class="btn btn-danger">Return & Pay</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Customer Quick Stats -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon bg-primary-soft">👛</div>
        <div class="kpi-info">
            <span class="kpi-label">Wallet Balance</span>
            <h3 class="kpi-value text-primary font-bold"><?= formatCurrency((float)$userStats['wallet']) ?></h3>
            <span class="kpi-sub"><a href="wallet.php" class="text-primary font-medium">Top-up Wallet →</a></span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-success-soft">🚴</div>
        <div class="kpi-info">
            <span class="kpi-label">Completed Trips</span>
            <h3 class="kpi-value text-success font-bold"><?= (int)$userStats['completed_rides'] ?></h3>
            <span class="kpi-sub"><a href="rental_history.php" class="text-success font-medium">View History →</a></span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-info-soft">💳</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Spent</span>
            <h3 class="kpi-value text-info font-bold"><?= formatCurrency((float)$userStats['revenue']) ?></h3>
            <span class="kpi-sub"><a href="payment_history.php" class="text-info font-medium">Invoices →</a></span>
        </div>
    </div>
</div>

<div class="grid grid-3 mt-4">
    <!-- Left: Station Availability Hubs -->
    <div class="card card-span-2">
        <div class="card-header">
            <h3 class="card-title">Nearby Station Hubs</h3>
            <a href="availability.php" class="btn btn-sm btn-outline-primary">Live Availability →</a>
        </div>
        <div class="grid grid-2 p-3">
            <?php foreach ($stations as $st): 
                $avail = (int)$st['cycle_availability'];
                $ready = (int)$st['ready_count'];
                $cap   = (int)$st['capacity'];
                $pct   = $cap > 0 ? min(100, round(($avail / $cap) * 100)) : 0;
            ?>
                <div class="station-dashboard-box">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>📍 <?= htmlspecialchars($st['Location']) ?></strong>
                        <span class="badge <?= $ready > 0 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $ready > 0 ? "{$ready} Ready" : 'Empty' ?>
                        </span>
                    </div>
                    <div class="progress-bar-track mb-2">
                        <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $ready > 0 ? 'var(--primary-color)' : 'var(--danger-color)' ?>;"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted"><?= $avail ?> / <?= $cap ?> Docks Occupied</small>
                        <?php if ($ready > 0 && !$activeRental): ?>
                            <a href="cycles.php?station=<?= urlencode($st['Location']) ?>" class="btn btn-xs btn-primary">Rent Here</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Quick Actions & Help -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="quick-links-group">
            <a href="cycles.php" class="btn btn-outline-primary btn-block mb-2">🚲 Browse All Bicycles</a>
            <a href="stations.php" class="btn btn-outline-secondary btn-block mb-2">📍 Find Station Locations</a>
            <a href="wallet.php" class="btn btn-outline-success btn-block mb-2">👛 Top-Up Smart Wallet</a>
            <a href="rental_history.php" class="btn btn-outline-info btn-block mb-2">📜 Past Rental Trips</a>
            <a href="profile.php" class="btn btn-outline-secondary btn-block">⚙ Account Profile & Phones</a>
        </div>

        <hr class="card-divider">

        <div class="pricing-mini-badge text-center">
            <span class="text-xs uppercase text-muted font-bold">Standard Pricing Rate</span>
            <h4 class="text-primary mt-1 mb-1">20 Tk Base + 5 Tk / 30m</h4>
            <small class="text-muted">Use code <code>WELCOME10</code> for 10% off!</small>
        </div>
    </div>
</div>

<!-- Recent Trips Table -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Recent Rental Trips</h3>
        <a href="rental_history.php" class="btn btn-sm btn-outline-primary">View All →</a>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Trans ID</th>
                    <th>Bicycle</th>
                    <th>Pickup</th>
                    <th>Return</th>
                    <th>Start Time</th>
                    <th>Status</th>
                    <th>Bill</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentTrips)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No trips taken yet. Ready to ride?</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentTrips as $r): ?>
                        <tr>
                            <td><strong>#<?= $r['trans_id'] ?></strong></td>
                            <td>Cycle #<?= $r['B_id'] ?> (<code><?= htmlspecialchars($r['QR_code']) ?></code>)</td>
                            <td>📍 <?= htmlspecialchars($r['pickup_station'] ?? 'Station') ?></td>
                            <td>📍 <?= htmlspecialchars($r['return_station'] ?? '—') ?></td>
                            <td><small><?= formatDateTime($r['start_time']) ?></small></td>
                            <td><?= getStatusBadge($r['status']) ?></td>
                            <td class="font-bold">
                                <?= $r['Bill'] !== null ? formatCurrency((float)$r['Bill']) : '<span class="text-warning">In-Use</span>' ?>
                            </td>
                            <td>
                                <?php if ($r['payment_id']): ?>
                                    <a href="payment.php?payment_id=<?= $r['payment_id'] ?>" class="btn btn-xs btn-outline-primary">Receipt ↗</a>
                                <?php elseif ($r['status'] === 'Active'): ?>
                                    <a href="return.php" class="btn btn-xs btn-danger">Return</a>
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
