<?php
// Start separate session for users
session_name('user_session');
session_start();

// Constants and includes
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'test');
define('UPLOAD_DIR', 'uploads/id_documents/');
define('MAX_FILE_SIZE', 5000000); // 5MB
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

// Initialize variables
$errors = [];
$login_error = null;
$register_success = false;

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current facility ID
$current_facility_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user is logged in and has access to current facility
$has_facility_access = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT u.id, u.is_deleted, u.facility_id 
        FROM users u
        WHERE u.id = ? AND u.facility_id = ?
    ");
    $stmt->bind_param("ii", $_SESSION['user_id'], $current_facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['is_deleted']) {
            // Account deleted, destroy user session
            session_destroy();
            header("Location: index.php?error=account_deleted");
            exit();
        }
        $has_facility_access = true;
    }
    $stmt->close();
}

// Handle Logout
if (isset($_GET['logout'])) {
    // Destroy the current session
    session_destroy();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Redirect to the facility page
    header("Location: facility-info.php?id=" . $current_facility_id);
    exit();
}

// Check if user account has been deleted
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT is_deleted FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['is_deleted']) {
            // Account deleted, destroy user session and redirect
            session_destroy();
            header("Location: index.php?error=account_deleted");
            exit();
        }
    }
    $stmt->close();
}

// Handle form submission and prevent resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login']) || isset($_POST['register'])) {
        $_SESSION['form_submitted'] = true;
        $_SESSION['post_data'] = $_POST;
        
        // Handle file upload for registration
        if (isset($_FILES['id_document'])) {
            $tmpName = $_FILES['id_document']['tmp_name'];
            $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
            if (move_uploaded_file($tmpName, $tmpPath)) {
                $_SESSION['temp_file'] = [
                    'path' => $tmpPath,
                    'name' => $_FILES['id_document']['name'],
                    'type' => $_FILES['id_document']['type'],
                    'size' => $_FILES['id_document']['size']
                ];
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $_GET['id']);
        exit();
    }
}

// Handle Login
function handleLogin($conn, $email, $password, $facility_id) {
    $stmt = $conn->prepare("
        SELECT u.id, u.password, u.first_name, u.last_name, u.status, 
               u.is_deleted, u.rejection_reason, u.facility_id 
        FROM users u 
        WHERE u.email = ? AND u.facility_id = ?
    ");
    $stmt->bind_param("si", $email, $facility_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if ($user['is_deleted']) {
            return 'deleted';
        }
        if ($user['status'] === 'pending') {
            return 'pending';
        }
        if ($user['status'] === 'rejected') {
            return 'rejected:' . ($user['rejection_reason'] ?? 'No reason provided');
        }
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['facility_id'] = $user['facility_id'];
            return 'success';
        }
    }
    return 'invalid';
}

