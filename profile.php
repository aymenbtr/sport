<?php
session_name('user_session');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: facility-user.php");
    exit();
}

// Database connection constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'test');
define('PROFILE_UPLOAD_DIR', 'uploads/profile_pics/');
define('MAX_FILE_SIZE', 5000000); // 5MB
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errors = [];
$success_message = '';

// Profile Picture Upload Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $file = $_FILES['profile_pic'];
    
    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_type = mime_content_type($file['tmp_name']);
        $file_size = $file['size'];

        // Check file type and size
        if (in_array($file_type, ALLOWED_FILE_TYPES) && $file_size <= MAX_FILE_SIZE) {
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('profile_') . '.' . $file_extension;
            $upload_path = PROFILE_UPLOAD_DIR . $new_filename;

            // Ensure upload directory exists
            if (!is_dir(PROFILE_UPLOAD_DIR)) {
                mkdir(PROFILE_UPLOAD_DIR, 0755, true);
            }

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update user's profile picture path in database
                $stmt = $conn->prepare("UPDATE users SET profile_pic_path = ? WHERE id = ?");
                $stmt->bind_param("si", $upload_path, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success_message = "Profile picture uploaded successfully!";
                } else {
                    $errors[] = "Database update failed: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "File upload failed.";
            }
        } else {
            $errors[] = "Invalid file type or size exceeds 5MB.";
        }
    } else {
        $errors[] = "File upload error: " . $file['error'];
    }
}

