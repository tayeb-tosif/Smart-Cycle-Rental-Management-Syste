<?php
/**
 * Active Rental Tracker
 * Feature 4 & 5: Cycle Rental & Return Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/pricing.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireCustomer();
$userId = (int)$_SESSION['user_id'];

// Refresh session
refreshUserSession($pdo);

// Fetch user's active rental
$activeRental = getActiveRental($userId, $pdo);

$pageTitle = "My Active Ride";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Active Cycle Ride</h1>
        <p class="page-subtitle">Real-time status and live duration counter for your current trip</p>
    </div>
    <div class="header-action">
        <a href="stations.php" class="btn btn-outline-secondary">📍 View Return Stations</a>
    </div>
</div>

<?php if (!$activeRental): ?>
    <div class="card text-center py-5">
        <div style="font-size: 3.5rem; margin-bottom: 10px;">🚴</div>
        <h2>No Active Rental in Progress</h2>
        <p class="text-muted">You do not currently have any bicycle rented. Ready for a ride?</p>
        <div class="mt-4">
            <a href="cycles.php" class="btn btn-primary btn-lg">Browse & Rent Bicycles</a>
            <a href="rental_history.php" class="btn btn-outline-secondary btn-lg ml-2">View Rental History</a>
        </div>
    </div>
<?php else: 
    $startTimestamp = strtotime($activeRental['start_time']);
    $elapsedMinutes = max(1, (int) ceil((time() - $startTimestamp) / 60));
    $fare = calculateRentalFare($elapsedMinutes);
?>
    <div class="grid grid-3">
        <!-- Live Ride Dashboard Card -->
        <div class="card card-span-2 active-ride-card">
            <div class="active-ride-header">
                <div class="pulsing-dot-container">
                    <span class="pulsing-dot"></span>
                    <strong>LIVE TRIP IN PROGRESS</strong>
                </div>
                <span class="badge badge-lg badge-success">🔓 Lock Open</span>
            </div>

            <div class="live-counter-box text-center py-4">
                <div class="live-counter-label text-muted">ELAPSED RIDE TIME</div>
                <div class="live-timer-display font-mono" id="liveTimer">
                    00:00:00
                </div>
                <p class="text-muted text-sm mt-1">
                    Started on <?= formatDateTime($activeRental['start_time']) ?>
                </p>
            </div>

            <div class="trip-details-grid">
                <div class="trip-detail-item">
                    <span class="trip-detail-label">Rental Transaction</span>
                    <span class="trip-detail-val">#<?= $activeRental['trans_id'] ?></span>
                </div>
                <div class="trip-detail-item">
                    <span class="trip-detail-label">Bicycle ID</span>
                    <span class="trip-detail-val font-bold">Cycle #<?= $activeRental['B_id'] ?></span>
                </div>
                <div class="trip-detail-item">
                    <span class="trip-detail-label">QR Identifier</span>
                    <span class="trip-detail-val font-mono"><?= htmlspecialchars($activeRental['QR_code']) ?></span>
                </div>
                <div class="trip-detail-item">
                    <span class="trip-detail-label">Pickup Hub</span>
                    <span class="trip-detail-val">📍 <?= htmlspecialchars($activeRental['pickup_station']) ?></span>
                </div>
            </div>

            <div class="active-ride-actions mt-4">
                <a href="return.php" class="btn btn-danger btn-lg btn-block">
                    🏁 Finish Trip & Return Bicycle
                </a>
            </div>
        </div>

        <!-- Estimated Fare Calculator Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Live Estimated Fare</h3>
            </div>

            <div class="stat-mini-group">
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Base Fare</span>
                    <span class="stat-mini-val"><?= formatCurrency(PRICING_BASE_FEE) ?></span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Time Tier Rate</span>
                    <span class="stat-mini-val">+ <?= formatCurrency(PRICING_ADDITIONAL_FEE_PER_SLOT) ?> / 30m</span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Current Estimate</span>
                    <span class="stat-mini-val text-primary font-bold" id="liveEstimatedFare">
                        <?= formatCurrency($fare['total_before_discount']) ?>
                    </span>
                </div>
            </div>

            <hr class="card-divider">

            <div class="alert alert-info" style="font-size: 0.85rem;">
                <strong>💡 Return Tips:</strong>
                <ul class="mb-0 mt-1" style="padding-left: 15px;">
                    <li>Dock the cycle at any official station dock.</li>
                    <li>You will select your drop-off station and payment method on the return screen.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Live Timer JavaScript -->
    <script>
    (function() {
        const startTime = <?= $startTimestamp ?> * 1000;
        const baseFee = <?= PRICING_BASE_FEE ?>;
        const addFeeSlot = <?= PRICING_ADDITIONAL_FEE_PER_SLOT ?>;
        const minPerSlot = <?= PRICING_MINUTES_PER_SLOT ?>;

        function updateTimer() {
            const now = new Date().getTime();
            const elapsedMs = Math.max(0, now - startTime);
            
            const totalSec = Math.floor(elapsedMs / 1000);
            const hrs = Math.floor(totalSec / 3600);
            const mins = Math.floor((totalSec % 3600) / 60);
            const secs = totalSec % 60;

            const pad = (n) => n.toString().padStart(2, '0');
            document.getElementById('liveTimer').innerText = `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;

            // Estimate fare
            const totalMins = Math.max(1, Math.ceil(totalSec / 60));
            const slots = Math.ceil(totalMins / minPerSlot);
            const totalFare = baseFee + (slots * addFeeSlot);
            document.getElementById('liveEstimatedFare').innerText = totalFare.toFixed(2) + ' Tk';
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    })();
    </script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
