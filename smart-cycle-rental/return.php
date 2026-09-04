<?php
/**
 * Cycle Return & Settlement Processing
 * Feature 5: Cycle Return Management & Feature 6: Payment & Coupon
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

if (!$activeRental) {
    setFlashMessage('info', "You do not have any active cycle rental to return.");
    header("Location: dashboard.php");
    exit;
}

// Calculate duration and pricing preview
$startTimestamp = strtotime($activeRental['start_time']);
$nowTimestamp   = time();
$durationMinutes = max(1, (int) ceil(($nowTimestamp - $startTimestamp) / 60));
$fare = calculateRentalFare($durationMinutes);

// Fetch list of available stations for return drop-off
$stationsStmt = $pdo->query("
    SELECT s.Location, s.capacity, COALESCE(a.cycle_availability, 0) AS cycle_availability
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    ORDER BY s.Location ASC
");
$stations = $stationsStmt->fetchAll();

// Handle Coupon Validation via AJAX/Post
$couponCode = sanitizeInput($_POST['coupon_code'] ?? '');
$couponResult = null;
$discountAmount = 0.00;

if (!empty($couponCode)) {
    $couponResult = validateCouponCode($couponCode, (float)$fare['total_before_discount'], $pdo);
    if ($couponResult['valid']) {
        $discountAmount = (float)$couponResult['discount_amount'];
    }
}

$finalBill = max(0.00, (float)$fare['total_before_discount'] - $discountAmount);

$errors = [];

// Handle Return Submission (Atomic Transaction)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_return') {
    $returnStation = sanitizeInput($_POST['return_station'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? 'Wallet');
    $appliedCoupon = sanitizeInput($_POST['applied_coupon_code'] ?? 'NONE');

    // Validation
    if (empty($returnStation)) {
        $errors[] = "Please select a valid return station hub.";
    }

    $validMethods = ['Wallet', 'Cash', 'Card', 'Mobile Banking'];
    if (!in_array($paymentMethod, $validMethods, true)) {
        $errors[] = "Invalid payment method selected.";
    }

    // Check station exists and has capacity
    $stCheck = $pdo->prepare("SELECT capacity, cycle_availability FROM station s LEFT JOIN availability a ON s.Location = a.Location WHERE s.Location = :loc");
    $stCheck->execute(['loc' => $returnStation]);
    $stData = $stCheck->fetch();

    if (!$stData) {
        $errors[] = "The selected return station does not exist.";
    }

    // Check wallet balance if payment method is Wallet
    if ($paymentMethod === 'Wallet') {
        $userCheck = $pdo->prepare("SELECT wallet FROM user WHERE user_id = :uid");
        $userCheck->execute(['uid' => $userId]);
        $currentWallet = (float)$userCheck->fetchColumn();

        if ($currentWallet < $finalBill) {
            $errors[] = "Insufficient wallet balance (" . formatCurrency($currentWallet) . "). Your final bill is " . formatCurrency($finalBill) . ". Please choose Cash, Card, Mobile Banking, or top up your wallet.";
        }
    }

    if (empty($errors)) {
        try {
            // ==========================================
            // START DATABASE TRANSACTION
            // ==========================================
            $pdo->beginTransaction();

            // 1. Lock and fetch active rental record
            $lockRental = $pdo->prepare("
                SELECT r.*, b.ID AS bike_id 
                FROM rental_trans r 
                JOIN bicycle b ON r.B_id = b.ID 
                WHERE r.trans_id = :tid AND r.user_id = :uid AND r.status = 'Active' 
                FOR UPDATE
            ");
            $lockRental->execute([
                'tid' => $activeRental['trans_id'],
                'uid' => $userId
            ]);
            $rentalRow = $lockRental->fetch();

            if (!$rentalRow) {
                throw new Exception("Active rental transaction not found or already completed.");
            }

            // 2. Compute exact duration using MySQL TIMESTAMPDIFF
            $timeStmt = $pdo->prepare("SELECT TIMESTAMPDIFF(MINUTE, :st, NOW()) AS exact_mins");
            $timeStmt->execute(['st' => $rentalRow['start_time']]);
            $exactMinutes = max(1, (int)$timeStmt->fetchColumn());

            // Compute exact pricing
            $exactFare = calculateRentalFare($exactMinutes);
            $exactBase = (float)$exactFare['base_fee'];
            $exactAdd  = (float)$exactFare['additional_fee'];
            $exactGross = (float)$exactFare['total_before_discount'];

            // Validate coupon again inside transaction
            $exactDiscount = 0.00;
            $couponToRecord = 'NONE';
            if (!empty($appliedCoupon) && $appliedCoupon !== 'NONE') {
                $cVal = validateCouponCode($appliedCoupon, $exactGross, $pdo);
                if ($cVal['valid']) {
                    $exactDiscount = (float)$cVal['discount_amount'];
                    $couponToRecord = $cVal['coupon_code'];
                }
            }
            $exactFinalBill = max(0.00, $exactGross - $exactDiscount);

            // 3. If paying with Wallet, verify balance with row lock
            if ($paymentMethod === 'Wallet') {
                $userLock = $pdo->prepare("SELECT wallet, revenue FROM user WHERE user_id = :uid FOR UPDATE");
                $userLock->execute(['uid' => $userId]);
                $userFin = $userLock->fetch();

                if ((float)$userFin['wallet'] < $exactFinalBill) {
                    throw new Exception("Insufficient wallet balance for deduction.");
                }

                // Deduct wallet and update user revenue
                $deductWallet = $pdo->prepare("UPDATE user SET wallet = wallet - :bill, revenue = revenue + :bill WHERE user_id = :uid");
                $deductWallet->execute([
                    'bill' => $exactFinalBill,
                    'uid'  => $userId
                ]);
            } else {
                // Cash, Card, Mobile Banking: Increase user spent revenue
                $addRevenue = $pdo->prepare("UPDATE user SET revenue = revenue + :bill WHERE user_id = :uid");
                $addRevenue->execute([
                    'bill' => $exactFinalBill,
                    'uid'  => $userId
                ]);
            }

            // 4. Insert into PAYMENT table
            $insertPayment = $pdo->prepare("
                INSERT INTO payment (B_id, user_id, Bill, base_fee, additional_fee, discount, payment_status)
                VALUES (:bid, :uid, :bill, :base, :add_fee, :disc, 'Paid')
            ");
            $insertPayment->execute([
                'bid'      => $rentalRow['B_id'],
                'uid'      => $userId,
                'bill'     => $exactFinalBill,
                'base'     => $exactBase,
                'add_fee'  => $exactAdd,
                'disc'     => $exactDiscount
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            // 5. Insert into PAYMENT_TYPE table
            $insertPayType = $pdo->prepare("
                INSERT INTO payment_type (payment_id, payment_method)
                VALUES (:pid, :method)
            ");
            $insertPayType->execute([
                'pid'    => $paymentId,
                'method' => $paymentMethod
            ]);

            // 6. Insert into COUPON_DETAILS table
            $insertCoupon = $pdo->prepare("
                INSERT INTO coupon_details (payment_id, coupon)
                VALUES (:pid, :coupon)
            ");
            $insertCoupon->execute([
                'pid'    => $paymentId,
                'coupon' => $couponToRecord
            ]);

            // 7. Update RENTAL_TRANS table
            $updateRental = $pdo->prepare("
                UPDATE rental_trans 
                SET end_time = NOW(), 
                    return_station = :ret_station, 
                    payment_id = :pid, 
                    status = 'Completed' 
                WHERE trans_id = :tid
            ");
            $updateRental->execute([
                'ret_station' => $returnStation,
                'pid'         => $paymentId,
                'tid'         => $rentalRow['trans_id']
            ]);

            // 8. Update BICYCLE status (Lock bicycle, clear user, assign return dock station)
            $updateBike = $pdo->prepare("
                UPDATE bicycle 
                SET user_id = NULL, 
                    locked = 1, 
                    unlocked = 0, 
                    location = :ret_station 
                WHERE ID = :bid
            ");
            $updateBike->execute([
                'ret_station' => $returnStation,
                'bid'         => $rentalRow['B_id']
            ]);

            // 9. Increase RETURN STATION availability (guarded against capacity overflow)
            $updateAvail = $pdo->prepare("
                UPDATE availability a
                JOIN station s ON a.Location = s.Location
                SET a.cycle_availability = LEAST(s.capacity, a.cycle_availability + 1)
                WHERE a.Location = :ret_station
            ");
            $updateAvail->execute(['ret_station' => $returnStation]);

            // ==========================================
            // COMMIT DATABASE TRANSACTION
            // ==========================================
            $pdo->commit();

            // Refresh session variables
            refreshUserSession($pdo);

            setFlashMessage('success', "🎉 Cycle returned successfully! Payment of " . formatCurrency($exactFinalBill) . " processed.");
            header("Location: payment.php?payment_id={$paymentId}");
            exit;

        } catch (Exception $e) {
            // ==========================================
            // ROLLBACK DATABASE TRANSACTION ON FAILURE
            // ==========================================
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Return failed: " . $e->getMessage();
        }
    }
}

$pageTitle = "Return Bicycle & Settle Payment";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Return Bicycle & Final Payment</h1>
        <p class="page-subtitle">Dock your bicycle, review rental duration, apply promo coupons, and complete checkout</p>
    </div>
    <div class="header-action">
        <a href="my_rental.php" class="btn btn-outline-secondary">← Back to Active Ride</a>
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

<div class="grid grid-3">
    <!-- Left: Rental Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ride Summary</h3>
        </div>

        <div class="cycle-visual py-3">
            <span class="cycle-big-icon">🚲</span>
            <h4>Bicycle #<?= $activeRental['B_id'] ?></h4>
            <code><?= htmlspecialchars($activeRental['QR_code']) ?></code>
        </div>

        <div class="stat-mini-group">
            <div class="stat-mini-item">
                <span class="stat-mini-label">Pickup Hub</span>
                <span class="stat-mini-val">📍 <?= htmlspecialchars($activeRental['pickup_station']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">Trip Started</span>
                <span class="stat-mini-val"><?= formatDateTime($activeRental['start_time']) ?></span>
            </div>
            <div class="stat-mini-item">
                <span class="stat-mini-label">Total Duration</span>
                <span class="stat-mini-val font-bold text-primary"><?= formatDuration($durationMinutes) ?></span>
            </div>
        </div>
    </div>

    <!-- Right: Return Dropoff & Payment Form -->
    <div class="card card-span-2">
        <form action="return.php" method="POST" id="returnForm">
            <input type="hidden" name="action" value="confirm_return">
            <input type="hidden" name="applied_coupon_code" id="appliedCouponCode" value="<?= htmlspecialchars($couponResult && $couponResult['valid'] ? $couponResult['coupon_code'] : 'NONE') ?>">

            <!-- Section 1: Drop-off Station -->
            <div class="card-header">
                <h3 class="card-title">1. Select Drop-off Station</h3>
            </div>
            <div class="form-group mb-4">
                <label for="return_station" class="form-label">Return Station Hub <span class="text-danger">*</span></label>
                <select name="return_station" id="return_station" class="form-select" required>
                    <option value="">-- Choose Return Station --</option>
                    <?php foreach ($stations as $st): ?>
                        <option value="<?= htmlspecialchars($st['Location']) ?>" <?= $st['Location'] === $activeRental['pickup_station'] ? 'selected' : '' ?>>
                            📍 <?= htmlspecialchars($st['Location']) ?> (Capacity: <?= $st['capacity'] ?>, Available: <?= $st['cycle_availability'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">You may return to any registered station hub across the city.</small>
            </div>

            <hr class="card-divider">

            <!-- Section 2: Bill Breakdown & Coupon -->
            <div class="card-header">
                <h3 class="card-title">2. Bill Calculation & Promo Coupon</h3>
            </div>

            <div class="bill-summary-box mb-3">
                <div class="bill-row">
                    <span>Base Unlock Fee (includes first 30 mins)</span>
                    <span><?= formatCurrency((float)$fare['base_fee']) ?></span>
                </div>
                <div class="bill-row">
                    <span>Additional Time Fee (<?= $fare['slots'] ?> slot<?= $fare['slots'] > 1 ? 's' : '' ?> &times; 5 Tk)</span>
                    <span><?= formatCurrency((float)$fare['additional_fee']) ?></span>
                </div>
                <div class="bill-row font-medium">
                    <span>Subtotal</span>
                    <span><?= formatCurrency((float)$fare['total_before_discount']) ?></span>
                </div>

                <?php if ($couponResult && $couponResult['valid']): ?>
                    <div class="bill-row text-success font-medium">
                        <span>Promo Discount (<?= htmlspecialchars($couponResult['coupon_code']) ?> - <?= $couponResult['percentage'] ?>%)</span>
                        <span>- <?= formatCurrency($discountAmount) ?></span>
                    </div>
                <?php endif; ?>

                <div class="bill-row bill-total">
                    <strong>Final Bill Amount</strong>
                    <strong class="text-primary font-bold" style="font-size: 1.3rem;">
                        <?= formatCurrency($finalBill) ?>
                    </strong>
                </div>
            </div>

            <!-- Coupon Input Form (Submits to validate coupon) -->
            <div class="form-group mb-4">
                <label class="form-label">Apply Promo Coupon (Try: <code>WELCOME10</code> or <code>SAVE20</code>)</label>
                <div class="input-group">
                    <input type="text" name="coupon_code" id="couponInput" class="form-control" placeholder="Enter coupon code" value="<?= htmlspecialchars($couponCode) ?>">
                    <button type="submit" name="apply_coupon_btn" value="1" formaction="return.php" class="btn btn-secondary">Apply Code</button>
                </div>
                <?php if ($couponResult): ?>
                    <div class="mt-2">
                        <?php if ($couponResult['valid']): ?>
                            <span class="text-success text-sm font-medium">✔ <?= htmlspecialchars($couponResult['message']) ?></span>
                        <?php else: ?>
                            <span class="text-danger text-sm font-medium">✖ <?= htmlspecialchars($couponResult['message']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <hr class="card-divider">

            <!-- Section 3: Payment Method -->
            <div class="card-header">
                <h3 class="card-title">3. Choose Payment Method</h3>
            </div>

            <div class="payment-methods-grid mb-4">
                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="Wallet" checked>
                    <div class="method-content">
                        <span class="method-icon">👛</span>
                        <strong>Smart Wallet</strong>
                        <small class="text-muted">Balance: <?= formatCurrency((float)$_SESSION['wallet']) ?></small>
                    </div>
                </label>

                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="Mobile Banking">
                    <div class="method-content">
                        <span class="method-icon">📱</span>
                        <strong>bKash / Nagad</strong>
                        <small class="text-muted">Mobile Banking</small>
                    </div>
                </label>

                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="Card">
                    <div class="method-content">
                        <span class="method-icon">💳</span>
                        <strong>Debit / Credit Card</strong>
                        <small class="text-muted">Visa / Mastercard</small>
                    </div>
                </label>

                <label class="payment-method-card">
                    <input type="radio" name="payment_method" value="Cash">
                    <div class="method-content">
                        <span class="method-icon">💵</span>
                        <strong>Cash at Station</strong>
                        <small class="text-muted">Pay Station Manager</small>
                    </div>
                </label>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-success btn-lg btn-block" onclick="return confirm('Confirm returning Cycle #<?= $activeRental['B_id'] ?> and paying <?= formatCurrency($finalBill) ?>?');">
                    🔒 Lock Bicycle, Return & Pay <?= formatCurrency($finalBill) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
