<?php
/**
 * Station Management (Admin & Staff)
 * Feature 2: Station & Availability Management
 * Smart Cycle Rental Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Staff or Admin access
requireStaffOrAdmin();

$errors = [];
$success = '';

// Handle Add Station
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_station') {
    $location = sanitizeInput($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 10);
    $managerId = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

    if (empty($location)) {
        $errors[] = "Station Location/Name is required.";
    }
    if ($capacity < 1) {
        $errors[] = "Station Capacity must be at least 1.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert into station table
            $stmt = $pdo->prepare("INSERT INTO station (Location, capacity, User_id) VALUES (:loc, :cap, :uid)");
            $stmt->execute([
                'loc' => $location,
                'cap' => $capacity,
                'uid' => $managerId
            ]);

            // Initialize availability table
            $stmtAvail = $pdo->prepare("INSERT INTO availability (Location, cycle_availability) VALUES (:loc, 0)");
            $stmtAvail->execute(['loc' => $location]);

            $pdo->commit();
            setFlashMessage('success', "Station '{$location}' created successfully.");
            header("Location: stations.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $errors[] = "A station with location name '{$location}' already exists.";
            } else {
                $errors[] = "Failed to create station: " . $e->getMessage();
            }
        }
    }
}

// Handle Edit Station
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_station') {
    $oldLocation = sanitizeInput($_POST['old_location'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 10);
    $managerId   = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;

    if ($capacity < 1) {
        $errors[] = "Capacity must be at least 1.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE station SET capacity = :cap, User_id = :uid WHERE Location = :loc");
            $stmt->execute([
                'cap' => $capacity,
                'uid' => $managerId,
                'loc' => $oldLocation
            ]);

            // Ensure availability does not exceed new capacity
            $stmtAdj = $pdo->prepare("UPDATE availability SET cycle_availability = LEAST(cycle_availability, :cap) WHERE Location = :loc");
            $stmtAdj->execute(['cap' => $capacity, 'loc' => $oldLocation]);

            setFlashMessage('success', "Station '{$oldLocation}' updated successfully.");
            header("Location: stations.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Failed to update station: " . $e->getMessage();
        }
    }
}

// Handle Delete Station
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_station') {
    // Only admin can delete stations
    if ($_SESSION['user_type'] !== 'admin') {
        $errors[] = "Only Admins have permission to delete stations.";
    } else {
        $delLocation = sanitizeInput($_POST['station_location'] ?? '');
        
        // Check if bicycles or active rentals are linked
        $checkCycles = $pdo->prepare("SELECT COUNT(*) FROM bicycle WHERE location = :loc OR s_location = :loc");
        $checkCycles->execute(['loc' => $delLocation]);
        $cycleCount = (int)$checkCycles->fetchColumn();

        if ($cycleCount > 0) {
            $errors[] = "Cannot delete station '{$delLocation}'. There are {$cycleCount} bicycle(s) assigned to this location. Reassign them first.";
        } else {
            try {
                $stmtDel = $pdo->prepare("DELETE FROM station WHERE Location = :loc");
                $stmtDel->execute(['loc' => $delLocation]);
                setFlashMessage('success', "Station '{$delLocation}' deleted successfully.");
                header("Location: stations.php");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Cannot delete station: " . $e->getMessage();
            }
        }
    }
}

// Handle Sync Availability with actual bicycle counts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sync_availability') {
    try {
        $pdo->exec("
            UPDATE availability a
            SET cycle_availability = (
                SELECT COUNT(*) 
                FROM bicycle b 
                WHERE b.location = a.Location 
                  AND b.user_id IS NULL
            )
        ");
        setFlashMessage('success', "Station availability successfully synchronized with current bicycle inventory.");
        header("Location: stations.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Sync error: " . $e->getMessage();
    }
}

// Fetch stations
$stmt = $pdo->query("
    SELECT 
        s.Location,
        s.capacity,
        s.User_id,
        u.Name AS manager_name,
        COALESCE(a.cycle_availability, 0) AS cycle_availability,
        (SELECT COUNT(*) FROM bicycle b WHERE b.location = s.Location) AS physical_cycles_count
    FROM station s
    LEFT JOIN availability a ON s.Location = a.Location
    LEFT JOIN user u ON s.User_id = u.user_id
    ORDER BY s.Location ASC
");
$stations = $stmt->fetchAll();

// Fetch staff and admin users for manager dropdown
$staffList = $pdo->query("SELECT user_id, Name, User_type FROM user WHERE User_type IN ('staff', 'admin') ORDER BY Name ASC")->fetchAll();

$pageTitle = "Station Management";
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Station & Hub Management</h1>
        <p class="page-subtitle">Add stations, set dock capacities, assign station managers, and audit availability</p>
    </div>
    <div class="header-action">
        <form action="stations.php" method="POST" style="display:inline;">
            <input type="hidden" name="action" value="sync_availability">
            <button type="submit" class="btn btn-outline-secondary" title="Recalculate availability from bicycle locations">🔄 Sync Counts</button>
        </form>
        <button type="button" class="btn btn-primary" onclick="toggleAddModal()">+ Add New Station</button>
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

<!-- Stations Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Cycle Stations</h3>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Location Name</th>
                    <th>Capacity</th>
                    <th>Available</th>
                    <th>Physical Bikes</th>
                    <th>Assigned Manager</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($stations)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No stations found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($stations as $st): ?>
                        <tr>
                            <td>
                                <strong>📍 <?= htmlspecialchars($st['Location']) ?></strong>
                            </td>
                            <td><?= (int)$st['capacity'] ?> Docks</td>
                            <td>
                                <span class="font-bold text-success"><?= (int)$st['cycle_availability'] ?></span>
                            </td>
                            <td><?= (int)$st['physical_cycles_count'] ?></td>
                            <td>
                                <?= htmlspecialchars($st['manager_name'] ?? 'Unassigned') ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick='openEditModal(<?= json_encode($st) ?>)'>Edit</button>
                                
                                <?php if ($_SESSION['user_type'] === 'admin'): ?>
                                    <form action="stations.php" method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete station <?= htmlspecialchars(addslashes($st['Location'])) ?>?');">
                                        <input type="hidden" name="action" value="delete_station">
                                        <input type="hidden" name="station_location" value="<?= htmlspecialchars($st['Location']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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

<!-- Modal: Add Station -->
<div class="modal" id="addStationModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Cycle Station</h3>
                <button type="button" class="modal-close" onclick="toggleAddModal()">&times;</button>
            </div>
            <form action="stations.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_station">

                    <div class="form-group mb-3">
                        <label class="form-label">Station Location / Name <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Uttara Sector 3" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Total Dock Capacity <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" class="form-control" value="15" min="1" max="200" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Assigned Station Manager / Staff</label>
                        <select name="manager_id" class="form-select">
                            <option value="">-- None / Unassigned --</option>
                            <?php foreach ($staffList as $stf): ?>
                                <option value="<?= $stf['user_id'] ?>">
                                    <?= htmlspecialchars($stf['Name']) ?> (<?= ucfirst($stf['User_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Station</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Station -->
<div class="modal" id="editStationModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Station: <span id="editModalLocationTitle"></span></h3>
                <button type="button" class="modal-close" onclick="toggleEditModal()">&times;</button>
            </div>
            <form action="stations.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_station">
                    <input type="hidden" name="old_location" id="editOldLocation">

                    <div class="form-group mb-3">
                        <label class="form-label">Total Dock Capacity <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" id="editCapacity" class="form-control" min="1" max="200" required>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Assigned Station Manager / Staff</label>
                        <select name="manager_id" id="editManagerId" class="form-select">
                            <option value="">-- None / Unassigned --</option>
                            <?php foreach ($staffList as $stf): ?>
                                <option value="<?= $stf['user_id'] ?>">
                                    <?= htmlspecialchars($stf['Name']) ?> (<?= ucfirst($stf['User_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="toggleEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Station Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAddModal() {
    const m = document.getElementById('addStationModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function toggleEditModal() {
    const m = document.getElementById('editStationModal');
    m.style.display = m.style.display === 'none' ? 'flex' : 'none';
}

function openEditModal(st) {
    document.getElementById('editModalLocationTitle').innerText = st.Location;
    document.getElementById('editOldLocation').value = st.Location;
    document.getElementById('editCapacity').value = st.capacity;
    document.getElementById('editManagerId').value = st.User_id || '';
    toggleEditModal();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
