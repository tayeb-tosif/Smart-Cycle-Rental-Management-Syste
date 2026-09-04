<?php
/**
 * Global Navigation Bar Component
 * Smart Cycle Rental Management System
 */

$isInAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$basePath = $isInAdmin ? '../' : '';
$currentPage = basename($_SERVER['PHP_SELF']);

$isAuth = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_type'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
$userWallet = isset($_SESSION['wallet']) ? (float)$_SESSION['wallet'] : 0.00;
?>

<header class="navbar-wrapper">
    <div class="container navbar-container">
        <!-- Logo -->
        <a href="<?= $basePath ?>index.php" class="brand-logo">
            <span class="logo-icon">🚲</span>
            <span class="logo-text">Smart<strong>Cycle</strong></span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <!-- Navigation Links -->
        <nav class="nav-menu" id="navMenu">
            <ul class="nav-list">
                <?php if (!$isAuth): ?>
                    <!-- Guest Navigation -->
                    <li class="nav-item"><a href="<?= $basePath ?>index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>stations.php" class="nav-link <?= $currentPage === 'stations.php' ? 'active' : '' ?>">Stations</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>availability.php" class="nav-link <?= $currentPage === 'availability.php' ? 'active' : '' ?>">Live Availability</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>cycles.php" class="nav-link <?= $currentPage === 'cycles.php' ? 'active' : '' ?>">Bicycles</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>login.php" class="btn btn-outline-nav">Sign In</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>register.php" class="btn btn-primary-nav">Register</a></li>

                <?php elseif ($userRole === 'customer'): ?>
                    <!-- Customer Navigation -->
                    <li class="nav-item"><a href="<?= $basePath ?>dashboard.php" class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>stations.php" class="nav-link <?= $currentPage === 'stations.php' ? 'active' : '' ?>">Stations</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>cycles.php" class="nav-link <?= $currentPage === 'cycles.php' ? 'active' : '' ?>">Rent a Cycle</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>my_rental.php" class="nav-link nav-highlight <?= $currentPage === 'my_rental.php' ? 'active' : '' ?>">Active Ride</a></li>
                    
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" id="historyDropdown">History ▾</a>
                        <ul class="dropdown-menu">
                            <li><a href="<?= $basePath ?>rental_history.php">Rental History</a></li>
                            <li><a href="<?= $basePath ?>payment_history.php">Payment History</a></li>
                        </ul>
                    </li>

                    <li class="nav-item"><a href="<?= $basePath ?>wallet.php" class="nav-link wallet-badge <?= $currentPage === 'wallet.php' ? 'active' : '' ?>">👛 <?= number_format($userWallet, 2) ?> Tk</a></li>

                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle user-menu-link" id="userDropdown">
                            <span class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                            <span class="user-name-short"><?= htmlspecialchars(explode(' ', $userName)[0]) ?></span> ▾
                        </a>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <li class="dropdown-header">Signed in as <strong><?= htmlspecialchars($userName) ?></strong></li>
                            <li><a href="<?= $basePath ?>profile.php">My Profile</a></li>
                            <li><a href="<?= $basePath ?>wallet.php">My Wallet</a></li>
                            <li class="divider"></li>
                            <li><a href="<?= $basePath ?>logout.php" class="text-danger">Logout</a></li>
                        </ul>
                    </li>

                <?php elseif ($userRole === 'staff' || $userRole === 'admin'): ?>
                    <!-- Admin & Staff Navigation -->
                    <li class="nav-item"><a href="<?= $basePath ?>admin/dashboard.php" class="nav-link <?= ($isInAdmin && $currentPage === 'dashboard.php') ? 'active' : '' ?>">Dashboard</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>admin/bicycles.php" class="nav-link <?= ($isInAdmin && $currentPage === 'bicycles.php') ? 'active' : '' ?>">Bicycles</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>admin/stations.php" class="nav-link <?= ($isInAdmin && $currentPage === 'stations.php') ? 'active' : '' ?>">Stations</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>admin/rentals.php" class="nav-link <?= ($isInAdmin && $currentPage === 'rentals.php') ? 'active' : '' ?>">Rentals</a></li>
                    <li class="nav-item"><a href="<?= $basePath ?>admin/payments.php" class="nav-link <?= ($isInAdmin && $currentPage === 'payments.php') ? 'active' : '' ?>">Payments</a></li>
                    
                    <?php if ($userRole === 'admin'): ?>
                        <li class="nav-item"><a href="<?= $basePath ?>admin/coupons.php" class="nav-link <?= ($isInAdmin && $currentPage === 'coupons.php') ? 'active' : '' ?>">Coupons</a></li>
                    <?php endif; ?>

                    <li class="nav-item"><a href="<?= $basePath ?>admin/users.php" class="nav-link <?= ($isInAdmin && $currentPage === 'users.php') ? 'active' : '' ?>">Users</a></li>

                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle user-menu-link" id="adminDropdown">
                            <span class="user-avatar badge-admin"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                            <span class="user-name-short"><?= htmlspecialchars(explode(' ', $userName)[0]) ?> (<?= ucfirst($userRole) ?>)</span> ▾
                        </a>
                        <ul class="dropdown-menu dropdown-menu-right">
                            <li class="dropdown-header"><?= ucfirst($userRole) ?> Portal</li>
                            <li><a href="<?= $basePath ?>profile.php">My Profile</a></li>
                            <li><a href="<?= $basePath ?>index.php" target="_blank">View Public Site ↗</a></li>
                            <li class="divider"></li>
                            <li><a href="<?= $basePath ?>logout.php" class="text-danger">Logout</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