// Handle Registration
function handleRegistration($conn, $data) {
    global $errors;
    
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'dob', 'pob', 'address', 'email', 
                          'phone', 'gender', 'id_type', 'id_number', 'password'];
        
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Please fill in all required fields. Missing: " . $field);
            }
        }

        // Get facility_id from URL parameter
        $facility_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Check if facility exists
        $check_facility = $conn->prepare("SELECT id FROM facilities WHERE id = ? AND status = 'approved' AND is_deleted = 0");
        $check_facility->bind_param("i", $facility_id);
        $check_facility->execute();
        if ($check_facility->get_result()->num_rows === 0) {
            throw new Exception("Invalid or inactive facility.");
        }

        // Get wilaya_id and wilaya_name from facility
        $stmt = $conn->prepare("
            SELECT f.wilaya_id, w.name as wilaya_name 
            FROM facilities f 
            JOIN wilaya w ON f.wilaya_id = w.id 
            WHERE f.id = ?
        ");
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Invalid facility.");
        }
        $facility = $result->fetch_assoc();
        $wilaya_id = $facility['wilaya_id'];
        $stmt->close();

        // Validate date format and age
        $dob = date('Y-m-d', strtotime($data['dob']));
        $age = date_diff(date_create($dob), date_create('today'))->y;
        if ($age < 18) {
            throw new Exception("You must be 18 or older to register.");
        }

        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already registered.");
        }
        $stmt->close();

        // Handle file upload from temporary storage
        $file_path = null;
        if (isset($_SESSION['temp_file'])) {
            $temp_file = $_SESSION['temp_file'];
            
            if ($temp_file['size'] > MAX_FILE_SIZE) {
                throw new Exception("File size too large. Maximum size is 5MB.");
            }
            
            if (!in_array($temp_file['type'], ALLOWED_FILE_TYPES)) {
                throw new Exception("Invalid file type. Please upload JPG, PNG or PDF.");
            }

            $file_extension = pathinfo($temp_file['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = UPLOAD_DIR . $file_name;
            
            if (!copy($temp_file['path'], $file_path)) {
                throw new Exception("Failed to save ID document.");
            }
            
            unlink($temp_file['path']);
            unset($_SESSION['temp_file']);
        } else {
            throw new Exception("ID document is required.");
        }

        $conn->begin_transaction();
        
        // Insert user
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, dob, pob, address, email, phone, gender, id_type, id_number, id_document_path, password, wilaya_id, facility_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssssssssssssss", 
            $data['first_name'],
            $data['last_name'],
            $dob,
            $data['pob'],
            $data['address'],
            $data['email'],
            $data['phone'],
            $data['gender'],
            $data['id_type'],
            $data['id_number'],
            $file_path,
            $hashed_password,
            $wilaya_id,
            $facility_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert user data: " . $stmt->error);
        }
        
        $user_id = $conn->insert_id;

        // Insert user sports if selected
        if (isset($data['sports']) && is_array($data['sports'])) {
            $sport_stmt = $conn->prepare("INSERT INTO user_sports (user_id, sport_id) VALUES (?, ?)");
            foreach ($data['sports'] as $sport_id) {
                $sport_stmt->bind_param("ii", $user_id, $sport_id);
                if (!$sport_stmt->execute()) {
                    throw new Exception("Failed to insert user sports.");
                }
            }
            $sport_stmt->close();
        }

        $conn->commit();
        return $user_id;

    } catch (Exception $e) {
        $conn->rollback();
        if (isset($file_path) && file_exists($file_path)) {
            unlink($file_path);
        }
        $errors[] = $e->getMessage();
        return false;
    }
}
function shouldShowAuthButtons($has_facility_access) {
    $current_facility_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!isset($_SESSION['user_id'])) {
        return true; // Show login/register buttons
    }
    if (isset($_SESSION['facility_id']) && $_SESSION['facility_id'] === $current_facility_id) {
        return false; // Show user profile menu
    }
    return true; // Show login/register buttons for other facilities
}

// Process Login
if (isset($_SESSION['form_submitted']) && isset($_SESSION['post_data']['login'])) {
    $email = filter_var($_SESSION['post_data']['email'], FILTER_SANITIZE_EMAIL);
    $facility_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $login_result = handleLogin($conn, $email, $_SESSION['post_data']['password'], $facility_id);
    
    switch ($login_result) {
        case 'wrong_facility':
            $login_error = "You are not registered with this facility.";
            break;
        case 'pending':
            $login_error = "Your registration is pending approval.";
            break;
        case 'deleted':
            $login_error = "This account has been deleted.";
            break;
        case 'invalid':
            $login_error = "Invalid credentials.";
            break;
        default:
            if (strpos($login_result, 'rejected:') === 0) {
                $rejection_reason = substr($login_result, 9);
                $login_error = "Your registration was rejected. Reason: " . htmlspecialchars($rejection_reason);
            }
            break;
    }
}

// Process Registration
if (isset($_SESSION['form_submitted']) && isset($_SESSION['post_data']['register'])) {
    $user_id = handleRegistration($conn, $_SESSION['post_data']);
    if ($user_id) {
        $register_success = "Your demand is under review.";
    }
}

if (isset($register_success)) {
    echo "<div class='form-success'>" . $register_success . "</div>";
}
if (isset($login_error)) {
    echo "<div class='form-error'>" . $login_error . "</div>";
}
// Clean up session data after processing
if (isset($_SESSION['form_submitted'])) {
    unset($_SESSION['form_submitted'], $_SESSION['post_data']);
    if (isset($_SESSION['temp_file']) && file_exists($_SESSION['temp_file']['path'])) {
        unlink($_SESSION['temp_file']['path']);
        unset($_SESSION['temp_file']);
    }
}

// Fetch facility details
$facility_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$query = "
    SELECT f.*, w.name as wilaya_name, 
           GROUP_CONCAT(DISTINCT s.name) as sports,
           GROUP_CONCAT(DISTINCT s.id) as sport_ids,
           GROUP_CONCAT(DISTINCT fi.image_path) as images
    FROM facilities f
    LEFT JOIN wilaya w ON f.wilaya_id = w.id
    LEFT JOIN facility_sports fs ON f.id = fs.facility_id
    LEFT JOIN sport s ON fs.sport_id = s.id
    LEFT JOIN facility_image fi ON f.id = fi.facility_id
    WHERE f.id = ? AND f.status = 'approved' AND f.is_deleted = 0
    GROUP BY f.id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();

