<?php
/**
 * Common Helper Functions
 * Smart Cycle Rental Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize string input to prevent XSS
 */
function sanitizeInput(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash alert message for current/next request
 */
function setFlashMessage(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Display and clear flash alert message
 */
function displayFlashMessages(): void {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $type = htmlspecialchars($flash['type']);
        $msg  = $flash['message']; // May contain formatted text
        echo "<div class='alert alert-{$type} fade-in' role='alert'>
                <span>{$msg}</span>
                <button type='button' class='alert-close' onclick='this.parentElement.remove();'>&times;</button>
              </div>";
        unset($_SESSION['flash']);
    }
}

/**
 * Format amount as currency string
 */
function formatCurrency(float $amount): string {
    return number_format($amount, 2) . ' ' . (defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'Tk');
}

/**
 * Format minutes into readable duration string (e.g. 1 hr 25 mins)
 */
function formatDuration(int $minutes): string {
    if ($minutes < 1) {
        return "Less than a min";
    }
    $hrs = intdiv($minutes, 60);
    $mins = $minutes % 60;
    
    if ($hrs > 0 && $mins > 0) {
        return "{$hrs} hr {$mins} min" . ($mins > 1 ? 's' : '');
    } elseif ($hrs > 0) {
        return "{$hrs} hr" . ($hrs > 1 ? 's' : '');
    } else {
        return "{$mins} min" . ($mins > 1 ? 's' : '');
    }
}

/**
 * Format database DATETIME to readable format
 */
function formatDateTime(?string $datetime): string {
    if (!$datetime) return '—';
    return date('d M Y, h:i A', strtotime($datetime));
}

/**
 * Check if a customer has an active ongoing rental
 */
function getActiveRental(int $userId, PDO $pdo): ?array {
    $stmt = $pdo->prepare("
        SELECT r.*, b.QR_code, b.location AS curr_loc, b.s_location, c.condition AS bike_condition
        FROM rental_trans r
        JOIN bicycle b ON r.B_id = b.ID
        LEFT JOIN condition_type c ON b.ID = c.B_id
        WHERE r.user_id = :uid AND r.status = 'Active'
        ORDER BY r.trans_id DESC
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $rental = $stmt->fetch();
    return $rental ?: null;
}

/**
 * Validate a promo coupon code against coupons catalog
 */
function validateCouponCode(string $code, float $billAmount, PDO $pdo): array {
    $code = strtoupper(trim($code));
    if (empty($code)) {
        return ['valid' => false, 'discount_amount' => 0.00, 'percentage' => 0, 'message' => 'Empty coupon code.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE coupon_code = :code LIMIT 1");
    $stmt->execute(['code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['valid' => false, 'discount_amount' => 0.00, 'percentage' => 0, 'message' => 'Invalid coupon code.'];
    }

    if ((int)$coupon['is_active'] !== 1) {
        return ['valid' => false, 'discount_amount' => 0.00, 'percentage' => 0, 'message' => 'This coupon has expired or is inactive.'];
    }

    if ($billAmount < (float)$coupon['min_amount']) {
        return [
            'valid' => false, 
            'discount_amount' => 0.00, 
            'percentage' => (int)$coupon['discount_percentage'], 
            'message' => "Minimum bill of " . formatCurrency((float)$coupon['min_amount']) . " required to apply this coupon."
        ];
    }

    $discountAmount = round(($billAmount * (int)$coupon['discount_percentage']) / 100, 2);
    return [
        'valid'           => true,
        'coupon_code'     => $coupon['coupon_code'],
        'percentage'      => (int)$coupon['discount_percentage'],
        'discount_amount' => $discountAmount,
        'message'         => "Coupon applied successfully! ({$coupon['discount_percentage']}% off)"
    ];
}

/**
 * Return badge HTML for bicycle condition
 */
function getConditionBadge(string $condition): string {
    $colorMap = [
        'Excellent'         => 'badge-success',
        'Good'              => 'badge-primary',
        'Fair'              => 'badge-warning',
        'Damaged'           => 'badge-danger',
        'Under Maintenance' => 'badge-dark',
    ];
    $cls = $colorMap[$condition] ?? 'badge-secondary';
    return "<span class='badge {$cls}'>" . htmlspecialchars($condition) . "</span>";
}

/**
 * Return badge HTML for status
 */
function getStatusBadge(string $status): string {
    $colorMap = [
        'Active'    => 'badge-info',
        'Completed' => 'badge-success',
        'Cancelled' => 'badge-danger',
        'Paid'      => 'badge-success',
        'Pending'   => 'badge-warning',
        'Failed'    => 'badge-danger',
    ];
    $cls = $colorMap[$status] ?? 'badge-secondary';
    return "<span class='badge {$cls}'>" . htmlspecialchars($status) . "</span>";
}
