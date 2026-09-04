<?php
/**
 * Bicycle Management (Admin & Staff)
 * Feature 3: Bicycle Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireStaffOrAdmin();

$errors = [];
$success = '';

// Handle Add Bicycle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bicycle') {
    $qrCode    = sanitizeInput($_POST['qr_code'] ?? '');
    $location  = sanitizeInput($_POST['location'] ?? '');
    $condition = sanitizeInput($_POST['condition'] ?? 'Good');

    if (empty($qrCode)) {
        $qrCode = 'QR-' . strtoupper(substr($location, 0, 3)) . '-' . rand(100, 999);
    }
    if (empty($location)) {
        $errors[] = "Starting station location is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert into bicycle table
            $stmt = $pdo->prepare("
                INSERT INTO bicycle (QR_code, location, locked, unlocked, s_location, user_id)
                VALUES (:qr, :loc, 1, 0, :sloc, NULL)
            ");
            $stmt->execute([
                'qr'   => $qrCode,
                'loc'  => $location,
                'sloc' => $location
            ]);
            $bikeId = (int)$pdo->lastInsertId();

            // Insert into condition_type table
            $stmtCond = $pdo->prepare("INSERT INTO condition_type (B_id, condition) VALUES (:bid, :cond)");
            $stmtCond->execute([
                'bid'  => $bikeId,
                'cond' => $condition
            ]);

            // If condition allows rental, increment availability
            if (!in_array($condition, ['Damaged', 'Under Maintenance'], true)) {
                $stmtAvail = $pdo->prepare("UPDATE availability SET cycle_availability = cycle_availability + 1 WHERE Location = :loc");
                $stmtAvail->execute(['loc' => $location]);
            }

            $pdo->commit();
            setFlashMessage('success', "Bicycle #{$bikeId} ({$qrCode}) added successfully to {$location}.");
            header("Location: bicycles.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $errors[] = "A bicycle with QR Code '{$qrCode}' already exists.";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Handle Edit Bicycle & Condition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_bicycle') {
    $bikeId       = (int)($_POST['bike_id'] ?? 0);
    $qrCode       = sanitizeInput($_POST['qr_code'] ?? '');
    $newLocation  = sanitizeInput($_POST['location'] ?? '');
    $newCondition = sanitizeInput($_POST['condition'] ?? 'Good');

    if ($bikeId <= 0 || empty($qrCode)) {
        $errors[] = "Invalid bicycle details.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Get previous state
            $prevStmt = $pdo->prepare("
                SELECT b.*, c.condition AS prev_cond 
                FROM bicycle b 
                LEFT JOIN condition_type c ON b.ID = c.B_id 
                WHERE b.ID = :bid FOR UPDATE
            ");
            $prevStmt->execute(['bid' => $bikeId]);
            $prev = $prevStmt->fetch();

            if (!$prev) {
                throw new Exception("Bicycle not found.");
            }

            if (!empty($prev['user_id'])) {
                throw new Exception("Cannot edit location while bicycle is currently on an active rental.");
            }

            // Update bicycle
            $updateBike = $pdo->prepare("UPDATE bicycle SET QR_code = :qr, location = :loc WHERE ID = :bid");
            $updateBike->execute([
                'qr'  => $qrCode,
                'loc' => !empty($newLocation) ? $newLocation : null,
                'bid' => $bikeId
            ]);

            // Update condition_type
            $updateCond = $pdo->prepare("
                INSERT INTO condition_type (B_id, condition) 
                VALUES (:bid, :cond) 
                ON DUPLICATE KEY UPDATE condition = :cond2
            ");
            $updateCond->execute([
                'bid'   => $bikeId,
                'cond'  => $newCondition,
                'cond2' => $newCondition
            ]);

            // Recalculate station availability for old and new locations
            $syncStmt = $pdo->prepare("
                UPDATE availability a 
                SET cycle_availability = (
                    SELECT COUNT(*) FROM bicycle b 
                    LEFT JOIN condition_type c ON b.ID = c.B_id
                    WHERE b.location = a.Location 
                      AND b.user_id IS NULL
                      AND (c.condition IS NULL OR c.condition NOT IN ('Damaged', 'Under Maintenance'))
                )
            ");
            $syncStmt->execute();

            $pdo->commit();
            setFlashMessage('success', "Bicycle #{$bikeId} details and condition updated.");
            header("Location: bicycles.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Handle Lock/Unlock Remote Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock') {
    $bikeId = (int)($_POST['bike_id'] ?? 0);
    $targetState = $_POST['target_state'] ?? 'lock'; // 'lock' or 'unlock'

    try {
        $locked = $targetState === 'lock' ? 1 : 0;
        $unlocked = $targetState === 'lock' ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE bicycle SET locked = :l, unlocked = :u WHERE ID = :bid");
        $stmt->execute(['l' => $locked, 'u' => $unlocked, 'bid' => $bikeId]);

        setFlashMessage('success', "Bicycle #{$bikeId} remote lock status updated to: " . strtoupper($targetState) . "ED.");
        header("Location: bicycles.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Failed to toggle lock: " . $e->getMessage();
    }
}

// Handle Delete Bicycle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bicycle') {
    if ($_SESSION['user_type'] !== 'admin') {
        $errors[] = "Only Admins have permission to delete bicycles.";
    } else {
        $bikeId = (int)($_POST['bike_id'] ?? 0);
        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare("SELECT user_id, location FROM bicycle WHERE ID = :bid");
            $check->execute(['bid' => $bikeId]);
            $bike = $check->fetch();

            if (!$bike) {
                throw new Exception("Bicycle not found.");
            }
            if (!empty($bike['user_id'])) {
                throw new Exception("Cannot delete bicycle #{$bikeId} because it is currently rented by an active user.");
            }

            $oldLoc = $bike['location'];

            // Delete condition
            $pdo->prepare("DELETE FROM condition_type WHERE B_id = :bid")->execute(['bid' => $bikeId]);
            // Delete bicycle
            $pdo->prepare("DELETE FROM bicycle WHERE ID = :bid")->execute(['bid' => $bikeId]);

            // Decrement availability
            if (!empty($oldLoc)) {
                $pdo->prepare("UPDATE availability SET cycle_availability = GREATEST(0, cycle_availability - 1) WHERE Location = :loc")
                    ->execute(['loc' => $oldLoc]);
            }

            $pdo->commit();
            setFlashMessage('success', "Bicycle #{$bikeId} removed from fleet.");
            header("Location: bicycles.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Fetch all bicycles
$stmt = $pdo->query("
    SELECT 
        b.ID,
        b.QR_code,
        b.location,
        b.locked,
        b.unlocked,
        b.s_location,
        b.user_id,
        u.Name AS rider_name,
        u.Email AS rider_email,
        COALESCE(c.condition, 'Good') AS cycle_condition,
        c.last_checked
    FROM bicycle b
    LEFT JOIN condition_type c ON b.ID = c.B_id
    LEFT JOIN user u ON b.user_id = u.user_id
    ORDER BY b.ID ASC
");
$bicycles = $stmt->fetchAll();

// Fetch stations for dropdowns
$stations = $pdo->query("SELECT Location FROM station ORDER BY Location ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Bicycle Fleet Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Bicycle Fleet Management</h1>
        <p class="page-subtitle">Add cycles, inspect physical conditions, perform remote lock/unlock, and manage dock assignments</p>
    </div>
    <div class="header-action">
        <button type="button" class="btn btn-primary" onclick="toggleAddModal()">+ Add New Bicycle</button>
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

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Bicycles (<?= count($bicycles) ?> Total Fleet)</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>QR Code</th>
                    <th>Current Station</th>
                    <th>Base Station</th>
                    <th>Condition</th>
                    <th>Lock State</th>
                    <th>Rider Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bicycles)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No bicycles found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bicycles as $bike): 
                        $isRented = !empty($bike['user_id']);
                    ?>
                        <tr>
                            <td><strong>#<?= $bike['ID'] ?></strong></td>
                            <td><code><?= htmlspecialchars($bike['QR_code']) ?></code></td>
                            <td>
                                <?= $bike['location'] ? '📍 ' . htmlspecialchars($bike['location']) : '<span class="text-warning">On Trip</span>' ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($bike['s_location'] ?? '—') ?></small></td>
                            <td><?= getConditionBadge($bike['cycle_condition']) ?></td>
                            <td>
                                <form action="bicycles.php" method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="toggle_lock">
                                    <input type="hidden" name="bike_id" value="<?= $bike['ID'] ?>">
                                    <?php if ($bike['locked'] == 1): ?>
                                        <input type="hidden" name="target_state" value="unlock">
                                        <button type="submit" class="btn btn-xs btn-success" title="Click to unlock remotely">🔒 Locked</button>
                                    <?php else: ?>
                                        <input type="hidden" name="target_state" value="lock">
                                        <button type="submit" class="btn btn-xs btn-warning" title="Click to lock remotely">🔓 Unlocked</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <?php if ($isRented): ?>
                                    <span class="badge badge-info" title="<?= htmlspecialchars($bike['rider_email']) ?>">
                                        Rider: <?= htmlspecialchars($bike['rider_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-success">Available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick='openEditModal(<?= json_encode($bike) ?>)'>Edit</button>
                                
                                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    <form action="bicycles.php" method="POST" class="inline-form" onsubmit="return confirm('Delete Bicycle #<?= $bike['ID'] ?>?');">
                                        <input type="hidden" name="action" value="delete_bicycle">
                                        <input type="hidden" name="bike_id" value="<?= $bike['ID'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" <?= $isRented ? 'disabled' : '' ?>>Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Bicycle -->
<div class="modal" id="addBikeModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Bicycle to Fleet</h3>
                <button type="button" class="modal-close" onclick="toggleAddModal()">&times;</button>
            </div>
            <form action="bicycles.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_bicycle">

                    <div class="form-group mb-3">
                        <label class="form-label">QR Code Identifier (Leave blank to auto-generate)</label>
                        <input type="text" name="qr_code" class="form-control" placeholder="e.g. QR-BRAC-020">
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Assign Starting Station <span class="text-danger">*</span></label>
                        <select name="location" class="form-select" required>
                            <option value="">-- Select Station --</option>
                            <?php foreach ($stations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>">📍 <?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Initial Physical Condition <span class="text-danger">*</span></label>
                        <select name="condition" class="form-select" required>
                            <option value="Excellent">Excellent (New / Fully Tuned)</option>
                            <option value="Good" selected>Good (Ready for Rent)</option>
                            <option value="Fair">Fair (Minor cosmetic wear)</option>
                            <option value="Under Maintenance">Under Maintenance (Not rentable)</option>
                            <option value="Damaged">Damaged (Requires repair)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bicycle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Bicycle -->
<div class="modal" id="editBikeModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Bicycle #<span id="editModalBikeIdTitle"></span></h3>
                <button type="button" class="modal-close" onclick="toggleEditModal()">&times;</button>
            </div>
            <form action="bicycles.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_bicycle">
                    <input type="hidden" name="bike_id" id="editBikeId">

                    <div class="form-group mb-3">
                        <label class="form-label">QR Code Identifier <span class="text-danger">*</span></label>
                        <input type="text" name="qr_code" id="editQrCode" class="form-control" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Assigned Station Location</label>
                        <select name="location" id="editLocation" class="form-select">
                            <option value="">-- None / On Road --</option>
                            <?php foreach ($stations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>">📍 <?= htmlspecialchars($loc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Physical Condition <span class="text-danger">*</span></label>
                        <select name="condition" id="editCondition" class="form-select" required>
                            <option value="Excellent">Excellent</option>
                            <option value="Good">Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Under Maintenance">Under Maintenance (Cannot be rented)</option>
                            <option value="Damaged">Damaged (Cannot be rented)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddModal() {
    const m = document.getElementById('addBikeModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function toggleEditModal() {
    const m = document.getElementById('editBikeModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function openEditModal(bike) {
    document.getElementById('editModalBikeIdTitle').innerText = bike.ID;
    document.getElementById('editBikeId').value = bike.ID;
    document.getElementById('editQrCode').value = bike.QR_code;
    document.getElementById('editLocation').value = bike.location || '';
    document.getElementById('editCondition').value = bike.cycle_condition;
    toggleEditModal();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
