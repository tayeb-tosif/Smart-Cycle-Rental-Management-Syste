<?php
/**
 * Public Landing Page
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/pricing.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Fetch quick fleet stats
$totalBikes = (int)$pdo->query("SELECT COUNT(*) FROM bicycle")->fetchColumn();
$totalStations = (int)$pdo->query("SELECT COUNT(*) FROM station")->fetchColumn();
$totalRentals = (int)$pdo->query("SELECT COUNT(*) FROM rental_trans WHERE status = 'Completed'")->fetchColumn();

// Fetch live stations
$stations = $pdo->query("
    SELECT s.Location, s.capacity, COALESCE(a.cycle_availability, 0) AS cycle_availability
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    ORDER BY s.Location ASC
    LIMIT 6
")->fetchAll();

$pageTitle = "Smart Cycle Rental - Sustainable Campus & City Mobility";
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-content">
        <div class="hero-badge">🚲 University CSE Database Management System Project</div>
        <h1 class="hero-title">Smart, Green & Frictionless Cycle Sharing</h1>
        <p class="hero-subtitle">
            Rent bicycles instantly from any campus or city hub. Dock anywhere, pay via Smart Wallet or Mobile Banking, and help keep our environment clean.
        </p>

        <div class="hero-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn btn-primary btn-lg">Go to My Dashboard →</a>
                <a href="cycles.php" class="btn btn-outline-nav btn-lg ml-2">Browse Fleet</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg">Get Started / Register</a>
                <a href="login.php" class="btn btn-outline-nav btn-lg ml-2">Account Sign In</a>
            <?php endif; ?>
        </div>

        <!-- Quick Platform Stats -->
        <div class="hero-stats-row">
            <div class="hero-stat-item">
                <h3><?= $totalStations ?> Hubs</h3>
                <p>Strategic Stations</p>
            </div>
            <div class="hero-stat-item">
                <h3><?= $totalBikes ?>+ Bikes</h3>
                <p>Smart Cycle Fleet</p>
            </div>
            <div class="hero-stat-item">
                <h3><?= $totalRentals ?>+ Rides</h3>
                <p>Completed Trips</p>
            </div>
            <div class="hero-stat-item">
                <h3>20 Tk</h3>
                <p>Base Unlock Rate</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="section-block">
    <div class="text-center mb-5">
        <span class="badge badge-primary">Frictionless Workflow</span>
        <h2 class="section-heading mt-2">How Smart Cycle Rental Works</h2>
        <p class="text-muted">4 simple steps to start your sustainable journey</p>
    </div>

    <div class="grid grid-4">
        <div class="card step-card text-center">
            <div class="step-number">1</div>
            <div class="step-icon">📱</div>
            <h4>Create Account</h4>
            <p class="text-muted text-sm">Register with your NID and phone number in under 60 seconds.</p>
        </div>

        <div class="card step-card text-center">
            <div class="step-number">2</div>
            <div class="step-icon">🚲</div>
            <h4>Select & Unlock</h4>
            <p class="text-muted text-sm">Choose an available cycle at any station hub. The lock opens automatically.</p>
        </div>

        <div class="card step-card text-center">
            <div class="step-number">3</div>
            <div class="step-icon">🚴</div>
            <h4>Ride Anywhere</h4>
            <p class="text-muted text-sm">Enjoy your eco-friendly commute with real-time live duration tracking.</p>
        </div>

        <div class="card step-card text-center">
            <div class="step-number">4</div>
            <div class="step-icon">🏁</div>
            <h4>Dock & Settle</h4>
            <p class="text-muted text-sm">Return to any registered dock station and settle via Wallet or Mobile Banking.</p>
        </div>
    </div>
</section>

<!-- Transparent Pricing Section -->
<section class="section-block bg-card-section p-4 rounded-lg my-5">
    <div class="text-center mb-4">
        <span class="badge badge-success">Transparent & Affordable</span>
        <h2 class="section-heading mt-2">Student & Commuter Pricing</h2>
        <p class="text-muted">No hidden charges. Configured centrally in <code>config/pricing.php</code>.</p>
    </div>

    <div class="grid grid-3">
        <div class="card pricing-box text-center">
            <h4 class="text-muted">Base Unlock</h4>
            <h2 class="pricing-headline text-primary mt-2">20 Tk</h2>
            <p class="text-muted text-sm">Includes cycle unlock and first 30 minutes of ride time</p>
            <ul class="pricing-features-list">
                <li>✔ Instant Smart Lock release</li>
                <li>✔ 30 Minutes included</li>
                <li>✔ Station-to-station mobility</li>
            </ul>
        </div>

        <div class="card pricing-box text-center pricing-featured">
            <div class="badge badge-primary mb-2">Standard Rate</div>
            <h4 class="text-muted">Additional Time</h4>
            <h2 class="pricing-headline text-primary mt-2">+5 Tk</h2>
            <p class="text-muted text-sm">Per every 30-minute block or fraction thereof</p>
            <ul class="pricing-features-list">
                <li>✔ 60 mins ride = 25 Tk</li>
                <li>✔ 90 mins ride = 30 Tk</li>
                <li>✔ 120 mins ride = 35 Tk</li>
            </ul>
        </div>

        <div class="card pricing-box text-center">
            <h4 class="text-muted">Promo Coupons</h4>
            <h2 class="pricing-headline text-success mt-2">10% - 25% OFF</h2>
            <p class="text-muted text-sm">Special discounts for university students and frequent riders</p>
            <ul class="pricing-features-list">
                <li>✔ Use code <code>WELCOME10</code></li>
                <li>✔ Use code <code>SAVE20</code></li>
                <li>✔ Direct discount on final bill</li>
            </ul>
        </div>
    </div>
</section>

<!-- Live Stations Hub Grid -->
<section class="section-block mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="section-heading">Station Hub Network</h2>
            <p class="text-muted">Real-time dock availability across campus and city locations</p>
        </div>
        <a href="availability.php" class="btn btn-outline-primary">View Live Board →</a>
    </div>

    <div class="grid grid-3">
        <?php foreach ($stations as $st): 
            $avail = (int)$st['cycle_availability'];
            $cap = (int)$st['capacity'];
            $pct = $cap > 0 ? min(100, round(($avail / $cap) * 100)) : 0;
        ?>
            <div class="card station-card">
                <div class="station-header">
                    <div class="station-icon">📍</div>
                    <div>
                        <h4 class="station-name mb-0"><?= htmlspecialchars($st['Location']) ?></h4>
                        <small class="text-muted">Capacity: <?= $cap ?> Docks</small>
                    </div>
                </div>
                <div class="station-body mt-3">
                    <div class="d-flex justify-content-between text-sm mb-1">
                        <span>Availability</span>
                        <strong><?= $avail ?> Cycles</strong>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width: <?= $pct ?>%; background: <?= $avail > 3 ? 'var(--primary-color)' : ($avail > 0 ? 'var(--warning-color)' : 'var(--danger-color)') ?>;"></div>
                    </div>
                </div>
                <div class="station-footer mt-3">
                    <a href="cycles.php?station=<?= urlencode($st['Location']) ?>" class="btn btn-sm btn-outline-primary btn-block">
                        View Cycles Here
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
