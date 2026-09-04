<?php
/**
 * Stations Directory
 * Feature 2: Station & Availability Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch all stations with their current availability and manager info
$stmt = $pdo->query("
    SELECT 
        s.Location,
        s.capacity,
        s.User_id,
        u.Name AS manager_name,
        u.Email AS manager_email,
        COALESCE(a.cycle_availability, 0) AS cycle_availability,
        (s.capacity - COALESCE(a.cycle_availability, 0)) AS occupied_slots,
        (
            SELECT COUNT(*) 
            FROM bicycle b 
            LEFT JOIN condition_type c ON b.ID = c.B_id
            WHERE b.location = s.Location 
              AND b.user_id IS NULL
              AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))
        ) AS ready_to_rent_count
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    LEFT JOIN user u ON s.User_id = u.user_id
    ORDER BY s.Location ASC
");
$stations = $stmt->fetchAll();

$pageTitle = "Cycle Stations & Hubs";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Cycle Station Hubs</h1>
        <p class="page-subtitle">Find bicycle pickup and return locations across the network</p>
    </div>
    <div class="header-action">
        <a href="availability.php" class="btn btn-outline-primary">📊 Live Availability Board</a>
    </div>
</div>

<div class="grid grid-3">
    <?php if (empty($stations)): ?>
        <div class="card card-span-3 text-center py-5">
            <p class="text-muted">No stations registered yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($stations as $st): 
            $avail = (int)$st['cycle_availability'];
            $cap   = (int)$st['capacity'];
            $pct   = $cap > 0 ? min(100, round(($avail / $cap) * 100)) : 0;
            $statusClass = $avail > 3 ? 'text-success' : ($avail > 0 ? 'text-warning' : 'text-danger');
            $statusText  = $avail > 3 ? 'Available' : ($avail > 0 ? 'Low Stock' : 'Empty');
        ?>
            <div class="card station-card">
                <div class="station-header">
                    <div class="station-icon">📍</div>
                    <div class="station-title-box">
                        <h3 class="station-name"><?= htmlspecialchars($st['Location']) ?></h3>
                        <span class="station-badge <?= $statusClass ?>">● <?= $statusText ?></span>
                    </div>
                </div>

                <div class="station-body">
                    <div class="progress-container mb-3">
                        <div class="progress-header">
                            <span class="text-sm font-medium">Availability</span>
                            <span class="text-sm font-bold"><?= $avail ?> / <?= $cap ?> Cycles</span>
                        </div>
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $avail > 3 ? 'var(--primary-color)' : ($avail > 0 ? 'var(--warning-color)' : 'var(--danger-color)') ?>;"></div>
                        </div>
                    </div>

                    <div class="station-meta-grid">
                        <div class="station-meta-item">
                            <span class="meta-label">Total Capacity</span>
                            <span class="meta-value"><?= $cap ?> Docks</span>
                        </div>
                        <div class="station-meta-item">
                            <span class="meta-label">Available for Rent</span>
                            <span class="meta-value font-bold <?= $statusClass ?>"><?= $st['ready_to_rent_count'] ?> Ready</span>
                        </div>
                        <div class="station-meta-item">
                            <span class="meta-label">Occupied / In Use</span>
                            <span class="meta-value"><?= max(0, $st['occupied_slots']) ?></span>
                        </div>
                        <div class="station-meta-item">
                            <span class="meta-label">Station Manager</span>
                            <span class="meta-value"><?= htmlspecialchars($st['manager_name'] ?? 'Assigned Staff') ?></span>
                        </div>
                    </div>
                </div>

                <div class="station-footer">
                    <a href="cycles.php?station=<?= urlencode($st['Location']) ?>" class="btn btn-outline-primary btn-block">
                        View Cycles at this Station 🚲
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
