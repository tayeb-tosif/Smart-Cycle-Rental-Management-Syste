<?php
/**
 * Cycle Rental Processing
 * Feature 4: Cycle Rental Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/pricing.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Rule 1 & RBAC: Must be a logged-in customer
requireCustomer();
$userId = (int)$_SESSION['user_id'];

// Refresh session
refreshUserSession($pdo);

$bikeId = (int)($_GET['bike_id'] ?? ($_POST['bike_id'] ?? 0));
$errors = [];

// Check if customer already has an active rental
$existingActive = getActiveRental($userId, $pdo);
if ($existingActive) {
    setFlashMessage('warning', "You already have an active cycle rental (Cycle #{$existingActive['B_id']}). Please return your active cycle before renting another.");
    header("Location: my_rental.php");
    exit;
}

// Fetch bicycle details for confirmation screen
if ($bikeId > 0) {
    $stmt = $pdo->prepare("
        SELECT b.*, c.condition AS cycle_condition, s.capacity, a.cycle_availability
        FROM bicycle b
        LEFT JOIN condition_type c ON b.ID = c.B_id
        LEFT JOIN station s ON b.location = s.Location
        LEFT JOIN availability a ON b.location = a.Location
        WHERE b.ID = :bid
    ");
    $stmt->execute(['bid' => $bikeId]);
    $bike = $stmt->fetch();
} else {
    $bike = null;
}

// Handle Rental Form Submission (Atomic Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_rent') {
    $submittedBikeId = (int)($_POST['bike_id'] ?? 0);

    if ($submittedBikeId <= 0) {
        $errors[] = "Invalid bicycle selection.";
    } else {
        try {
            // ==========================================
            // START DATABASE TRANSACTION
            // ==========================================
            $pdo->beginTransaction();

            // 1. Verify customer does not already have an active rental (with row lock)
            $checkUser = $pdo->prepare("
                SELECT trans_id 
                FROM rental_trans 
                WHERE user_id = :uid AND status = 'Active' 
                FOR UPDATE
            ");
            $checkUser->execute(['uid' => $userId]);
            if ($checkUser->fetch()) {
                throw new Exception("You already have an active rental in progress.");
            }

            // 2. Lock & inspect selected bicycle
            $lockBike = $pdo->prepare("
                SELECT b.*, c.condition AS cycle_condition 
                FROM bicycle b 
                LEFT JOIN condition_type c ON b.ID = c.B_id 
                WHERE b.ID = :bid 
                FOR UPDATE
            ");
            $lockBike->execute(['bid' => $submittedBikeId]);
            $targetBike = $lockBike->fetch();

            if (!$targetBike) {
                throw new Exception("The selected bicycle does not exist.");
            }

            // Prevent double rental
            if (!empty($targetBike['user_id'])) {
                throw new Exception("This bicycle has just been rented by another customer. Please choose another bicycle.");
            }

            if (empty($targetBike['location'])) {
                throw new Exception("This bicycle is currently in transit and not docked at any station.");
            }

            $pickupStation = $targetBike['location'];

            // Condition suitability check
            if (in_array($targetBike['cycle_condition'], ['Damaged', 'Under Maintenance'], true)) {
                throw new Exception("This bicycle is currently marked as '" . $targetBike['cycle_condition'] . "' and cannot be rented.");
            }

            // 3. Lock & verify station availability
            $lockAvail = $pdo->prepare("
                SELECT cycle_availability 
                FROM availability 
                WHERE Location = :loc 
                FOR UPDATE
            ");
            $lockAvail->execute(['loc' => $pickupStation]);
            $avail = $lockAvail->fetch();

            if (!$avail || (int)$avail['cycle_availability'] <= 0) {
                throw new Exception("The pickup station '{$pickupStation}' has no available cycles recorded.");
            }

            // 4. Create record in rental_trans
            $insertTrans = $pdo->prepare("
                INSERT INTO rental_trans (user_id, B_id, payment_id, start_time, end_time, pickup_station, return_station, status)
                VALUES (:uid, :bid, NULL, NOW(), NULL, :pickup, NULL, 'Active')
            ");
            $insertTrans->execute([
                'uid'    => $userId,
                'bid'    => $submittedBikeId,
                'pickup' => $pickupStation
            ]);
            $transId = (int)$pdo->lastInsertId();

            // 5. Update bicycle status (assign rider, unlock, remove from station dock)
            $updateBike = $pdo->prepare("
                UPDATE bicycle 
                SET user_id = :uid, locked = 0, unlocked = 1, location = NULL 
                WHERE ID = :bid
            ");
            $updateBike->execute([
                'uid' => $userId,
                'bid' => $submittedBikeId
            ]);

            // 6. Decrease station availability
            $updateAvail = $pdo->prepare("
                UPDATE availability 
                SET cycle_availability = GREATEST(0, cycle_availability - 1) 
                WHERE Location = :loc
            ");
            $updateAvail->execute(['loc' => $pickupStation]);

            // ==========================================
            // COMMIT DATABASE TRANSACTION
            // ==========================================
            $pdo->commit();

            setFlashMessage('success', "🎉 Bicycle #{$submittedBikeId} unlocked and rented successfully! Have a safe ride.");
            header("Location: my_rental.php");
            exit;

        } catch (Exception $e) {
            // ==========================================
            // ROLLBACK DATABASE TRANSACTION ON FAILURE
            // ==========================================
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = "Confirm Cycle Rental";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Rent a Bicycle</h1>
        <p class="page-subtitle">Confirm your pickup station, rental terms, and unlock your cycle</p>
    </div>
    <div class="header-action">
        <a href="cycles.php" class="btn btn-outline-secondary">← Back to Cycle Catalog</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="error-list">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$bike): ?>
    <div class="card text-center py-5">
        <div style="font-size: 3rem;">🚲</div>
        <h3>No Bicycle Selected</h3>
        <p class="text-muted">Please select an available bicycle from the catalog to proceed with rental.</p>
        <a href="cycles.php" class="btn btn-primary mt-3">Browse Available Bicycles</a>
    </div>
<?php else: 
    $isAvailable = empty($bike['user_id']) && !empty($bike['location']) && !in_array($bike['cycle_condition'], ['Damaged', 'Under Maintenance'], true);
?>
    <div class="grid grid-3">
        <!-- Cycle Info Card -->
        <div class="card">
            <div class="cycle-visual">
                <span class="cycle-big-icon">🚲</span>
                <h3>Bicycle #<?= $bike['ID'] ?></h3>
                <code><?= htmlspecialchars($bike['QR_code']) ?></code>
            </div>
            
            <hr class="card-divider">

            <div class="stat-mini-group">
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Station Dock</span>
                    <span class="stat-mini-val">📍 <?= htmlspecialchars($bike['location'] ?? 'Not Docked') ?></span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Physical Condition</span>
                    <span class="stat-mini-val"><?= getConditionBadge($bike['cycle_condition']) ?></span>
                </div>
                <div class="stat-mini-item">
                    <span class="stat-mini-label">Availability</span>
                    <span class="stat-mini-val">
                        <?= $isAvailable ? '<span class="text-success font-bold">Ready to Rent</span>' : '<span class="text-danger font-bold">Unavailable</span>' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Rental Agreement & Pricing Breakdown -->
        <div class="card card-span-2">
            <div class="card-header">
                <h3 class="card-title">Rental Terms & Fare Structure</h3>
            </div>

            <div class="pricing-banner mb-4">
                <div class="pricing-row">
                    <div>
                        <strong>Base Rental Fee:</strong>
                        <p class="text-muted text-sm">Includes initial unlock and first 30 minutes</p>
                    </div>
                    <span class="pricing-val font-bold text-primary"><?= formatCurrency(PRICING_BASE_FEE) ?></span>
                </div>
                <div class="pricing-row">
                    <div>
                        <strong>Additional Time:</strong>
                        <p class="text-muted text-sm">Every additional 30 minutes or fraction thereof</p>
                    </div>
                    <span class="pricing-val font-bold text-primary">+ <?= formatCurrency(PRICING_ADDITIONAL_FEE_PER_SLOT) ?> / 30 mins</span>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>Important Rental Rules:</strong>
                <ul class="mb-0 mt-1" style="padding-left: 20px; font-size: 0.9rem;">
                    <li>You can return this cycle to <strong>any</strong> registered station hub across the city.</li>
                    <li>Payment is settled immediately upon cycle return via Wallet, Cash, Card, or Mobile Banking.</li>
                    <li>The smart lock on the bicycle will unlock immediately when you confirm rental.</li>
                </ul>
            </div>

            <?php if ($isAvailable): ?>
                <form action="rent.php" method="POST" class="mt-4">
                    <input type="hidden" name="action" value="confirm_rent">
                    <input type="hidden" name="bike_id" value="<?= $bike['ID'] ?>">

                    <div class="form-check mb-3">
                        <input type="checkbox" id="agreeTerms" class="form-check-input" required checked>
                        <label for="agreeTerms" class="form-check-label">
                            I accept the Smart Cycle Rental terms and agree to safely operate Cycle #<?= $bike['ID'] ?>.
                        </label>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            🚲 Unlock & Start Ride Now
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mt-3">
                    This bicycle is currently not available for rental.
                </div>
                <a href="cycles.php" class="btn btn-secondary mt-2">Select Another Bicycle</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
