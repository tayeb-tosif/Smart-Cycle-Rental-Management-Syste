<?php
/**
 * Admin & Staff Central Dashboard
 * Feature 7: Admin Management & Reports
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffOrAdmin();
$currentRole = $_SESSION['user_type'];

// Fetch KPI Statistics
$stats = [];

// 1. Total Users
$stats['total_users'] = (int)$pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
// 2. Customers
$stats['total_customers'] = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE User_type = 'customer'")->fetchColumn();
// 3. Staff
$stats['total_staff'] = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE User_type = 'staff'")->fetchColumn();
// 4. Bicycles
$stats['total_bicycles'] = (int)$pdo->query("SELECT COUNT(*) FROM bicycle")->fetchColumn();
// 5. Available Bicycles
$stats['available_bicycles'] = (int)$pdo->query("
    SELECT COUNT(*) FROM bicycle b 
    LEFT JOIN condition_type c ON b.ID = c.B_id 
    WHERE b.user_id IS NULL AND b.location IS NOT NULL 
      AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))
")->fetchColumn();
// 6. Rented Bicycles
$stats['rented_bicycles'] = (int)$pdo->query("SELECT COUNT(*) FROM bicycle WHERE user_id IS NOT NULL")->fetchColumn();
// 7. Stations
$stats['total_stations'] = (int)$pdo->query("SELECT COUNT(*) FROM station")->fetchColumn();
// 8. Total Rentals
$stats['total_rentals'] = (int)$pdo->query("SELECT COUNT(*) FROM rental_trans")->fetchColumn();
// 9. Total Revenue
$stats['total_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(Bill), 0.00) FROM payment WHERE payment_status = 'Paid'")->fetchColumn();

// Fetch Recent Rentals
$recentRentals = $pdo->query("
    SELECT 
        r.*, 
        u.Name AS customer_name, 
        u.Email AS customer_email,
        b.QR_code,
        p.Bill,
        pt.payment_method
    FROM rental_trans r
    JOIN user u ON r.user_id = u.user_id
    JOIN bicycle b ON r.B_id = b.ID
    LEFT JOIN payment p ON r.payment_id = p.payment_id
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    ORDER BY r.trans_id DESC
    LIMIT 6
")->fetchAll();

// Fetch Station Availability Snapshot
$stationSnapshot = $pdo->query("
    SELECT s.Location, s.capacity, COALESCE(a.cycle_availability, 0) AS cycle_availability
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    ORDER BY s.Location ASC
")->fetchAll();

$pageTitle = "Admin & Staff Dashboard";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= ucfirst($currentRole) ?> Command Center</h1>
        <p class="page-subtitle">Smart Cycle Rental Management System — Campus & City Operations</p>
    </div>
    <div class="header-action">
        <a href="bicycles.php" class="btn btn-primary">+ Manage Bicycles</a>
        <a href="stations.php" class="btn btn-outline-secondary">+ Manage Stations</a>
    </div>
</div>

<!-- 9 Key Performance Indicator (KPI) Metric Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon bg-success-soft">💰</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Revenue</span>
            <h3 class="kpi-value text-success font-bold"><?= formatCurrency($stats['total_revenue']) ?></h3>
            <span class="kpi-sub">Total payments collected</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-primary-soft">🚴</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Rentals</span>
            <h3 class="kpi-value text-primary font-bold"><?= $stats['total_rentals'] ?></h3>
            <span class="kpi-sub">Trips completed & active</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-info-soft">🚲</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Bicycles</span>
            <h3 class="kpi-value text-info font-bold"><?= $stats['total_bicycles'] ?></h3>
            <span class="kpi-sub"><?= $stats['available_bicycles'] ?> available / <?= $stats['rented_bicycles'] ?> rented</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-warning-soft">🔓</div>
        <div class="kpi-info">
            <span class="kpi-label">Currently In-Use</span>
            <h3 class="kpi-value text-warning font-bold"><?= $stats['rented_bicycles'] ?></h3>
            <span class="kpi-sub">Active riders on road</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-success-soft">📍</div>
        <div class="kpi-info">
            <span class="kpi-label">Station Hubs</span>
            <h3 class="kpi-value text-success font-bold"><?= $stats['total_stations'] ?></h3>
            <span class="kpi-sub">Active transit stations</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-primary-soft">👥</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Accounts</span>
            <h3 class="kpi-value text-primary font-bold"><?= $stats['total_users'] ?></h3>
            <span class="kpi-sub"><?= $stats['total_customers'] ?> Customers / <?= $stats['total_staff'] ?> Staff</span>
        </div>
    </div>
</div>

<div class="grid grid-3 mt-4">
    <!-- Recent Rentals Table -->
    <div class="card card-span-2">
        <div class="card-header">
            <h3 class="card-title">Recent Rental Trips</h3>
            <a href="rentals.php" class="btn btn-sm btn-outline-primary">View All Rentals →</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Trans ID</th>
                        <th>Customer</th>
                        <th>Bicycle</th>
                        <th>Pickup Station</th>
                        <th>Start Time</th>
                        <th>Status</th>
                        <th>Bill</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRentals)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No rental records yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRentals as $r): ?>
                            <tr>
                                <td><strong>#<?= $r['trans_id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($r['customer_email']) ?></small>
                                </td>
                                <td>Cycle #<?= $r['B_id'] ?></td>
                                <td>📍 <?= htmlspecialchars($r['pickup_station']) ?></td>
                                <td><small><?= formatDateTime($r['start_time']) ?></small></td>
                                <td><?= getStatusBadge($r['status']) ?></td>
                                <td class="font-bold">
                                    <?= $r['Bill'] !== null ? formatCurrency((float)$r['Bill']) : '<span class="text-warning">In-Use</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Station Live Snapshot -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Station Dock Snapshot</h3>
            <a href="stations.php" class="btn btn-sm btn-outline-secondary">Manage</a>
        </div>
        <div class="station-mini-list">
            <?php foreach ($stationSnapshot as $st): 
                $cap = (int)$st['capacity'];
                $avail = (int)$st['cycle_availability'];
                $pct = $cap > 0 ? min(100, round(($avail / $cap) * 100)) : 0;
            ?>
                <div class="station-mini-item mb-3">
                    <div class="d-flex justify-content-between text-sm mb-1">
                        <strong>📍 <?= htmlspecialchars($st['Location']) ?></strong>
                        <span class="font-bold font-mono"><?= $avail ?> / <?= $cap ?></span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $avail > 3 ? 'var(--primary-color)' : ($avail > 0 ? 'var(--warning-color)' : 'var(--danger-color)') ?>;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <hr class="card-divider">

        <div class="quick-links-group">
            <h4 class="text-sm font-bold mb-2">Operations Quick Actions</h4>
            <a href="bicycles.php" class="btn btn-sm btn-outline-primary btn-block mb-2">🚲 Inspect Fleet & Locks</a>
            <a href="payments.php" class="btn btn-sm btn-outline-success btn-block mb-2">💳 Review Financial Ledger</a>
            <?php if ($currentRole === 'admin'): ?>
                <a href="coupons.php" class="btn btn-sm btn-outline-info btn-block mb-2">🎟 Manage Promo Coupons</a>
                <a href="users.php" class="btn btn-sm btn-outline-secondary btn-block">👥 Manage System Users</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
