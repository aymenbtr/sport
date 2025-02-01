<?php
session_start();

if (!isset($_SESSION['facility_id'])) {
    header("Location: facility-management.php");
    exit();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'test');

$facility_id = $_SESSION['facility_id'];

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get messages from session and clear them
$success_message = $_SESSION['success_message'] ?? "";
$error_message = $_SESSION['error_message'] ?? "";
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle user management actions
    if (isset($_POST['accept_user'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE users SET status = 'approved' WHERE id = ? AND facility_id = ?");
        $stmt->bind_param("ii", $user_id, $facility_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "User approved successfully";
        } else {
            $_SESSION['error_message'] = "Error approving user";
        }
    }

    if (isset($_POST['reject_user'])) {
        $user_id = intval($_POST['user_id']);
        $reason = $conn->real_escape_string($_POST['rejection_reason']);
        
        if (empty($reason)) {
            $_SESSION['error_message'] = "Rejection reason must be provided.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = 'rejected', rejection_reason = ? WHERE id = ? AND facility_id = ?");
            $stmt->bind_param("sii", $reason, $user_id, $facility_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "User rejected successfully.";
            } else {
                $_SESSION['error_message'] = "Error rejecting user: " . $stmt->error;
            }
        }
    }

    // Handle child management actions
if (isset($_POST['delete_child'])) {
    $child_id = intval($_POST['child_id']);
    $conn->begin_transaction();
    try {
        // First delete from child_sports
        $delete_sports = $conn->prepare("
            DELETE FROM child_sports 
            WHERE child_id = ? AND child_id IN (
                SELECT c.id 
                FROM children c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.id = ? AND u.facility_id = ?
            )
        ");
        $delete_sports->bind_param("iii", $child_id, $child_id, $facility_id);
        $delete_sports->execute();
        
        // Then delete from children
        $delete_child = $conn->prepare("
            DELETE c FROM children c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.id = ? AND u.facility_id = ?
        ");
        $delete_child->bind_param("ii", $child_id, $facility_id);
        $delete_child->execute();
        
        $conn->commit();
        $_SESSION['success_message'] = "Child record deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting child record: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['accept_child'])) {
    $child_id = intval($_POST['child_id']);
    $stmt = $conn->prepare("
        UPDATE children c 
        JOIN users u ON c.user_id = u.id 
        SET c.status = 'approved' 
        WHERE c.id = ? AND u.facility_id = ?
    ");
    $stmt->bind_param("ii", $child_id, $facility_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Child registration approved successfully";
    } else {
        $_SESSION['error_message'] = "Error approving child registration";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['reject_child'])) {
    $child_id = intval($_POST['child_id']);
    $reason = $_POST['rejection_reason'] ?? '';
    
    if (empty($reason)) {
        $_SESSION['error_message'] = "Rejection reason is required";
    } else {
        $stmt = $conn->prepare("
            UPDATE children c 
            JOIN users u ON c.user_id = u.id 
            SET c.status = 'rejected', c.rejection_reason = ? 
            WHERE c.id = ? AND u.facility_id = ?
        ");
        $stmt->bind_param("sii", $reason, $child_id, $facility_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Child registration rejected successfully";
        } else {
            $_SESSION['error_message'] = "Error rejecting child registration";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
}
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    $conn->begin_transaction();
    try {
        // Delete reservations associated with the user
        $delete_reservations = $conn->prepare("DELETE FROM reservations WHERE user_id = ?");
        $delete_reservations->bind_param("i", $user_id);
        $delete_reservations->execute();
        
        // Delete user sports
        $delete_sports = $conn->prepare("DELETE FROM user_sports WHERE user_id = ? AND user_id IN (SELECT id FROM users WHERE facility_id = ?)");
        $delete_sports->bind_param("ii", $user_id, $facility_id);
        $delete_sports->execute();
        
        // Delete user
        $delete_user = $conn->prepare("DELETE FROM users WHERE id = ? AND facility_id = ?");
        $delete_user->bind_param("ii", $user_id, $facility_id);
        $delete_user->execute();
        
        $conn->commit();
        $success_message = "User deleted successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error deleting user: " . $e->getMessage();
    }
}
// Handle reservation actions
if (isset($_POST['accept_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("UPDATE reservations SET status = 'approved' WHERE id = ? AND facility_id = ?");
    $stmt->bind_param("ii", $reservation_id, $facility_id);
    $stmt->execute();
    $success_message = "Reservation approved successfully";
}

if (isset($_POST['reject_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $reason = $conn->real_escape_string($_POST['rejection_reason']);
    $stmt = $conn->prepare("UPDATE reservations SET status = 'rejected', rejection_reason = ? WHERE id = ? AND facility_id = ?");
    $stmt->bind_param("sii", $reason, $reservation_id, $facility_id);
    $stmt->execute();
    $success_message = "Reservation rejected successfully";
}
if (isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ? AND facility_id = ?");
    $stmt->bind_param("ii", $reservation_id, $facility_id);
    if ($stmt->execute()) {
        $success_message = "Reservation deleted successfully";
    } else {
        $error_message = "Error deleting reservation";
    }
}

// Fetch users with details
$users_query = "
    SELECT 
        u.*,
        w.name as wilaya_name,
        GROUP_CONCAT(DISTINCT s.name) as sports,
        GROUP_CONCAT(DISTINCT s.id) as sport_ids
    FROM users u
    LEFT JOIN wilaya w ON u.wilaya_id = w.id
    LEFT JOIN user_sports us ON u.id = us.user_id
    LEFT JOIN sport s ON us.sport_id = s.id
    WHERE u.is_deleted = 0 
    AND u.facility_id = ?
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$stmt = $conn->prepare($users_query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch reservations with user details
$reservations_query = "
    SELECT 
        r.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        u.dob,
        u.pob,
        u.gender,
        u.id_type,
        u.id_number,
        u.id_document_path,
        s.name as sport_name  -- Added sport name
    FROM reservations r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN sport s ON r.sport_id = s.id  -- Added join with sport table
    WHERE r.facility_id = ?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($reservations_query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get combined statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE facility_id = ? AND is_deleted = 0) as total_users,
        (SELECT COUNT(*) FROM users WHERE facility_id = ? AND status = 'pending' AND is_deleted = 0) as pending_users,
        (SELECT COUNT(*) FROM users WHERE facility_id = ? AND status = 'approved' AND is_deleted = 0) as approved_users,
        (SELECT COUNT(*) FROM users WHERE facility_id = ? AND status = 'rejected' AND is_deleted = 0) as rejected_users,
        (SELECT COUNT(*) FROM reservations WHERE facility_id = ?) as total_reservations,
        (SELECT COUNT(*) FROM reservations WHERE facility_id = ? AND status = 'pending') as pending_reservations,
        (SELECT COUNT(*) FROM reservations WHERE facility_id = ? AND status = 'approved') as approved_reservations,
        (SELECT COUNT(*) FROM reservations WHERE facility_id = ? AND status = 'rejected') as rejected_reservations
";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiiiiii", $facility_id, $facility_id, $facility_id, $facility_id, $facility_id, $facility_id, $facility_id, $facility_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$children_stats_query = "
    SELECT 
        COUNT(*) as total_children,
        SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_children,
        SUM(CASE WHEN c.status = 'approved' THEN 1 ELSE 0 END) as approved_children,
        SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected_children
    FROM children c
    JOIN users u ON c.user_id = u.id
    WHERE u.facility_id = ?
";
$stmt = $conn->prepare($children_stats_query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$children_stats = $stmt->get_result()->fetch_assoc();

// Fetch children with parent details
$children_query = "
    SELECT 
        c.*,
        u.first_name as parent_first_name,
        u.last_name as parent_last_name,
        u.email as parent_email,
        GROUP_CONCAT(DISTINCT s.name) as sports
    FROM children c
    JOIN users u ON c.user_id = u.id
    LEFT JOIN child_sports cs ON c.id = cs.child_id
    LEFT JOIN sport s ON cs.sport_id = s.id
    WHERE u.facility_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
     <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #d97706;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .stat-card .number {
            font-size: 1.875rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .users-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .users-table th,
        .users-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .users-table th {
            background-color: #f9fafb;
            font-weight: 600;
        }

        .btn {
            padding: 8px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
        }

        .user-details-scroll {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 15px;
}

.user-details {
    padding: 20px;
}

.user-details h3 {
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 10px;
    margin: 20px 0 15px;
    color: var(--secondary-color);
}

.user-details dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.user-details dt {
    font-weight: bold;
    color: #4b5563;
}

.user-details dd {
    margin-left: 0;
    color: #1f2937;
}

.id-document-container {
    display: flex;
    justify-content: center;
    margin-top: 20px;
}

.id-document-preview {
    max-width: 100%;
    max-height: 400px;
    object-fit: contain;
}

        .badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .message-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }
        .section-header {
            background-color: #f8fafc;
            padding: 1rem;
            margin: 2rem 0 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--secondary-color);
            margin: 0;
        }

        .stats-container {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stats-section {
            flex: 1;
        }
        .children-table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.children-table th,
.children-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.children-table th {
    background-color: #f5f5f5;
    font-weight: 600;
}

.sport-tag {
    display: inline-block;
    padding: 4px 8px;
    margin: 2px;
    background: #e2e8f0;
    border-radius: 4px;
    font-size: 0.85em;
}
.sport-badge {
    background-color: #e9ecef;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.9em;
    color: #495057;
}

.time-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.9em;
}

.time-details i {
    width: 16px;
    margin-right: 4px;
    color: #6c757d;
}
     </style>
</head>
<body>
<div class="container">
    <div class="mb-3">
        <a href="facility-management.php" class="btn btn-secondary back-button">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="message message-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message message-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-container">
            <div class="stats-section">
                <h2>User Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <div class="number"><?php echo $stats['total_users']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Users</h3>
                        <div class="number"><?php echo $stats['pending_users']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Approved Users</h3>
                        <div class="number"><?php echo $stats['approved_users']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Rejected Users</h3>
                        <div class="number"><?php echo $stats['rejected_users']; ?></div>
                    </div>
                </div>
            </div>
            
            <div class="stats-section">
                <h2>Reservation Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Reservations</h3>
                        <div class="number"><?php echo $stats['total_reservations']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Reservations</h3>
                        <div class="number"><?php echo $stats['pending_reservations']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Approved Reservations</h3>
                        <div class="number"><?php echo $stats['approved_reservations']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Rejected Reservations</h3>
                        <div class="number"><?php echo $stats['rejected_reservations']; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="stats-section">
    <h2>Children Statistics</h2>
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Children</h3>
            <div class="number"><?php echo $children_stats['total_children']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Pending Children</h3>
            <div class="number"><?php echo $children_stats['pending_children']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Approved Children</h3>
            <div class="number"><?php echo $children_stats['approved_children']; ?></div>
        </div>
        <div class="stat-card">
            <h3>Rejected Children</h3>
            <div class="number"><?php echo $children_stats['rejected_children']; ?></div>
        </div>
    </div>
</div>

        <div class="section-header">
            <h2>User Management</h2>
        </div>

        <div class="filters">
            <select class="filter-select" id="statusFilter">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Registration Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr data-status="<?php echo htmlspecialchars($user['status']); ?>">
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo htmlspecialchars($user['status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button onclick="showUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn btn-warning">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($user['status'] === 'pending'): ?>
                                <button onclick="acceptUser(<?php echo $user['id']; ?>)" class="btn btn-success">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button onclick="rejectUser(<?php echo $user['id']; ?>)" class="btn btn-danger">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                            <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="section-header">
    <h2>Children Management</h2>
</div>

<div class="filters">
    <select class="filter-select" id="childrenStatusFilter">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
    </select>
</div>

<table class="children-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Child Name</th>
            <th>Parent</th>
            <th>Age</th>
            <th>Gender</th>
            <th>Sports</th>
            <th>Status</th>
            <th>Registration Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($children as $child): ?>
            <tr data-status="<?php echo htmlspecialchars($child['status']); ?>">
                <td><?php echo htmlspecialchars($child['id']); ?></td>
                <td><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></td>
                <td>
                    <?php echo htmlspecialchars($child['parent_first_name'] . ' ' . $child['parent_last_name']); ?><br>
                    <small><?php echo htmlspecialchars($child['parent_email']); ?></small>
                </td>
                <td>
                    <?php 
                    $dob = new DateTime($child['dob']);
                    echo date_diff($dob, date_create('today'))->y; 
                    ?> years
                </td>
                <td><?php echo htmlspecialchars($child['gender']); ?></td>
                <td>
                    <?php if (!empty($child['sports'])): ?>
                        <?php foreach (explode(',', $child['sports']) as $sport): ?>
                            <span class="sport-tag"><?php echo htmlspecialchars($sport); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-<?php echo htmlspecialchars($child['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($child['status'])); ?>
                    </span>
                </td>
                <td><?php echo date('Y-m-d H:i', strtotime($child['created_at'])); ?></td>
                <td>
            <button onclick="showChildDetails(<?php echo htmlspecialchars(json_encode($child)); ?>)" 
            class="btn btn-warning">
          <i class="fas fa-eye"></i>
         </button>
       <?php if ($child['status'] === 'pending'): ?>
           <button onclick="acceptChild(<?php echo $child['id']; ?>)" 
                class="btn btn-success">
              <i class="fas fa-check"></i>
             </button>
            <button onclick="rejectChild(<?php echo $child['id']; ?>)" 
                class="btn btn-danger">
               <i class="fas fa-times"></i>
               </button>
             <?php endif; ?>
           <button onclick="deleteChild(<?php echo $child['id']; ?>)" 
              class="btn btn-danger">
             <i class="fas fa-trash"></i>
              </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Add Child Details Modal -->
<div id="childDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Child Details</h2>
        <div class="child-details">
            <h3>Personal Information</h3>
            <dl>
                <dt>Full Name:</dt>
                <dd id="childFullName"></dd>

                <dt>Date of Birth:</dt>
                <dd id="childDob"></dd>

                <dt>Gender:</dt>
                <dd id="childGender"></dd>

                <dt>Medical Notes:</dt>
                <dd id="childMedicalNotes"></dd>
            </dl>

            <h3>Parent Information</h3>
            <dl>
                <dt>Parent Name:</dt>
                <dd id="childParentName"></dd>

                <dt>Parent Email:</dt>
                <dd id="childParentEmail"></dd>
            </dl>

            <h3>Sports</h3>
            <div id="childSports" class="sports-tags"></div>
        </div>
    </div>
</div>

        <div class="section-header">
            <h2>Reservation Management</h2>
        </div>

        <div class="filters">
            <select class="filter-select" id="reservationStatusFilter">
                <option value="">All Reservation Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>

        <table class="users-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Check-in</th>
            <th>Check-out</th>
            <th>Sport</th>  <!-- Added sport column -->
            <th>Times</th>  <!-- Added times column -->
            <th>Duration</th>
            <th>Status</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
            <tr data-status="<?php echo htmlspecialchars($reservation['status']); ?>">
                <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                <td>
                    <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?><br>
                    <small><?php echo htmlspecialchars($reservation['email']); ?></small>
                </td>
                <td>
                    <?php echo date('Y-m-d', strtotime($reservation['check_in_date'])); ?>
                </td>
                <td><?php echo date('Y-m-d', strtotime($reservation['check_out_date'])); ?></td>
                <td>
                    <span class="sport-badge">
                        <?php echo htmlspecialchars($reservation['sport_name'] ?? 'N/A'); ?>
                    </span>
                </td>
                <td>
                    <div class="time-details">
                        <div>
                            <i class="fas fa-sign-in-alt"></i> 
                            <?php echo date('H:i', strtotime($reservation['check_in_time'])); ?>
                        </div>
                        <div>
                            <i class="fas fa-sign-out-alt"></i> 
                            <?php echo date('H:i', strtotime($reservation['check_out_time'])); ?>
                        </div>
                    </div>
                </td>
                <td>
                    <?php 
                    $interval = date_diff(
                        date_create($reservation['check_in_date']),
                        date_create($reservation['check_out_date'])
                    );
                    echo $interval->days . ' days';
                    ?>
                </td>
                <td>
                    <span class="badge badge-<?php echo htmlspecialchars($reservation['status']); ?>">
                        <?php echo ucfirst(htmlspecialchars($reservation['status'])); ?>
                    </span>
                </td>
                <td><?php echo date('Y-m-d H:i', strtotime($reservation['created_at'])); ?></td>
                <td>
                    <button onclick="showReservationDetails(<?php echo htmlspecialchars(json_encode($reservation)); ?>)" 
                            class="btn btn-warning">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($reservation['status'] === 'pending'): ?>
                        <button onclick="acceptReservation(<?php echo $reservation['id']; ?>)" 
                                class="btn btn-success">
                            <i class="fas fa-check"></i>
                        </button>
                        <button onclick="rejectReservation(<?php echo $reservation['id']; ?>)" 
                                class="btn btn-danger">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                    <button onclick="deleteReservation(<?php echo $reservation['id']; ?>)" 
                            class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        <!-- User Details Modal -->
<div id="userDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>User Details</h2>
        <div class="user-details-scroll">
            <div class="user-details">
                <h3>Personal Information</h3>
                <dl>
                    <dt>Full Name:</dt>
                    <dd id="userFullName"></dd>

                    <dt>Email:</dt>
                    <dd id="userEmail"></dd>

                    <dt>Phone:</dt>
                    <dd id="userPhone"></dd>

                    <dt>Date of Birth:</dt>
                    <dd id="userDob"></dd>

                    <dt>Place of Birth:</dt>
                    <dd id="userPob"></dd>

                    <dt>Gender:</dt>
                    <dd id="userGender"></dd>
                </dl>

                <h3>Address & Location</h3>
                <dl>
                    <dt>Wilaya:</dt>
                    <dd id="userWilaya"></dd>
                </dl>

                <h3>Sports Profile</h3>
                <dl>
                    <dt>Sports:</dt>
                    <dd id="userSports"></dd>
                </dl>

                <h3>Identification</h3>
                <dl>
                    <dt>ID Type:</dt>
                    <dd id="userIdType"></dd>

                    <dt>ID Number:</dt>
                    <dd id="userIdNumber"></dd>
                </dl>

                <h3>ID Document</h3>
                <div class="id-document-container">
                    <img id="userIdDocument" src="" alt="ID Document" class="id-document-preview">
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Reservation Details Modal -->
        <div id="reservationDetailsModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Reservation Details</h2>
        <div class="reservation-details">
            <div class="detail-row">
                <label>User:</label>
                <span id="reservationUser"></span>
            </div>
            <div class="detail-row">
                <label>Sport:</label>
                <span id="reservationSport"></span>
            </div>
            <div class="detail-row">
                <label>Check-in:</label>
                <span id="reservationCheckIn"></span>
            </div>
            <div class="detail-row">
                <label>Check-out:</label>
                <span id="reservationCheckOut"></span>
            </div>
            <div class="detail-row">
                <label>Arrival Time:</label>
                <span id="reservationArrivalTime"></span>
            </div>
            <div class="detail-row">
                <label>Departure Time:</label>
                <span id="reservationDepartureTime"></span>
            </div>
            <div class="detail-row">
                <label>Duration:</label>
                <span id="reservationDuration"></span>
            </div>
            <div class="detail-row">
                <label>Status:</label>
                <span id="reservationStatus"></span>
            </div>
            <div class="detail-row">
                <label>Created At:</label>
                <span id="reservationCreatedAt"></span>
            </div>
        </div>
    </div>
</div>

        <!-- Rejection Reason Modal -->
        <div id="rejectionModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Provide Rejection Reason</h2>
                <form id="rejectionForm">
                    <input type="hidden" id="rejectionItemId" name="item_id">
                    <input type="hidden" id="rejectionType" name="type">
                    <textarea id="rejectionReason" name="rejection_reason" required></textarea>
                    <button type="submit" class="btn btn-danger">Submit</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
document.getElementById('statusFilter').addEventListener('change', function() {
    filterTable('users-table', this.value);
});

document.getElementById('reservationStatusFilter').addEventListener('change', function() {
    filterTable('reservations-table', this.value);
});

function filterTable(tableClass, status) {
    const rows = document.querySelectorAll('.' + tableClass + ' tbody tr');
    rows.forEach(row => {
        if (!status || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Modal functionality
function showUserDetails(user) {
    document.getElementById('userFullName').textContent = user.first_name + ' ' + user.last_name;
    document.getElementById('userEmail').textContent = user.email;
    document.getElementById('userPhone').textContent = user.phone;
    document.getElementById('userDob').textContent = user.dob;
    document.getElementById('userPob').textContent = user.pob;
    document.getElementById('userGender').textContent = user.gender;
    document.getElementById('userWilaya').textContent = user.wilaya_name;
    document.getElementById('userSports').textContent = user.sports;
    document.getElementById('userIdType').textContent = user.id_type;
    document.getElementById('userIdNumber').textContent = user.id_number;
    document.getElementById('userIdDocument').src = user.id_document_path;
    
    document.getElementById('userDetailsModal').style.display = 'block';
}

function showReservationDetails(reservation) {
    document.getElementById('reservationUser').textContent = 
        reservation.first_name + ' ' + reservation.last_name;
    document.getElementById('reservationSport').textContent = 
        reservation.sport_name || 'N/A';
    document.getElementById('reservationCheckIn').textContent = 
        reservation.check_in_date;
    document.getElementById('reservationCheckOut').textContent = 
        reservation.check_out_date;
    document.getElementById('reservationArrivalTime').textContent = 
        new Date('1970-01-01T' + reservation.check_in_time).toLocaleTimeString([], 
            {hour: '2-digit', minute:'2-digit'});
    document.getElementById('reservationDepartureTime').textContent = 
        new Date('1970-01-01T' + reservation.check_out_time).toLocaleTimeString([], 
            {hour: '2-digit', minute:'2-digit'});
    document.getElementById('reservationStatus').textContent = reservation.status;
    document.getElementById('reservationCreatedAt').textContent = reservation.created_at;
    
    const checkIn = new Date(reservation.check_in_date);
    const checkOut = new Date(reservation.check_out_date);
    const days = Math.round((checkOut - checkIn) / (1000 * 60 * 60 * 24));
    document.getElementById('reservationDuration').textContent = days + ' days';
    
    document.getElementById('reservationDetailsModal').style.display = 'block';
}

// Close modal functionality
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
    });
});

// Window click to close modals
window.addEventListener('click', function(event) {
    document.querySelectorAll('.modal').forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

// User management functions
function acceptUser(userId) {
    if (confirm('Are you sure you want to approve this user?')) {
        submitForm('accept_user', userId);
    }
}

function rejectUser(userId) {
    document.getElementById('rejectionItemId').value = userId;
    document.getElementById('rejectionType').value = 'user';
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectionModal').style.display = 'block';
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        submitForm('delete_user', userId);
    }
}
function showChildDetails(child) {
    document.getElementById('childFullName').textContent = child.first_name + ' ' + child.last_name;
    document.getElementById('childDob').textContent = child.dob;
    document.getElementById('childGender').textContent = child.gender;
    document.getElementById('childMedicalNotes').textContent = child.medical_notes || 'None';
    document.getElementById('childParentName').textContent = child.parent_first_name + ' ' + child.parent_last_name;
    document.getElementById('childParentEmail').textContent = child.parent_email;
    
    const sportsContainer = document.getElementById('childSports');
    sportsContainer.innerHTML = '';
    if (child.sports) {
        child.sports.split(',').forEach(sport => {
            const sportTag = document.createElement('span');
            sportTag.className = 'sport-tag';
            sportTag.textContent = sport;
            sportsContainer.appendChild(sportTag);
        });
    }
    
    document.getElementById('childDetailsModal').style.display = 'block';
}

function acceptChild(childId) {
    if (confirm('Are you sure you want to approve this child?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'accept_child';
        actionInput.value = '1';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'child_id';
        idInput.value = childId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectChild(childId) {
    document.getElementById('rejectionItemId').value = childId;
    document.getElementById('rejectionType').value = 'child';
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectionModal').style.display = 'block';
}

function deleteChild(childId) {
    if (confirm('Are you sure you want to delete this child? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'delete_child';
        actionInput.value = '1';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'child_id';
        idInput.value = childId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Add child status filter
document.getElementById('childrenStatusFilter').addEventListener('change', function() {
    filterTable('children-table', this.value);
});

// Reservation management functions
function acceptReservation(reservationId) {
    if (confirm('Are you sure you want to approve this reservation?')) {
        submitForm('accept_reservation', reservationId);
    }
}

function rejectReservation(reservationId) {
    document.getElementById('rejectionItemId').value = reservationId;
    document.getElementById('rejectionType').value = 'reservation';
    document.getElementById('rejectionReason').value = '';
    document.getElementById('rejectionModal').style.display = 'block';
}

function deleteReservation(reservationId) {
    if (confirm('Are you sure you want to delete this reservation? This action cannot be undone.')) {
        submitForm('delete_reservation', reservationId);
    }
}

// Update the rejection form handler
document.getElementById('rejectionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const itemId = document.getElementById('rejectionItemId').value;
    const type = document.getElementById('rejectionType').value;
    const reason = document.getElementById('rejectionReason').value;
    
    if (!reason.trim()) {
        alert('Please provide a rejection reason');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = type === 'child' ? 'reject_child' : 
                      type === 'user' ? 'reject_user' : 'reject_reservation';
    actionInput.value = '1';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = type === 'child' ? 'child_id' : 
                   type === 'user' ? 'user_id' : 'reservation_id';
    idInput.value = itemId;
    form.appendChild(idInput);
    
    const reasonInput = document.createElement('input');
    reasonInput.type = 'hidden';
    reasonInput.name = 'rejection_reason';
    reasonInput.value = reason;
    form.appendChild(reasonInput);
    
    document.body.appendChild(form);
    form.submit();
    
    document.getElementById('rejectionModal').style.display = 'none';
});

// Form submission helper
function submitForm(action, id, reason = null) {
    const form = document.createElement('form');
    form.method = 'POST';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = action;
    actionInput.value = '1';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = action.includes('user') ? 'user_id' : 'reservation_id';
    idInput.value = id;
    form.appendChild(idInput);
    
    if (reason) {
        const reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'rejection_reason';
        reasonInput.value = reason;
        form.appendChild(reasonInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>