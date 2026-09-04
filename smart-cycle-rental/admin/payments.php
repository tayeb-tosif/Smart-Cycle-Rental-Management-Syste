<?php
/**
 * Global Payment Ledger & Financial Reports (Admin & Staff)
 * Feature 6 & 7: Payment & Admin Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffOrAdmin();

$methodFilter = sanitizeInput($_GET['method'] ?? '');
$search       = sanitizeInput($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($methodFilter)) {
    $where[] = "pt.payment_method = :method";
    $params['method'] = $methodFilter;
}

if (!empty($search)) {
    $where[] = "(u.Name LIKE :q OR u.Email LIKE :q OR b.QR_code LIKE :q OR p.payment_id = :qid)";
    $params['q'] = "%{$search}%";
    $params['qid'] = is_numeric($search) ? (int)$search : 0;
}

$sql = "
    SELECT 
        p.*,
        u.Name AS customer_name,
        u.Email AS customer_email,
        b.QR_code,
        pt.payment_method,
        COALESCE(cd.coupon, 'NONE') AS applied_coupon,
        r.trans_id
    FROM payment p
    JOIN user u ON p.user_id = u.user_id
    JOIN bicycle b ON p.B_id = b.ID
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    LEFT JOIN coupon_details cd ON p.payment_id = cd.payment_id
    LEFT JOIN rental_trans r ON p.payment_id = r.payment_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.payment_id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Financial Aggregate Summaries
$agg = $pdo->query("
    SELECT 
        COALESCE(SUM(Bill), 0.00) AS total_revenue,
        COALESCE(SUM(discount), 0.00) AS total_discounts,
        COALESCE(SUM(CASE WHEN pt.payment_method = 'Wallet' THEN p.Bill ELSE 0 END), 0.00) AS wallet_revenue,
        COALESCE(SUM(CASE WHEN pt.payment_method = 'Cash' THEN p.Bill ELSE 0 END), 0.00) AS cash_revenue,
        COALESCE(SUM(CASE WHEN pt.payment_method = 'Mobile Banking' THEN p.Bill ELSE 0 END), 0.00) AS mobile_revenue,
        COALESCE(SUM(CASE WHEN pt.payment_method = 'Card' THEN p.Bill ELSE 0 END), 0.00) AS card_revenue
    FROM payment p
    LEFT JOIN payment_type pt ON p.payment_id = pt.payment_id
    WHERE p.payment_status = 'Paid'
")->fetch();

$pageTitle = "Financial Payment Ledger";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Financial Ledger & Revenue Reports</h1>
        <p class="page-subtitle">Complete audit ledger of all cycle rental payments, coupon discounts, and channel breakdowns</p>
    </div>
    <div class="header-action">
        <button onclick="window.print();" class="btn btn-secondary">🖨 Export Financial Report</button>
    </div>
</div>

<!-- Revenue Summary Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon bg-success-soft">💰</div>
        <div class="kpi-info">
            <span class="kpi-label">Gross Revenue</span>
            <h3 class="kpi-value text-success font-bold"><?= formatCurrency((float)$agg['total_revenue']) ?></h3>
            <span class="kpi-sub">Total settled payments</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-primary-soft">👛</div>
        <div class="kpi-info">
            <span class="kpi-label">Wallet Payments</span>
            <h3 class="kpi-value text-primary font-bold"><?= formatCurrency((float)$agg['wallet_revenue']) ?></h3>
            <span class="kpi-sub">In-app wallet deductions</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-info-soft">📱</div>
        <div class="kpi-info">
            <span class="kpi-label">Mobile Banking & Cards</span>
            <h3 class="kpi-value text-info font-bold"><?= formatCurrency((float)$agg['mobile_revenue'] + (float)$agg['card_revenue']) ?></h3>
            <span class="kpi-sub">bKash, Nagad, Visa, Mastercard</span>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-icon bg-warning-soft">🎟</div>
        <div class="kpi-info">
            <span class="kpi-label">Promotional Discounts</span>
            <h3 class="kpi-value text-warning font-bold"><?= formatCurrency((float)$agg['total_discounts']) ?></h3>
            <span class="kpi-sub">Coupons subsidized</span>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="filter-card card mb-4">
    <form action="payments.php" method="GET" class="filter-form-grid">
        <div class="form-group" style="flex: 2;">
            <label class="form-label">Search Customer or Payment ID</label>
            <input type="text" name="q" class="form-control" placeholder="Customer name, email, QR code, or Payment ID..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select name="method" class="form-select" onchange="this.form.submit()">
                <option value="">-- All Channels --</option>
                <option value="Wallet" <?= $methodFilter === 'Wallet' ? 'selected' : '' ?>>👛 Smart Wallet</option>
                <option value="Mobile Banking" <?= $methodFilter === 'Mobile Banking' ? 'selected' : '' ?>>📱 Mobile Banking</option>
                <option value="Card" <?= $methodFilter === 'Card' ? 'selected' : '' ?>>💳 Card</option>
                <option value="Cash" <?= $methodFilter === 'Cash' ? 'selected' : '' ?>>💵 Cash</option>
            </select>
        </div>

        <div class="form-group" style="align-self: flex-end;">
            <button type="submit" class="btn btn-primary">Filter Ledger</button>
            <a href="payments.php" class="btn btn-secondary ml-1">Reset</a>
        </div>
    </form>
</div>

<!-- Payments Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Payment Transactions Ledger (<?= count($payments) ?> Records)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Transaction Time</th>
                    <th>Customer</th>
                    <th>Bicycle</th>
                    <th>Base + Additional</th>
                    <th>Coupon</th>
                    <th>Method</th>
                    <th>Final Bill</th>
                    <th>Status</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">No payment records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['payment_id'] ?></strong></td>
                            <td><small><?= formatDateTime($p['trans_time']) ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($p['customer_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($p['customer_email']) ?></small>
                            </td>
                            <td>Cycle #<?= $p['B_id'] ?> (<code><?= htmlspecialchars($p['QR_code']) ?></code>)</td>
                            <td>
                                <?= formatCurrency((float)$p['base_fee']) ?> + <?= formatCurrency((float)$p['additional_fee']) ?>
                            </td>
                            <td>
                                <?php if (!empty($p['applied_coupon']) && $p['applied_coupon'] !== 'NONE'): ?>
                                    <span class="badge badge-success"><?= htmlspecialchars($p['applied_coupon']) ?> (-<?= formatCurrency((float)$p['discount']) ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($p['payment_method'] ?? 'Wallet') ?></span>
                            </td>
                            <td class="font-bold text-success font-mono" style="font-size: 1.05rem;">
                                <?= formatCurrency((float)$p['Bill']) ?>
                            </td>
                            <td><?= getStatusBadge($p['payment_status']) ?></td>
                            <td>
                                <a href="../payment.php?payment_id=<?= $p['payment_id'] ?>" class="btn btn-xs btn-outline-primary" target="_blank">Invoice ↗</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
