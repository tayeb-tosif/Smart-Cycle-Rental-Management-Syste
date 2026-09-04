<?php
/**
 * Global Rental Logs (Admin & Staff)
 * Feature 4, 5 & 7: Rental & Admin Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffOrAdmin();

$statusFilter  = sanitizeInput($_GET['status'] ?? '');
$stationFilter = sanitizeInput($_GET['station'] ?? '');
$search        = sanitizeInput($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($statusFilter)) {
    $where[] = "r.status = :st";
    $params['st'] = $statusFilter;
}

if (!empty($stationFilter)) {
    $where[] = "(r.pickup_station = :stn OR r.return_station = :stn)";
    $params['stn'] = $stationFilter;
}

if (!empty($search)) {
    $where[] = "(u.Name LIKE :q OR u.Email LIKE :q OR b.QR_code LIKE :q OR r.trans_id = :qid)";
    $params['q'] = "%{$search}%";
    $params['qid'] = is_numeric($search) ? (int)$search : 0;
}

$sql = "
    SELECT 
        r.*,
        u.Name AS customer_name,
        u.Email AS customer_email,
        b.QR_code,
        TIMESTAMPDIFF(MINUTE, r.start_time, COALESCE(r.end_time, NOW())) AS duration_minutes,
        p.Bill,
        pt.payment_method
    FROM rental_trans r
    JOIN user u ON r.user_id = u.user_id
    JOIN bicycle b ON r.B_id = b.ID
    LEFT JOIN payment p ON r.payment_id = p.payment_id
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY r.trans_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rentals = $stmt->fetchAll();

// Fetch stations for filter dropdown
$stations = $pdo->query("SELECT Location FROM station ORDER BY Location ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Rental Transactions Log";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Global Rental Logs & Reports</h1>
        <p class="page-subtitle">Track real-time active trips, audit completed rental records, and inspect duration metrics</p>
    </div>
    <div class="header-action">
        <button onclick="window.print();" class="btn btn-secondary">🖨 Export / Print</button>
    </div>
</div>

<!-- Filter Card -->
<div class="filter-card card mb-4">
    <form action="rentals.php" method="GET" class="filter-form-grid">
        <div class="form-group" style="flex: 2;">
            <label class="form-label">Search Customer or QR Code</label>
            <input type="text" name="q" class="form-control" placeholder="Customer name, email, QR code, or Trans ID..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Trip Status</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Statuses --</option>
                <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active Trips Only</option>
                <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed Only</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Station Hub</label>
            <select name="station" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Stations --</option>
                <?php foreach ($stations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $stationFilter === $loc ? 'selected' : '' ?>>
                        📍 <?= htmlspecialchars($loc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="align-self: flex-end;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="rentals.php" class="btn btn-secondary ml-1">Reset</a>
        </div>
    </form>
</div>

<!-- Rentals Log Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Rental Transactions (<?= count($rentals) ?> Records)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Trans ID</th>
                    <th>Customer</th>
                    <th>Bicycle</th>
                    <th>Pickup Hub & Time</th>
                    <th>Return Hub & Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Bill Amount</th>
                    <th>Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">No rental records found matching criteria.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): 
                        $duration = (int)$r['duration_minutes'];
                    ?>
                        <tr>
                            <td><strong>#<?= $r['trans_id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($r['customer_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($r['customer_email']) ?></small>
                            </td>
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
                                    <span class="text-warning font-bold">● Active on Road</span>
                                <?php else: ?>
                                    📍 <?= htmlspecialchars($r['return_station'] ?? '—') ?><br>
                                    <small class="text-muted"><?= formatDateTime($r['end_time']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-bold"><?= formatDuration($duration) ?></span>
                            </td>
                            <td><?= getStatusBadge($r['status']) ?></td>
                            <td class="font-bold font-mono">
                                <?= $r['Bill'] !== null ? formatCurrency((float)$r['Bill']) : '<span class="text-warning">Pending</span>' ?>
                            </td>
                            <td>
                                <?php if ($r['payment_id']): ?>
                                    <a href="../payment.php?payment_id=<?= $r['payment_id'] ?>" class="btn btn-xs btn-outline-primary" target="_blank">Invoice ↗</a>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
