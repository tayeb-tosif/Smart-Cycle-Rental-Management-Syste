<?php
/**
 * Global Header Component
 * Smart Cycle Rental Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine relative path depth
$isInAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$basePath = $isInAdmin ? '../' : '';

$pageTitle = isset($pageTitle) ? $pageTitle . ' - Smart Cycle Rental' : 'Smart Cycle Rental - Sustainable Urban Mobility';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Smart Cycle Rental Management System - University CSE DBMS Project. Easy bicycle rent, return, wallet management, and real-time station availability.">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $basePath ?>css/style.css">
</head>
<body>
    <div class="site-wrapper">
        <?php include __DIR__ . '/navbar.php'; ?>
        <main class="main-content">
            <div class="container">
                <?php 
                if (function_exists('displayFlashMessages')) {
                    displayFlashMessages(); 
                }
                ?>