// Fetch user details
$stmt = $conn->prepare("
    SELECT u.*, w.name as wilaya_name, f.company_name as facility_name,
           GROUP_CONCAT(DISTINCT s.name) as sports
    FROM users u
    LEFT JOIN wilaya w ON u.wilaya_id = w.id
    LEFT JOIN facilities f ON u.facility_id = f.id
    LEFT JOIN user_sports us ON u.id = us.user_id
    LEFT JOIN sport s ON us.sport_id = s.id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Logout handling
if (isset($_GET['logout'])) {
    session_destroy();
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    header("Location: facility-user.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2575fc;
            --secondary-color: #6a11cb;
            --success-color: #00b09b;
            --danger-color: #ff6b6b;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-primary: #1a1a1a;
            --text-secondary: #666666;
            --border-radius: 16px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-container {
            width: 100%;
            max-width: 100%; /* Full width */
            background: var(--card-bg);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: var(--border-radius);
            padding: 3rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('/api/placeholder/1920/1080') center/cover;
            opacity: 0.1;
            mix-blend-mode: overlay;
        }

        .profile-header-content {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 3rem;
            align-items: center;
        }

        .profile-pic-container {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .profile-pic:hover {
            transform: scale(1.02);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        .profile-pic-upload-label {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .profile-pic-upload-label:hover {
            transform: scale(1.1);
        }
        .profile-pic-upload {
            display: none;
        }

        .profile-pic-upload-label i {
            color: white;
            font-size: 1.8rem;
        }

        .profile-header-info {
            color: white;
            margin-top: 1rem;
        }

        .profile-info h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .profile-info p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .mood-selector {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            background: white;
            padding: 10px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .mood-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .mood-btn:hover {
            transform: scale(1.2);
        }

        .mood-calm { background: var(--mood-calm); }
        .mood-energetic { background: var(--mood-energetic); }
        .mood-serene { background: var(--mood-serene); }
        .mood-passionate { background: var(--mood-passionate); }

        .profile-header-info p {
            opacity: 0.9;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
        }

        .profile-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 3rem;
            background-color: var(--color-background);
        }

        .profile-section {
            background-color: white;
            border-radius: var(--border-radius-soft);
            padding: 2rem;
            box-shadow: var(--shadow-smooth);
            transition: all 0.4s ease;
            border-top: 4px solid var(--color-primary);
        }

        .profile-section:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .profile-section h2 {
            color: var(--color-primary);
            border-bottom: 2px solid var(--color-primary);
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-section-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s ease;
        }

        .profile-section-item:hover {
            background-color: rgba(41, 128, 185, 0.05);
        }

        .profile-section-item span:first-child {
            color: var(--color-text-light);
            font-weight: 500;
        }

        .profile-section-item span:last-child {
            color: var(--color-text-dark);
            font-weight: 600;
        }

        .profile-actions {
            display: flex;
            justify-content: center;
            gap: 2rem;
            padding: 2.5rem;
            background: var(--color-background);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 15px 30px rgba(37, 117, 252, 0.3);
        }

        .btn-danger {
            background: var(--gradient-secondary);
            color: white;
            box-shadow: 0 15px 30px rgba(255, 107, 107, 0.3);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            z-index: -1;
        }

        .btn:hover::before {
            left: 0;
        }

        .btn:hover {
            transform: translateY(-5px);
        }
        .info-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .info-card h2 {
            color: var(--primary-color);
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-card h2 i {
            font-size: 1.1em;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .profile-header {
                height: auto;
                padding: 2rem 1rem;
            }

            .profile-pic-container {
                width: 250px;
                height: 250px;
            }

            .profile-pic-upload-label {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }

            .profile-pic-upload-label i {
                font-size: 1.2rem;
            }

            .profile-header-info h1 {
                font-size: 2.5rem;
            }

            .profile-header-info p {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <form method="POST" enctype="multipart/form-data">
            <div class="profile-header">
                <div class="profile-pic-container">
                    <img src="<?php echo !empty($user['profile_pic_path']) ? htmlspecialchars($user['profile_pic_path']) : 'default-avatar.png'; ?>" 
                         alt="Profile Picture" 
                         class="profile-pic" 
                         id="profile-pic-preview">
                    <label for="profile-pic-upload" class="profile-pic-upload-label">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" 
                           name="profile_pic" 
                           id="profile-pic-upload" 
                           class="profile-pic-upload" 
                           accept="image/jpeg,image/png,image/gif">
                </div>
                <div class="profile-header-info">
                    <h1><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </form>

        <div class="profile-content">
            <div class="profile-section">
                <h2><i class="fas fa-user"></i> Personal Details</h2>
                <div class="profile-section-item">
                    <span>Date of Birth</span>
                    <span><?php echo htmlspecialchars($user['dob']); ?></span>
                </div>
                <div class="profile-section-item">
                    <span>Place of Birth</span>
                    <span><?php echo htmlspecialchars($user['pob']); ?></span>
                </div>
                <div class="profile-section-item">
                    <span>Gender</span>
                    <span><?php echo ucfirst(htmlspecialchars($user['gender'])); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h2><i class="fas fa-address-card"></i> Contact Information</h2>
                <div class="profile-section-item">
                    <span>Phone:</span>
                    <span><?php echo htmlspecialchars($user['phone']); ?></span>
                </div>
                <div class="profile-section-item">
                    <span>Address:</span>
                    <span><?php echo htmlspecialchars($user['address']); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h2><i class="fas fa-map-marker-alt"></i> Location</h2>
                <div class="profile-section-item">
                    <span>Wilaya</span>
                    <span><?php echo htmlspecialchars($user['wilaya_name'] ?? 'Not specified'); ?></span>
                </div>
                <div class="profile-section-item">
                    <span>Facility:</span>
                    <span><?php echo htmlspecialchars($user['facility_name'] ?? 'Not specified'); ?></span>
                </div>
            </div>

            <div class="profile-section">
                <h2><i class="fas fa-running"></i> Sports Interests</h2>
                <div class="profile-section-item">
                    <span>Sports</span>
                    <span>
                        <?php 
                        echo !empty($user['sports']) ? 
                            htmlspecialchars($user['sports']) : 
                            'No sports selected'; 
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="profile-actions">
            <a href="?logout=1" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <script>
        // Preview profile picture before upload
        document.getElementById('profile-pic-upload').addEventListener('change', function(e) {
            const file = this.files[0];
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload JPG, PNG or GIF.');
                this.value = '';
                return false;
            }

            if (file.size > maxSize) {
                alert('File size too large. Maximum size is 5MB.');
                this.value = '';
                return false;
            }

            // Preview the image
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile-pic-preview').src = e.target.result;
            }
            reader.readAsDataURL(file);

            // Automatically submit the form when a file is selected
            this.closest('form').submit();
        });

        // Fade out alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    </script>
</body>
</html>