if (!$facility) {
    header("Location: index.php");
    exit();
}

$images = explode(',', $facility['images']);
$sports = explode(',', $facility['sports']);
$sport_ids = explode(',', $facility['sport_ids']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($facility['company_name']); ?> - Book sports facilities online">
    <title><?php echo htmlspecialchars($facility['company_name']); ?> - SportsConnect</title>
    
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
                :root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --accent-color: #28a745;
    --background-light: #f8f9fa;
    --text-dark: #212529;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
    color: var(--text-dark);
    background-color: var(--background-light);
}

/* Navigation Styling */
nav {
    background-color: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 50px;
    max-width: 1200px;
    margin: 0 auto;
}

.logo {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
}

.auth-buttons {
    display: flex;
    align-items: center;
    gap: 15px;
}

.btn {
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: 2px solid var(--primary-color);
}

.btn-outline {
    background-color: transparent;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

/* Profile Dropdown */
.profile-menu {
    position: relative;
}

.profile-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background-color: white;
    min-width: 180px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    border-radius: 8px;
    padding: 10px 0;
    z-index: 1000;
}

.profile-menu:hover .profile-dropdown {
    display: block;
}

.profile-dropdown a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: var(--text-dark);
    text-decoration: none;
    transition: background-color 0.3s;
}

.profile-dropdown a:hover {
    background-color: var(--background-light);
}

/* Modal Styling */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    padding: 30px;
    border-radius: 15px;
    max-width: 500px;
    box-shadow: 0 5px 25px rgba(0,0,0,0.2);
}

.close {
    color: var(--secondary-color);
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--primary-color);
}

.form-title {
    text-align: center;
    margin-bottom: 25px;
    color: var(--primary-color);
}

.form-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
}

.form-footer {
    text-align: center;
    margin-top: 20px;
}

.form-footer a {
    color: var(--primary-color);
    text-decoration: none;
}

.sports-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Facility Details */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px;
}

.facility-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
}

.facility-header {
    text-align: center;
    margin-bottom: 30px;
}

.facility-name {
    color: var(--primary-color);
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.facility-location {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: var(--secondary-color);
}

/* Image Gallery */
.image-gallery {
    position: relative;
    width: 100%;
    height: 600px;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.facility-image {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 0.7s ease;
}

.facility-image.active {
    opacity: 1;
}

.facility-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-title {
    position: absolute;
    bottom: 20px;
    left: 20px;
    background-color: rgba(0,0,0,0.7);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
}

.gallery-controls {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 10px;
}

.gallery-dot {
    width: 12px;
    height: 12px;
    background-color: rgba(255,255,255,0.5);
    border-radius: 50%;
    cursor: pointer;
}

.gallery-dot.active {
    background-color: white;
}

.gallery-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background-color: rgba(0,0,0,0.5);
    color: white;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
}

.gallery-arrow.prev { left: 20px; }
.gallery-arrow.next { right: 20px; }

/* Facility Information */
.facility-info {
    display: grid;
    gap: 25px;
}

.info-section {
    background-color: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}

.section-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
}

