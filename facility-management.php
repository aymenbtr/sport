<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Secure session setup
function secureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        session_start();
    }
}

// Facility logout function (modified)
function logout($message = null) {
    // Clear only facility-specific session data
    unset($_SESSION['facility_id']);
    unset($_SESSION['session_token']);
    unset($_SESSION['last_activity']);
    
    // Optional: add facility-specific error message for next page
    if ($message) {
        $_SESSION['facility_error'] = $message;
    }
    
    header("Location: facility-management.php");
    exit();
}

// Check if this is a facility logout request
if (isset($_GET['facility_logout'])) {
    logout();
}

// Session validation
if (isset($_SESSION['facility_id'])) {
    header("Location: facility-dashboard.php");
    exit();
}

// Handle login
if (isset($_POST['login'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Include admin_message in your SELECT statement
    $stmt = $conn->prepare("SELECT * FROM facilities WHERE email = ? AND is_deleted = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $facility = $result->fetch_assoc();
        if (password_verify($password, $facility['password'])) {
            if ($facility['status'] === 'approved') {
                // Generate and store session token
                $session_token = bin2hex(random_bytes(32));
                $_SESSION['facility_id'] = $facility['id'];
                $_SESSION['session_token'] = $session_token;
                $_SESSION['last_activity'] = time();

                // Update session token in database
                $stmt = $conn->prepare("UPDATE facilities SET session_token = ? WHERE id = ?");
                $stmt->bind_param("si", $session_token, $facility['id']);
                $stmt->execute();

                header("Location: facility-dashboard.php");
                exit();
            } else {
                $status_messages = [
                    'pending' => "Your demand is under review. Thank you for waiting.",
                    'rejected' => "Your registration was rejected. Reason: " . htmlspecialchars($facility['admin_message'])
                ];
                $_SESSION['error'] = $status_messages[$facility['status']] ?? "Invalid facility status";
            }
        } else {
            $_SESSION['error'] = "Invalid email or password";
        }
    } else {
        $_SESSION['error'] = "Invalid email or password";
    }
    header("Location: facility-management.php");
    exit();
}

// Handle facility registration with improved image handling
if (isset($_POST['register'])) {
    $target_dir = "uploads/facility/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $conn->begin_transaction();

    try {
        // Basic validation
        if (empty($_POST['sports']) || !is_array($_POST['sports'])) {
            throw new Exception("Please select at least one sport.");
        }

        if (!isset($_FILES['facilityImages']) || empty($_FILES['facilityImages']['name'][0])) {
            throw new Exception("Please upload at least one facility image.");
        }

        // Sanitize and validate input
        $company_name = filter_var($_POST['companyName'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $phone = filter_var($_POST['companyPhone'], FILTER_SANITIZE_STRING);
        $address = filter_var($_POST['facilityAddress'], FILTER_SANITIZE_STRING);
        $wilaya_id = filter_var($_POST['wilaya_id'], FILTER_VALIDATE_INT);
        $opening_hours = filter_var($_POST['openingHours'], FILTER_SANITIZE_STRING);
        $description = filter_var($_POST['facilityDescription'], FILTER_SANITIZE_STRING);
        $social_urls = array_map(function($url) {
            return filter_var($url, FILTER_SANITIZE_URL);
        }, [
            'facebook_url' => $_POST['facebook_url'] ?? '',
            'instagram_url' => $_POST['instagram_url'] ?? '',
            'twitter_url' => $_POST['twitter_url'] ?? '',
            'website_url' => $_POST['website_url'] ?? ''
        ]);

        // Check for duplicate email
        $stmt = $conn->prepare("SELECT id FROM facilities WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already registered");
        }

        // Insert facility
        $stmt = $conn->prepare("INSERT INTO facilities (company_name, email, password, phone, 
            address, wilaya_id, opening_hours, description, facebook_url, instagram_url, 
            twitter_url, website_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $stmt->bind_param("sssssissssss", $company_name, $email, $password, $phone, 
            $address, $wilaya_id, $opening_hours, $description, $social_urls['facebook_url'], 
            $social_urls['instagram_url'], $social_urls['twitter_url'], $social_urls['website_url']);

        if (!$stmt->execute()) {
            throw new Exception("Error registering facility");
        }

        $facility_id = $stmt->insert_id;

        // Handle sports
        $sport_stmt = $conn->prepare("INSERT INTO facility_sports (facility_id, sport_id) VALUES (?, ?)");
        foreach ($_POST['sports'] as $sport_id) {
            $sport_id = filter_var($sport_id, FILTER_VALIDATE_INT);
            if ($sport_id) {
                $sport_stmt->bind_param("ii", $facility_id, $sport_id);
                if (!$sport_stmt->execute()) {
                    throw new Exception("Error adding sports");
                }
            }
        }

        // Handle images with improved validation
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $image_stmt = $conn->prepare("INSERT INTO facility_image (facility_id, image_path) VALUES (?, ?)");
        
        foreach ($_FILES['facilityImages']['tmp_name'] as $i => $tmp_name) {
            if ($_FILES['facilityImages']['size'][$i] > 0) {
                // Validate file type and size
                $file_type = $_FILES['facilityImages']['type'][$i];
                $file_size = $_FILES['facilityImages']['size'][$i];
                
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed.");
                }
                
                if ($file_size > $max_size) {
                    throw new Exception("File size too large. Maximum size is 5MB.");
                }

                $file_extension = strtolower(pathinfo($_FILES['facilityImages']['name'][$i], PATHINFO_EXTENSION));
                $file_name = uniqid() . '_' . $i . '.' . $file_extension;
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($tmp_name, $target_file)) {
                    $image_stmt->bind_param("is", $facility_id, $target_file);
                    if (!$image_stmt->execute()) {
                        throw new Exception("Error uploading images.");
                    }
                } else {
                    throw new Exception("Error uploading images.");
                }
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Registration successful! Your facility is under review.";
        header("Location: facility-management.php#login-form");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: facility-management.php#register-form");
        exit();
    }
}

// Fetch data for forms
$sports = $conn->query("SELECT * FROM sport ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$wilayas = $conn->query("SELECT * FROM wilaya ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Session timeout check
$timeout = 30 * 60; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    logout("Session expired. Please login again.");
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Management - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
     <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --secondary-color: #4f46e5;
            --background-light: #f8fafc;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --success-color: #059669;
            --error-color: #dc2626;
            --card-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --input-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            line-height: 1.6;
        }

        nav {
            background-color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--card-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 1.75rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            margin-left: 1.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-links a:hover {
            background-color: var(--background-light);
            color: var(--primary-color);
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .tabs button {
            flex: 1;
            padding: 1rem;
            border: none;
            background: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-light);
            border-radius: 0.5rem;
        }

        .tabs button:hover {
            color: var(--primary-color);
            background-color: var(--background-light);
        }

        .tabs button.active {
            color: var(--primary-color);
            background-color: #eff6ff;
            position: relative;
        }

        .tabs button.active::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        input[type="url"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: var(--input-shadow);
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid var(--border-color) !important;
            padding: 0.5rem !important;
            min-height: 100px !important;
            border-radius: 0.5rem !important;
        }

        button[type="submit"] {
            width: 100%;
            padding: 1rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
        }

        button[type="submit"]:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .error-message,
        .success-message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem auto;
            max-width: 800px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-message {
            background-color: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fee2e2;
        }

        .success-message {
            background-color: #f0fdf4;
            color: var(--success-color);
            border: 1px solid #dcfce7;
        }

        .images-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-container {
            position: relative;
            padding-bottom: 100%;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .preview-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .preview-container:hover img {
            transform: scale(1.05);
        }

        .preview-container button {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background-color: rgba(220, 38, 38, 0.9);
            color: white;
            border: none;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            z-index: 1;
            transition: all 0.3s ease;
        }

        .preview-container button:hover {
            background-color: var(--error-color);
            transform: scale(1.1);
        }

        .file-upload-label {
            display: block;
            padding: 2rem;
            background-color: #f8fafc;
            border: 2px dashed var(--border-color);
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background-color: #eff6ff;
        }

        .file-upload-text {
            display: block;
            margin-top: 0.5rem;
            color: var(--text-light);
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }

            .tabs {
                flex-direction: column;
            }

            .nav-links a {
                margin-left: 0.5rem;
                padding: 0.5rem;
            }
        }

        /* Loading State */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Custom Select2 Styling */
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            margin: 0.25rem;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            color: white;
            margin-right: 0.5rem;
            border-right: none;
        }

        .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
            background-color: transparent;
            color: #fee2e2;
        }
     </style>
</head>
<body>
<nav>
<a href="intro.php" class="btn btn-secondary back-button">
<i class="fas fa-arrow-left me-2"></i> Back to home
</a>
  <div class="logo">SportsConnect</div>
      <div class="nav-links">
            <?php if (isset($_SESSION['facility_id'])): ?>
                <a href="facility-dashboard.php" id="profileButton">Profile</a>
                <a href="facility-management.php?logout=true" id="logoutButton">Logout</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="error-message">
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="success-message">
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>
  <div class="container">
        <div class="tabs">
            <button onclick="showLoginForm()" id="loginTab">Login</button>
            <button onclick="showRegisterForm()" id="registerTab">Register</button>
        </div>

        <div id="login-form">
            <form method="POST" action="facility-management.php">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
        </div>

        <div id="register-form" style="display: none;">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="text" name="companyName" placeholder="Company Name" required>
                </div>

                <div class="form-group">
                    <select name="wilaya_id" required>
                        <option value="">Select Wilaya</option>
                        <?php foreach ($wilayas as $wilaya): ?>
                            <option value="<?php echo $wilaya['id']; ?>">
                                <?php echo htmlspecialchars($wilaya['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="select-label">Select Sports (Multiple)</label>
                    <select name="sports[]" multiple class="select2" required>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo $sport['id']; ?>">
                                <?php echo htmlspecialchars($sport['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>

                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>

                <div class="form-group">
                    <input type="tel" name="companyPhone" placeholder="Phone Number" required>
                </div>

                <div class="form-group">
                    <input type="text" name="facilityAddress" placeholder="Address" required>
                </div>

                <div class="form-group">
                    <input type="text" name="openingHours" placeholder="Opening Hours (e.g., Mon-Fri: 9AM-6PM)" required>
                </div>

                <div class="form-group">
                    <textarea name="facilityDescription" placeholder="Facility Description" required></textarea>
                </div>

                <div class="form-group">
                    <input type="url" name="facebook_url" placeholder="Facebook URL">
                </div>

                <div class="form-group">
                    <input type="url" name="instagram_url" placeholder="Instagram URL">
                </div>

                <div class="form-group">
                    <input type="url" name="twitter_url" placeholder="Twitter URL">
                </div>

                <div class="form-group">
                    <input type="url" name="website_url" placeholder="Website URL">
                </div>

                <div class="form-group">
                    <label class="file-upload-label">
                        <input type="file" name="facilityImages[]" multiple accept="image/*" 
                               onchange="previewImages(event)" required style="display: none;">
                        <span>Click to upload facility images</span>
                        <span class="file-upload-text">Upload up to 8 images</span>
                    </label>
                    <div id="imageError"></div>
                    <div id="imagesPreview" class="images-preview"></div>
                </div>

                <button type="submit" name="register">Register</button>
            </form>
        </div>
    </div>

    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Select sports",
                allowClear: true,
                width: '100%'
            });

            // Show appropriate form based on hash
            if (window.location.hash === '#login-form') {
                showLoginForm();
            } else if (window.location.hash === '#register-form') {
                showRegisterForm();
            }

            // Handle browser back button
            window.onpopstate = function(event) {
                if (window.location.hash === '#login-form') {
                    showLoginForm();
                } else if (window.location.hash === '#register-form') {
                    showRegisterForm();
                }
            };
        });

        function showLoginForm() {
            document.getElementById('login-form').style.display = 'block';
            document.getElementById('register-form').style.display = 'none';
            document.getElementById('loginTab').classList.add('active');
            document.getElementById('registerTab').classList.remove('active');
            window.location.hash = 'login-form';
        }

        function showRegisterForm() {
            document.getElementById('login-form').style.display = 'none';
            document.getElementById('register-form').style.display = 'block';
            document.getElementById('loginTab').classList.remove('active');
            document.getElementById('registerTab').classList.add('active');
            window.location.hash = 'register-form';
        }

        // Image preview functionality
        window.onload = function() {
            if (window.history && window.history.pushState) {
                window.history.pushState('forward', null, window.location.href);
                window.onpopstate = function(e) {
                    if (document.referrer.includes('facility-dashboard.php')) {
                        window.history.forward();
                    }
                };
            }
        }

        // Enhanced image preview functionality
        let selectedFiles = [];
        const maxFiles = 8;
        const maxFileSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

        function previewImages(event) {
            const preview = document.getElementById('imagesPreview');
            const errorMsg = document.getElementById('imageError');
            const files = Array.from(event.target.files);
            
            // Validate number of files
            if (selectedFiles.length + files.length > maxFiles) {
                errorMsg.style.display = 'block';
                errorMsg.textContent = `Maximum ${maxFiles} images allowed`;
                event.target.value = '';
                return;
            }

            // Validate each file
            for (const file of files) {
                if (!allowedTypes.includes(file.type)) {
                    errorMsg.style.display = 'block';
                    errorMsg.textContent = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
                    event.target.value = '';
                    return;
                }
                if (file.size > maxFileSize) {
                    errorMsg.style.display = 'block';
                    errorMsg.textContent = 'File size too large. Maximum size is 5MB.';
                    event.target.value = '';
                    return;
                }
            }

            errorMsg.style.display = 'none';
            selectedFiles = [...selectedFiles, ...files];
            updatePreview();
        }

        function updatePreview() {
            const preview = document.getElementById('imagesPreview');
            preview.innerHTML = '';

            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                const container = document.createElement('div');
                container.className = 'preview-container';

                reader.onload = function(e) {
                    container.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" onclick="removeImage(${index})" 
                                title="Remove image">×</button>
                    `;
                };

                reader.readAsDataURL(file);
                preview.appendChild(container);
            });

            // Update form data
            const formData = new FormData();
            selectedFiles.forEach((file, index) => {
                formData.append(`facilityImages[]`, file);
            });
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            updatePreview();
        }

        // Enhanced form validation
        function validateForm() {
            const requiredFields = [
                'companyName', 'email', 'password', 'companyPhone', 
                'facilityAddress', 'wilaya_id', 'openingHours', 'facilityDescription'
            ];
            
            for (const field of requiredFields) {
                const element = document.getElementsByName(field)[0];
                if (!element.value.trim()) {
                    alert(`Please fill in the ${field.replace(/([A-Z])/g, ' $1').toLowerCase()}`);
                    element.focus();
                    return false;
                }
            }

            const sports = document.querySelector('select[name="sports[]"]');
            if (sports.selectedOptions.length === 0) {
                alert('Please select at least one sport');
                sports.focus();
                return false;
            }

            if (selectedFiles.length === 0) {
                alert('Please upload at least one facility image');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>