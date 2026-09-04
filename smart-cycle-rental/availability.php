<?php
/**
 * Real-Time Station Availability Board
 * Feature 2: Station & Availability Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch availability joined with station and ready cycles count
$stmt = $pdo->query("
    SELECT 
        s.Location,
        s.capacity,
        COALESCE(a.cycle_availability, 0) AS cycle_availability,
        (s.capacity - COALESCE(a.cycle_availability, 0)) AS occupied,
        (
            SELECT COUNT(*) 
            FROM bicycle b 
            LEFT JOIN condition_type c ON b.ID = c.B_id
            WHERE b.location = s.Location 
              AND b.user_id IS NULL
              AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))
        ) AS rent_ready
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    ORDER BY s.Location ASC
");
$records = $stmt->fetchAll();

// Summary stats
$totalCapacity = 0;
$totalAvailable = 0;
$totalOccupied = 0;

foreach ($records as $r) {
    $totalCapacity += (int)$r['capacity'];
    $totalAvailable += (int)$r['cycle_availability'];
    $totalOccupied += max(0, (int)$r['occupied']);
}

$pageTitle = "Real-Time Station Availability";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Live Station Availability</h1>
        <p class="page-subtitle">Monitored live from the <code>availability</code> and <code>station</code> database tables</p>
    </div>
    <div class="header-action">
        <button onclick="window.location.reload();" class="btn btn-secondary btn-sm">🔄 Refresh Live Data</button>
    </div>
</div>

<!-- Quick Network Metrics -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon bg-primary-soft">🚲</div>
        <div class="kpi-info">
            <span class="kpi-label">Available Bicycles</span>
            <h3 class="kpi-value text-primary"><?= $totalAvailable ?></h3>
            <span class="kpi-sub">Across all 5 stations</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon bg-warning-soft">🔒</div>
        <div class="kpi-info">
            <span class="kpi-label">Rented / In-Use</span>
            <h3 class="kpi-value text-warning"><?= $totalOccupied ?></h3>
            <span class="kpi-sub">Currently on active trips</span>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-icon bg-info-soft">🅿️</div>
        <div class="kpi-info">
            <span class="kpi-label">Total Dock Capacity</span>
            <h3 class="kpi-value text-info"><?= $totalCapacity ?></h3>
            <span class="kpi-sub">Total parking docks</span>
        </div>
    </div>
</div>

<!-- Availability Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Live Availability Status</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Station Location</th>
                    <th>Total Capacity</th>
                    <th>Available Cycles</th>
                    <th>Occupied / In-Use</th>
                    <th>Availability Meter</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No stations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $row): 
                        $cap = (int)$row['capacity'];
                        $avail = (int)$row['cycle_availability'];
                        $occupied = max(0, (int)$row['occupied']);
                        $pct = $cap > 0 ? min(100, round(($avail / $cap) * 100)) : 0;
                        
                        if ($avail >= 3) {
                            $statusBadge = '<span class="badge badge-success">Available</span>';
                        } elseif ($avail > 0) {
                            $statusBadge = '<span class="badge badge-warning">Limited</span>';
                        } else {
                            $statusBadge = '<span class="badge badge-danger">Full / Empty</span>';
                        }
                    ?>
                        <tr>
                            <td>
                                <strong>📍 <?= htmlspecialchars($row['Location']) ?></strong>
                            </td>
                            <td><?= $cap ?> Docks</td>
                            <td>
                                <span class="font-bold text-success font-mono"><?= $avail ?></span>
                                <?php if ($row['rent_ready'] < $avail): ?>
                                    <small class="text-muted" title="Some cycles are undergoing maintenance">(<?= $row['rent_ready'] ?> ready)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="font-mono text-muted"><?= $occupied ?></span>
                            </td>
                            <td style="min-width: 160px;">
                                <div class="progress-bar-track">
                                    <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $avail >= 3 ? 'var(--primary-color)' : ($avail > 0 ? 'var(--warning-color)' : 'var(--danger-color)') ?>;"></div>
                                </div>
                                <small class="text-muted"><?= $pct ?>% available</small>
                            </td>
                            <td><?= $statusBadge ?></td>
                            <td>
                                <?php if ($avail > 0 && $row['rent_ready'] > 0): ?>
                                    <a href="cycles.php?station=<?= urlencode($row['Location']) ?>" class="btn btn-sm btn-primary">Rent Now</a>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Unavailable</button>
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