.sports-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.sport-tag {
    background-color: var(--primary-color);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.contact-info {
    display: grid;
    gap: 10px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.social-links {
    display: flex;
    gap: 20px;
}

.social-link {
    color: var(--secondary-color);
    font-size: 24px;
    transition: color 0.3s;
}

.social-link:hover {
    color: var(--primary-color);
}

.book-button {
    width: 100%;
    justify-content: center;
    margin-top: 20px;
}

/* Footer */
footer {
    background-color: #f1f3f5;
    padding: 30px 0;
    text-align: center;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
}

.footer-links {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-top: 15px;
}

.footer-links a {
    color: var(--secondary-color);
    text-decoration: none;
    transition: color 0.3s;
}

.footer-links a:hover {
    color: var(--primary-color);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .nav-container, .container {
        padding: 0 15px;
    }

    .nav-container {
        flex-direction: column;
        gap: 15px;
    }

    .facility-content {
        grid-template-columns: 1fr;
    }

    .image-gallery {
        height: 300px;
    }

    .footer-links {
        flex-direction: column;
        align-items: center;
    }
}
    </style>
</head>
<body>
    <!-- Updated Navigation -->
    <nav>
        <div class="nav-container">
            <a href="facility-user.php" class="logo">SportsConnect</a>
            <div class="auth-buttons">
    <?php if (!shouldShowAuthButtons($has_facility_access)): ?>
        <div class="profile-menu">
            <button class="btn btn-outline">
                <i class="fas fa-user"></i>
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            </button>
            <div class="profile-dropdown">
                <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="Kids.php"><i class="fas fa-baby"></i> Add your Kids Here</a>
                <a href="?id=<?php echo $current_facility_id; ?>&logout=1">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    <?php else: ?>
        <button class="btn btn-outline" onclick="openModal('loginModal')">Login</button>
        <button class="btn btn-primary" onclick="openModal('registerModal')">Register</button>
    <?php endif; ?>
</div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container">
        <div class="facility-details">
            <div class="facility-header">
                <h1 class="facility-name"><?php echo htmlspecialchars($facility['company_name']); ?></h1>
                <div class="facility-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($facility['wilaya_name'] . ' - ' . $facility['address']); ?></span>
                </div>
            </div>

            <div class="facility-content">
                <!-- Image Gallery -->
                <div class="image-gallery">
                <?php foreach ($images as $index => $image): ?>
    <?php if (!empty($image)): ?>
        <div class="facility-image <?php echo $index === 0 ? 'active' : ''; ?>">
            <img 
                src="<?php echo htmlspecialchars($image); ?>" 
                alt="<?php echo htmlspecialchars($facility['company_name']); ?>" 
                loading="lazy"
            >
            <div class="image-title">
                <?php 
                // Fetch image title from database or use a default
                $stmt = $conn->prepare("SELECT title FROM facility_image WHERE image_path = ?");
                $stmt->bind_param("s", $image);
                $stmt->execute();
                $result = $stmt->get_result();
                $imageData = $result->fetch_assoc();
                echo htmlspecialchars($imageData['title'] ?? 'Facility Image');
                ?>
              </div>
             </div>
              <?php endif; ?>
             <?php endforeach; ?>
                    <!-- Gallery Controls -->
                    <div class="gallery-controls">
                        <?php foreach ($images as $index => $image): ?>
                            <?php if (!empty($image)): ?>
                                <div class="gallery-dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-index="<?php echo $index; ?>">
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Gallery Arrows -->
                    <div class="gallery-arrow prev">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                    <div class="gallery-arrow next">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>

                <!-- Facility Information -->
                <div class="facility-info">
                    <!-- Sports Section -->
                    <div class="info-section">
                        <h2 class="section-title">Sports Available</h2>
                        <div class="sports-tags">
                            <?php foreach ($sports as $sport): ?>
                                <?php if (!empty($sport)): ?>
                                    <span class="sport-tag">
                                        <i class="fas fa-running"></i>
                                        <?php echo htmlspecialchars($sport); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Description Section -->
                    <div class="info-section">
                        <h2 class="section-title">About the Facility</h2>
                        <p><?php echo nl2br(htmlspecialchars($facility['description'])); ?></p>
                    </div>

                    <!-- Contact Information -->
                    <div class="info-section">
                        <h2 class="section-title">Contact Information</h2>
                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($facility['phone']); ?></span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($facility['email']); ?></span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-map-marked-alt"></i>
                                <span><?php echo htmlspecialchars($facility['address']); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Social Media Links -->
                    <div class="info-section">
                        <h2 class="section-title">Follow Us</h2>
                        <div class="social-links">
                            <?php if (!empty($facility['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($facility['facebook_url']); ?>" 
                                   target="_blank" 
                                   class="social-link">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($facility['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($facility['instagram_url']); ?>" 
                                   target="_blank" 
                                   class="social-link">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($facility['twitter_url'])): ?>
                                <a href="<?php echo htmlspecialchars($facility['twitter_url']); ?>" 
                                   target="_blank" 
                                   class="social-link">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($facility['website_url'])): ?>
                                <a href="<?php echo htmlspecialchars($facility['website_url']); ?>" 
                                   target="_blank" 
                                   class="social-link">
                                    <i class="fas fa-globe"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Operating Hours -->
                    <div class="info-section">
                        <h2 class="section-title">Operating Hours</h2>
                        <div class="operating-hours">
                            <?php echo htmlspecialchars($facility['opening_hours']); ?>
                        </div>
                    </div>

                    <!-- Booking Button -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="btn btn-primary book-button" 
                                onclick="window.location.href='booking.php?facility_id=<?php echo $facility_id; ?>'">
                            <i class="fas fa-calendar-plus"></i>
                            Reserve Now
                        </button>
                        <?php else: ?>
                    <button class="btn btn-primary book-button" 
                                onclick="openModal('loginModal')">
                            <i class="fas fa-sign-in-alt"></i>
                            Login to Reserve
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2 class="form-title">Login to Your Account</h2>
            <?php if (isset($login_error)): ?>
                <div class="form-error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            <form method="POST" class="login-form" onsubmit="return validateLoginForm()">
                <div class="form-group">
                    <label class="form-label" for="login_email">Email</label>
                    <input type="email" id="login_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="login_password">Password</label>
                    <input type="password" id="login_password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </button>
                <p class="form-footer">
                    Don't have an account? 
                    <a href="#" onclick="switchModal('loginModal', 'registerModal')">Register now</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('registerModal')">&times;</span>
            <h2 class="form-title">Create New Account</h2>
            <?php if (!empty($errors)): ?>
                <div class="form-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="register-form" onsubmit="return validateRegisterForm()">
                <!-- Personal Information -->
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="pob">Place of Birth</label>
                    <input type="text" id="pob" name="pob" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                   </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" required></textarea>
                </div>

                <!-- ID Document Information -->
                <div class="form-group">
                    <label class="form-label" for="id_type">ID Type</label>
                    <select id="id_type" name="id_type" class="form-control" required>
                        <option value="">Select ID Type</option>
                        <option value="national_id">National ID</option>
                        <option value="passport">Passport</option>
                        <option value="drivers_license">Driver's License</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="id_number">ID Number</label>
                    <input type="text" id="id_number" name="id_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="id_document">Upload ID Document</label>
                    <input type="file" id="id_document" name="id_document" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                    <small class="form-text">Accepted formats: JPG, PNG, PDF. Max size: 5MB</small>
                </div>

                <!-- Sports Preferences -->
                <div class="form-group">
                    <label class="form-label">Sport Interests</label>
                    <div class="sports-checkbox-group">
                        <?php foreach ($sport_ids as $index => $sport_id): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="sports[]" value="<?php echo htmlspecialchars($sport_id); ?>">
                                <?php echo htmlspecialchars($sports[$index]); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="facility_id" value="<?php echo intval($_GET['id']); ?>">
                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <!-- Terms and Conditions -->
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="terms" name="terms" required>
                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a>
                    </label>
                </div>

                <button type="submit" name="register" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Register
                </button>
                <p class="form-footer">
                    Already have an account? 
                    <a href="#" onclick="switchModal('registerModal', 'loginModal')">Login here</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> SportsConnect. All rights reserved.</p>
                <div class="footer-links"></div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function switchModal(fromModalId, toModalId) {
            closeModal(fromModalId);
            openModal(toModalId);
        }

        // Form Validation
        function validateLoginForm() {
            // Add login form validation logic here
            return true;
        }

        function validateRegisterForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            // Add more validation logic here
            return true;
        }
        // Image Gallery
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.facility-image');
            const dots = document.querySelectorAll('.gallery-dot');
            const prevButton = document.querySelector('.gallery-arrow.prev');
            const nextButton = document.querySelector('.gallery-arrow.next');
            let currentIndex = 0;
            let slideInterval;

            function showImage(index) {
                images.forEach(img => {
                    img.classList.remove('active');
                    const titleEl = img.querySelector('.image-title');
                    if (titleEl) titleEl.style.opacity = '0';
                });
                dots.forEach(dot => dot.classList.remove('active'));
                
                images[index].classList.add('active');
                const titleEl = images[index].querySelector('.image-title');
                if (titleEl) titleEl.style.opacity = '1';
                
                dots[index].classList.add('active');
                currentIndex = index;
            }

            function nextImage() {
                const newIndex = (currentIndex + 1) % images.length;
                showImage(newIndex);
            }

            function startSlideshow() {
                slideInterval = setInterval(nextImage, 2000); // Change slide every 2 seconds
            }

            function stopSlideshow() {
                clearInterval(slideInterval);
            }

            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    stopSlideshow();
                    showImage(index);
                    startSlideshow();
                });
            });

            prevButton.addEventListener('click', () => {
                stopSlideshow();
                const newIndex = (currentIndex - 1 + images.length) % images.length;
                showImage(newIndex);
                startSlideshow();
            });

            nextButton.addEventListener('click', () => {
                stopSlideshow();
                const newIndex = (currentIndex + 1) % images.length;
                showImage(newIndex);
                startSlideshow();
            });

            // Initial setup
            if (images.length > 0) {
                showImage(0);
                startSlideshow();
            }
        });
        // Add this to your existing JavaScript
       document.querySelector('.register-form').addEventListener('submit', function(e) {
       const dob = document.getElementById('dob').value;
      if (!dob) {
        e.preventDefault();
        alert('Please enter your date of birth');
        return false;
        }
        });
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLinks = document.querySelectorAll('[href*="logout=1"]');
            logoutLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Optional: Add confirmation dialog
                    if (confirm('Are you sure you want to logout?')) {
                        // Proceed with logout
                        return true;
                    } else {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });
</script>
</body>
</html>
