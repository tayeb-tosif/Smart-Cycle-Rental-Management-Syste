<?php
/**
 * Bicycle Catalog & Search
 * Feature 3: Bicycle Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Filter parameters
$selectedStation   = sanitizeInput($_GET['station'] ?? '');
$selectedCondition = sanitizeInput($_GET['condition'] ?? '');
$selectedStatus    = sanitizeInput($_GET['status'] ?? 'available'); // 'all', 'available', 'rented'
$searchQuery       = sanitizeInput($_GET['q'] ?? '');

// Build query
$where = ["1=1"];
$params = [];

if (!empty($selectedStation)) {
    $where[] = "b.location = :station";
    $params['station'] = $selectedStation;
}

if (!empty($selectedCondition)) {
    $where[] = "c.condition = :cond";
    $params['cond'] = $selectedCondition;
}

if ($selectedStatus === 'available') {
    $where[] = "b.user_id IS NULL AND b.location IS NOT NULL AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))";
} elseif ($selectedStatus === 'rented') {
    $where[] = "b.user_id IS NOT NULL";
} elseif ($selectedStatus === 'maintenance') {
    $where[] = "c.condition IN ('Damaged', 'Under Maintenance')";
}

if (!empty($searchQuery)) {
    $where[] = "(b.QR_code LIKE :q OR b.ID = :qid)";
    $params['q'] = "%{$searchQuery}%";
    $params['qid'] = is_numeric($searchQuery) ? (int)$searchQuery : 0;
}

$sql = "
    SELECT 
        b.ID,
        b.QR_code,
        b.location,
        b.locked,
        b.unlocked,
        b.s_location,
        b.user_id,
        u.Name AS rider_name,
        COALESCE(c.condition, 'Good') AS cycle_condition,
        c.last_checked
    FROM bicycle b
    LEFT JOIN condition_type c ON b.ID = c.B_id
    LEFT JOIN user u ON b.user_id = u.user_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY b.ID ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bicycles = $stmt->fetchAll();

// Fetch stations for filter dropdown
$stations = $pdo->query("SELECT Location FROM station ORDER BY Location ASC")->fetchAll(PDO::FETCH_COLUMN);

// Check if current user has an active ride
$activeRental = isLoggedIn() ? getActiveRental((int)$_SESSION['user_id'], $pdo) : null;

$pageTitle = "Browse Bicycles";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Find & Rent Bicycles</h1>
        <p class="page-subtitle">Smart cycle fleet stationed across campus and transit points</p>
    </div>
    <?php if ($activeRental): ?>
        <div class="header-action">
            <a href="my_rental.php" class="btn btn-warning">🚴 Active Ride in Progress (Bike #<?= $activeRental['B_id'] ?>)</a>
        </div>
    <?php endif; ?>
</div>

<!-- Search & Filter Filter-Bar -->
<div class="filter-card card mb-4">
    <form action="cycles.php" method="GET" class="filter-form-grid">
        <div class="form-group">
            <label class="form-label">Station Hub</label>
            <select name="station" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Stations --</option>
                <?php foreach ($stations as $loc): ?>
                    <option value="<?= htmlspecialchars($loc) ?>" <?= $selectedStation === $loc ? 'selected' : '' ?>>
                        📍 <?= htmlspecialchars($loc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Condition</label>
            <select name="condition" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Conditions --</option>
                <option value="Excellent" <?= $selectedCondition === 'Excellent' ? 'selected' : '' ?>>Excellent</option>
                <option value="Good" <?= $selectedCondition === 'Good' ? 'selected' : '' ?>>Good</option>
                <option value="Fair" <?= $selectedCondition === 'Fair' ? 'selected' : '' ?>>Fair</option>
                <option value="Under Maintenance" <?= $selectedCondition === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Availability</label>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="available" <?= $selectedStatus === 'available' ? 'selected' : '' ?>>Available Only</option>
                <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>All Bicycles</option>
                <option value="rented" <?= $selectedStatus === 'rented' ? 'selected' : '' ?>>Currently In-Use</option>
                <option value="maintenance" <?= $selectedStatus === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Search QR / ID</label>
            <div class="input-group">
                <input type="text" name="q" class="form-control" placeholder="e.g. QR-BRAC or 1" value="<?= htmlspecialchars($searchQuery) ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </div>
    </form>
</div>

<!-- Bicycles Fleet Grid -->
<div class="grid grid-3">
    <?php if (empty($bicycles)): ?>
        <div class="card card-span-3 text-center py-5">
            <div style="font-size: 3rem; margin-bottom: 15px;">🚲</div>
            <h3>No bicycles found</h3>
            <p class="text-muted">Try adjusting your station or condition filters.</p>
            <a href="cycles.php" class="btn btn-outline-primary mt-3">Reset All Filters</a>
        </div>
    <?php else: ?>
        <?php foreach ($bicycles as $bike): 
            $isRented = !empty($bike['user_id']);
            $isMaintenance = in_array($bike['cycle_condition'], ['Damaged', 'Under Maintenance'], true);
            $canRent = !$isRented && !$isMaintenance && !empty($bike['location']);
        ?>
            <div class="card cycle-card <?= $isRented ? 'cycle-rented' : ($isMaintenance ? 'cycle-maintenance' : '') ?>">
                <div class="cycle-card-header">
                    <div class="cycle-id-pill">Cycle #<?= $bike['ID'] ?></div>
                    <div><?= getConditionBadge($bike['cycle_condition']) ?></div>
                </div>

                <div class="cycle-visual">
                    <span class="cycle-big-icon">🚲</span>
                    <div class="cycle-qr-code">
                        <code><?= htmlspecialchars($bike['QR_code']) ?></code>
                    </div>
                </div>

                <div class="cycle-details">
                    <div class="cycle-meta-row">
                        <span class="cycle-meta-label">Current Location:</span>
                        <span class="cycle-meta-val font-bold">
                            <?= $bike['location'] ? '📍 ' . htmlspecialchars($bike['location']) : '<span class="text-warning">On Road (In-Use)</span>' ?>
                        </span>
                    </div>

                    <div class="cycle-meta-row">
                        <span class="cycle-meta-label">Base Station:</span>
                        <span class="cycle-meta-val text-muted"><?= htmlspecialchars($bike['s_location'] ?? 'Not set') ?></span>
                    </div>

                    <div class="cycle-meta-row">
                        <span class="cycle-meta-label">Lock Status:</span>
                        <span class="cycle-meta-val">
                            <?php if ($bike['locked'] == 1): ?>
                                <span class="badge badge-success">🔒 Locked (Docked)</span>
                            <?php else: ?>
                                <span class="badge badge-warning">🔓 Unlocked (Riding)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="cycle-card-actions">
                    <?php if ($canRent): ?>
                        <?php if ($activeRental): ?>
                            <button class="btn btn-secondary btn-block" disabled title="You already have an active rental">Already in Active Ride</button>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-primary btn-block">Sign In to Rent</a>
                        <?php elseif ($_SESSION['user_type'] !== 'customer'): ?>
                            <a href="admin/bicycles.php" class="btn btn-outline-secondary btn-block">Manage Cycle (Staff)</a>
                        <?php else: ?>
                            <a href="rent.php?bike_id=<?= $bike['ID'] ?>" class="btn btn-primary btn-block btn-lg">
                                🚲 Rent This Cycle
                            </a>
                        <?php endif; ?>
                    <?php elseif ($isRented): ?>
                        <div class="alert alert-info text-center py-2 mb-0" style="font-size: 0.85rem;">
                            Rented by <?= htmlspecialchars($bike['rider_name'] ?? 'Active Rider') ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger text-center py-2 mb-0" style="font-size: 0.85rem;">
                            Unavailable: <?= htmlspecialchars($bike['cycle_condition']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
