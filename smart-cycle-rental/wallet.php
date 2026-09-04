<?php
/**
 * Customer Smart Wallet Management
 * Feature 6: Payment & Wallet Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$userId = (int)$_SESSION['user_id'];

// Refresh session
refreshUserSession($pdo);

$errors = [];

// Handle Demo Balance Top-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'top_up') {
    $amount = (float)($_POST['amount'] ?? 0.00);

    if ($amount <= 0 || $amount > 5000) {
        $errors[] = "Please enter a valid top-up amount between 10 Tk and 5,000 Tk.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE user SET wallet = wallet + :amt WHERE user_id = :uid");
            $stmt->execute(['amt' => $amount, 'uid' => $userId]);

            refreshUserSession($pdo);
            setFlashMessage('success', "🎉 Successfully credited " . formatCurrency($amount) . " to your Smart Wallet!");
            header("Location: wallet.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Failed to update wallet: " . $e->getMessage();
        }
    }
}

// Fetch user current wallet and revenue
$userStmt = $pdo->prepare("SELECT wallet, revenue, Name, Email FROM user WHERE user_id = :uid");
$userStmt->execute(['uid' => $userId]);
$userData = $userStmt->fetch();

// Fetch customer recent payments made from wallet
$payStmt = $pdo->prepare("
    SELECT p.*, pt.payment_method, cd.coupon, b.QR_code 
    FROM payment p
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    LEFT JOIN coupon_details cd ON p.payment_id = cd.payment_id
    LEFT JOIN bicycle b ON p.B_id = b.ID
    WHERE p.user_id = :uid
    ORDER BY p.payment_id DESC
    LIMIT 10
");
$payStmt->execute(['uid' => $userId]);
$walletPayments = $payStmt->fetchAll();

$pageTitle = "My Smart Wallet";
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Smart Cycle Wallet</h1>
        <p class="page-subtitle">Manage your balance for instantaneous cycle unlocks and zero-wait returns</p>
    </div>
    <div class="header-action">
        <a href="payment_history.php" class="btn btn-outline-secondary">View All Payment Receipts</a>
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
    <!-- Wallet Card Summary -->
    <div class="card wallet-hero-card">
        <div class="wallet-chip"></div>
        <div class="wallet-hero-header">
            <span class="wallet-badge-label">SMART WALLET</span>
            <span class="wallet-brand">🚲 SmartCycle</span>
        </div>

        <div class="wallet-balance-block">
            <span class="wallet-label text-white-50">AVAILABLE BALANCE</span>
            <h2 class="wallet-amount"><?= formatCurrency((float)$userData['wallet']) ?></h2>
        </div>

        <div class="wallet-footer-info">
            <div>
                <span class="text-white-50 text-xs">CARD HOLDER</span>
                <p class="text-white font-bold mb-0"><?= htmlspecialchars($userData['Name']) ?></p>
            </div>
            <div class="text-right">
                <span class="text-white-50 text-xs">TOTAL SPENT</span>
                <p class="text-white font-mono mb-0"><?= formatCurrency((float)$userData['revenue']) ?></p>
            </div>
        </div>
    </div>

    <!-- Top Up Simulator Card (Demonstration purpose for viva) -->
    <div class="card card-span-2">
        <div class="card-header">
            <h3 class="card-title">Add Funds / Top-Up Wallet</h3>
            <span class="badge badge-outline">Demo Simulator</span>
        </div>

        <p class="text-muted text-sm mb-3">
            Top up your demo wallet balance to simulate frictionless rentals. Select a quick amount or enter a custom amount.
        </p>

        <div class="quick-topup-grid mb-3">
            <button type="button" class="btn btn-outline-primary" onclick="setTopUp(100)">+ 100 Tk</button>
            <button type="button" class="btn btn-outline-primary" onclick="setTopUp(200)">+ 200 Tk</button>
            <button type="button" class="btn btn-outline-primary" onclick="setTopUp(500)">+ 500 Tk</button>
            <button type="button" class="btn btn-outline-primary" onclick="setTopUp(1000)">+ 1,000 Tk</button>
        </div>

        <form action="wallet.php" method="POST" class="form-grid">
            <input type="hidden" name="action" value="top_up">
            <div class="form-group full-width">
                <label for="amount" class="form-label">Top-Up Amount (BDT)</label>
                <div class="input-group">
                    <input type="number" step="10" min="10" max="5000" id="topUpAmount" name="amount" class="form-control font-bold" value="200" required>
                    <button type="submit" class="btn btn-success">Add Balance to Wallet</button>
                </div>
            </div>
        </form>

        <div class="alert alert-info mt-3 mb-0" style="font-size: 0.85rem;">
            <strong>ℹ Note:</strong> The <code>wallet</code> field in the <code>user</code> database table is instantly credited via a prepared statement.
        </div>
    </div>
</div>

<!-- Recent Wallet Transactions Table -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Recent Rental Payments</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Date & Time</th>
                    <th>Bicycle</th>
                    <th>Base / Extra</th>
                    <th>Coupon</th>
                    <th>Method</th>
                    <th>Bill Amount</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($walletPayments)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No payment records yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($walletPayments as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['payment_id'] ?></strong></td>
                            <td><?= formatDateTime($p['trans_time']) ?></td>
                            <td>Cycle #<?= $p['B_id'] ?> (<code><?= htmlspecialchars($p['QR_code']) ?></code>)</td>
                            <td><?= formatCurrency((float)$p['base_fee']) ?> + <?= formatCurrency((float)$p['additional_fee']) ?></td>
                            <td>
                                <?php if (!empty($p['coupon']) && $p['coupon'] !== 'NONE'): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($p['coupon']) ?> (-<?= formatCurrency((float)$p['discount']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($p['payment_method'] ?? 'Wallet') ?></span></td>
                            <td class="font-bold font-mono text-primary"><?= formatCurrency((float)$p['Bill']) ?></td>
                            <td>
                                <a href="payment.php?payment_id=<?= $p['payment_id'] ?>" class="btn btn-xs btn-outline-secondary">View Invoice</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function setTopUp(amt) {
    document.getElementById('topUpAmount').value = amt;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
