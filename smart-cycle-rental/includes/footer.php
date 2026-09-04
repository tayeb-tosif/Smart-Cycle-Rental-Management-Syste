<?php
/**
 * Global Footer Component
 * Smart Cycle Rental Management System
 */

$isInAdmin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$basePath = $isInAdmin ? '../' : '';
?>
            </div><!-- /.container -->
        </main><!-- /.main-content -->

        <footer class="site-footer">
            <div class="container footer-container">
                <div class="footer-col">
                    <div class="footer-logo">
                        <span class="logo-icon">🚲</span>
                        <span class="logo-text">Smart<strong>Cycle</strong></span>
                    </div>
                    <p class="footer-desc">
                        A modern, sustainable cycle sharing platform. Database Management System Project built with PHP 8+ and MySQL.
                    </p>
                </div>
                
                <div class="footer-col">
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="<?= $basePath ?>stations.php">Station Network</a></li>
                        <li><a href="<?= $basePath ?>availability.php">Live Availability</a></li>
                        <li><a href="<?= $basePath ?>cycles.php">Browse Bicycles</a></li>
                        <li><a href="<?= $basePath ?>login.php">Account Sign In</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4 class="footer-title">System Information</h4>
                    <p class="footer-info"><strong>Project:</strong> Smart Cycle Rental</p>
                    <p class="footer-info"><strong>Database:</strong> MySQL (smart_cycle_rental)</p>
                    <p class="footer-info"><strong>Environment:</strong> Localhost / XAMPP</p>
                    <p class="footer-info"><strong>Base Fare:</strong> 20 Tk + 5 Tk/30m</p>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="container footer-bottom-inner">
                    <p>&copy; <?= date('Y') ?> Smart Cycle Rental Management System. All Rights Reserved.</p>
                    <p class="badge badge-outline">University DBMS Project</p>
                </div>
            </div>
        </footer>
    </div><!-- /.site-wrapper -->

    <!-- Custom JavaScript -->
    <script src="<?= $basePath ?>js/script.js"></script>
</body>
</html>
