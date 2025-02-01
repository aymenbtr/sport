<?php
session_start();

// Strong password generation
$admin_username = 'adminisaymen';
$admin_password = 'X9#kL2$pQ7zN3@mR'; // A strong, randomly generated password

// Login logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = "Invalid credentials. Please try again.";
    }
}

// Logout logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
}

// Check if user is logged in
$is_logged_in = $_SESSION['admin_logged_in'] ?? false;

// Rest of the previous PHP code remains the same, but wrapped with login check
if (!$is_logged_in) {
    // Login page code remains here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            width: 350px;
        }
        .login-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .login-form input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .login-form button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-form button:hover {
            opacity: 0.9;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><i class="fas fa-lock"></i> Admin Login</h2>
        <?php if (isset($login_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <form class="login-form" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
<?php 
    exit(); 
}
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch facilities data - show all non-deleted facilities
$facilities_query = "
    SELECT 
        f.*,
        w.name as wilaya_name,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name ASC SEPARATOR ', ') as sports,
        GROUP_CONCAT(DISTINCT fi.image_path ORDER BY fi.id ASC SEPARATOR '|||') as images
    FROM facilities f
    LEFT JOIN wilaya w ON f.wilaya_id = w.id
    LEFT JOIN facility_sports fs ON f.id = fs.facility_id
    LEFT JOIN sport s ON fs.sport_id = s.id
    LEFT JOIN facility_image fi ON f.id = fi.facility_id
    WHERE f.is_deleted = 0
    GROUP BY f.id
    ORDER BY f.created_at DESC
";

$facilities_result = $conn->query($facilities_query);

if (!$facilities_result) {
    die("Query failed: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $facility_id = filter_input(INPUT_POST, 'facility_id', FILTER_SANITIZE_NUMBER_INT);
    $action = $_POST['action'];
    
    if ($action === 'delete') {
        try {
            $conn->begin_transaction();
            $delete_query = "DELETE FROM facilities WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $facility_id);
            
            if ($stmt->execute()) {
                $delete_sports = "DELETE FROM facility_sports WHERE facility_id = ?";
                $stmt_sports = $conn->prepare($delete_sports);
                $stmt_sports->bind_param("i", $facility_id);
                $stmt_sports->execute();

                $delete_images = "DELETE FROM facility_image WHERE facility_id = ?";
                $stmt_images = $conn->prepare($delete_images);
                $stmt_images->bind_param("i", $facility_id);
                $stmt_images->execute();

                $conn->commit();
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Error deleting facility");
            }
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } else {
        try {
            $admin_message = filter_input(INPUT_POST, 'admin_message', FILTER_SANITIZE_STRING);
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            
            $update_query = "UPDATE facilities SET status = ?, admin_message = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssi", $status, $admin_message, $facility_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Error updating facility status");
            }
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel - Facility Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container {
            max-width: 900px;
            margin: auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .facility-details {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .gallery-image-container {
            position: relative;
            padding-top: 75%; /* 4:3 Aspect Ratio */
            overflow: hidden;
            border-radius: 8px;
            cursor: pointer;
        }

        .gallery-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gallery-image:hover {
            transform: scale(1.05);
        }
        .social-button {
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .admin-button {
            margin-right: 10px;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .approve-button {
            background-color: #4CAF50;
            color: white;
        }
        .reject-button {
            background-color: #f44336;
            color: white;
        }
        .delete-button {
            background-color: #555;
            color: white;
        }
        .admin-message textarea {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #777;
        }
    </style>
</head>
<body>
<a href="?action=logout" class="logout-button">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
    <div class="container">
        <h1>Admin Control Panel - Facility Management</h1>

        <?php if ($facilities_result->num_rows > 0): ?>
            <?php while ($facility = $facilities_result->fetch_assoc()): ?>
                <div class="facility-details">
                    <div class="image-gallery">
                        <?php 
                        if ($facility['images']) {
                            $images = explode('|||', $facility['images']);
                            foreach ($images as $image): 
                                if (!empty($image)):
                                    $image_path = str_replace('\\', '/', $image);
                                    $image_path = str_replace('//', '/', $image_path);
                                    if (strpos($image_path, 'uploads/') !== 0) {
                                        $image_path = 'uploads/' . ltrim($image_path, '/');
                                    }
                        ?>
                            <div class="gallery-image-container">
                                <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                     alt="Facility Image"
                                     class="gallery-image"
                                     onerror="this.parentElement.style.display='none'"
                                     onclick="showImageModal(this.src)">
                            </div>
                        <?php 
                                endif;
                            endforeach; 
                        }
                        ?>
                    </div>

                    <h2><?php echo htmlspecialchars($facility['company_name']); ?></h2>
                    <div class="social-links">
                        <?php if (!empty($facility['facebook_url'])): ?>
                            <a href="<?php echo htmlspecialchars($facility['facebook_url']); ?>" target="_blank" class="social-button facebook">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($facility['instagram_url'])): ?>
                            <a href="<?php echo htmlspecialchars($facility['instagram_url']); ?>" target="_blank" class="social-button instagram">
                                <i class="fab fa-instagram"></i> Instagram
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($facility['twitter_url'])): ?>
                            <a href="<?php echo htmlspecialchars($facility['twitter_url']); ?>" target="_blank" class="social-button twitter">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($facility['website_url'])): ?>
                            <a href="<?php echo htmlspecialchars($facility['website_url']); ?>" target="_blank" class="social-button website">
                                <i class="fas fa-globe"></i> Website
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <p><strong>Sports:</strong> <?php echo htmlspecialchars($facility['sports']); ?></p>
                    <p><strong>Wilaya:</strong> <?php echo htmlspecialchars($facility['wilaya_name']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($facility['address']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($facility['phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($facility['email']); ?></p>
                    <p><strong>Hours:</strong> <?php echo htmlspecialchars($facility['opening_hours']); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($facility['description']); ?></p>
                    <p><strong>Registration Date:</strong> <?php echo date('F j, Y', strtotime($facility['created_at'])); ?></p>
                    
                    <div class="admin-message">
                        <textarea placeholder="Add a message for the facility manager" rows="3"></textarea>
                    </div>

                    <div class="admin-actions">
                        <?php if ($facility['status'] !== 'approved'): ?>
                            <button class="admin-button approve-button" 
                                    onclick="handleAction(<?php echo $facility['id']; ?>, 'approve')">
                                Approve
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($facility['status'] !== 'rejected'): ?>
                            <button class="admin-button reject-button" 
                                    onclick="handleAction(<?php echo $facility['id']; ?>, 'reject')">
                                Reject
                            </button>
                        <?php endif; ?>
                        
                        <button class="admin-button delete-button" 
                                onclick="handleAction(<?php echo $facility['id']; ?>, 'delete')">
                            Delete
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">No facility registrations found.</div>
        <?php endif; ?>
    </div>

    <div id="image-modal" style="display:none; position:fixed; z-index:1000; padding-top:50px; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.9);">
        <span style="position:absolute; right:35px; top:15px; color:#f1f1f1; font-size:40px; font-weight:bold; cursor:pointer;" onclick="closeImageModal()">&times;</span>
        <img id="modal-img" style="margin:auto; display:block; max-width:90%; max-height:90vh;">
    </div>

    <script>
        function handleAction(facilityId, action) {
            const adminMessage = document.querySelector('textarea').value;
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'facility_id': facilityId,
                    'action': action,
                    'admin_message': adminMessage,
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        function showImageModal(src) {
            const modal = document.getElementById('image-modal');
            const modalImg = document.getElementById('modal-img');
            modal.style.display = "block";
            modalImg.src = src;
        }

        function closeImageModal() {
            document.getElementById('image-modal').style.display = "none";
        }

        window.onclick = function(event) {
            const modal = document.getElementById('image-modal');
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>