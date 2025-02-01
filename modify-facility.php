<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate session and facility access
function validateFacilitySession($conn) {
    if (!isset($_SESSION['facility_id']) || !isset($_SESSION['session_token'])) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT session_token FROM facilities WHERE id = ? AND is_deleted = 0");
    $stmt->bind_param("i", $_SESSION['facility_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $facility = $result->fetch_assoc();
        return hash_equals($facility['session_token'], $_SESSION['session_token']);
    }
    return false;
}

// Check if user is logged in and session is valid
if (!isset($_SESSION['facility_id']) || !validateFacilitySession($conn)) {
    session_destroy();
    header("Location: facility-management.php?error=Please login to access modification page");
    exit();
}

// Fetch current facility details
$stmt = $conn->prepare("SELECT * FROM facilities WHERE id = ?");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();

// Fetch current facility sports
$stmt = $conn->prepare("SELECT sport_id FROM facility_sports WHERE facility_id = ?");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$current_sports = $stmt->get_result();
$current_sport_ids = [];
while ($sport = $current_sports->fetch_assoc()) {
    $current_sport_ids[] = $sport['sport_id'];
}

// Fetch all sports for checkboxes
$sports_query = "SELECT id, name FROM sport ORDER BY name";
$sports_result = $conn->query($sports_query);

// Fetch all wilayas for dropdown
$wilayas_query = "SELECT id, name FROM wilaya ORDER BY name";
$wilayas_result = $conn->query($wilayas_query);

// Handle form submission for facility details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_facility'])) {
    $company_name = trim($_POST['company_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $wilaya_id = intval($_POST['wilaya_id']);
    $opening_hours = trim($_POST['opening_hours']);
    $description = trim($_POST['description']);
    $facebook_url = trim($_POST['facebook_url']);
    $instagram_url = trim($_POST['instagram_url']);
    $twitter_url = trim($_POST['twitter_url']);
    $website_url = trim($_POST['website_url']);

    // Prepare and execute update statement
    $stmt = $conn->prepare("
        UPDATE facilities SET 
        company_name = ?, email = ?, phone = ?, 
        address = ?, wilaya_id = ?, opening_hours = ?, 
        description = ?, facebook_url = ?, instagram_url = ?, 
        twitter_url = ?, website_url = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssssssssi", 
        $company_name, $email, $phone, $address, $wilaya_id, 
        $opening_hours, $description, $facebook_url, $instagram_url, 
        $twitter_url, $website_url, $_SESSION['facility_id']
    );
    $stmt->execute();

    // Update sports
    // First, remove existing sports
    $stmt = $conn->prepare("DELETE FROM facility_sports WHERE facility_id = ?");
    $stmt->bind_param("i", $_SESSION['facility_id']);
    $stmt->execute();

    // Then, insert new sports
    if (isset($_POST['sports']) && is_array($_POST['sports'])) {
        $sport_stmt = $conn->prepare("INSERT INTO facility_sports (facility_id, sport_id) VALUES (?, ?)");
        foreach ($_POST['sports'] as $sport_id) {
            $sport_stmt->bind_param("ii", $_SESSION['facility_id'], $sport_id);
            $sport_stmt->execute();
        }
    }

    // Redirect with success message
    header("Location: facility-dashboard.php?success=Facility information updated successfully");
    exit();
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['facility_image']) && $_FILES['facility_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        $file_type = $_FILES['facility_image']['type'];
        $file_size = $_FILES['facility_image']['size'];

        if (in_array($file_type, $allowed_types) && $file_size <= $max_file_size) {
            $upload_dir = 'uploads/facility_images/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $image_title = trim($_POST['image_title']) ?? 'Facility Image';
            $file_extension = pathinfo($_FILES['facility_image']['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_filename;

            if (move_uploaded_file($_FILES['facility_image']['tmp_name'], $upload_path)) {
                // Insert image record into database
                $stmt = $conn->prepare("INSERT INTO facility_image (facility_id, image_path, title) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $_SESSION['facility_id'], $upload_path, $image_title);
                $stmt->execute();

                header("Location: modify-facility.php?success=Image uploaded successfully");
                exit();
            }
        }
    }
}

// Fetch uploaded images
$stmt = $conn->prepare("SELECT id, image_path, title FROM facility_image WHERE facility_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$uploaded_images = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modify Facility - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --light-background: #f4f6f7;
            --text-color: #2c3e50;
        }

        body {
            background-color: var(--light-background);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--text-color);
        }

        .facility-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .form-control, .form-select {
            border: 1.5px solid #e0e4e7;
            border-radius: 8px;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background-color: var(--light-background);
            border-bottom: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .image-upload-container {
            border: 2px dashed #e0e4e7;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .image-upload-container:hover {
            border-color: var(--secondary-color);
            background-color: rgba(52, 152, 219, 0.05);
        }

        .uploaded-image {
            max-width: 200px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }

        .uploaded-image:hover {
            transform: scale(1.05);
        }

        .form-check-input:checked {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container facility-container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <h1 class="mb-4 text-center">
                    <i class="fas fa-edit text-secondary"></i> Modify Facility Information
                </h1>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="mb-4">
                <input type="hidden" name="update_facility" value="1">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['company_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['email']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['phone']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Wilaya</label>
                        <select name="wilaya_id" class="form-control" required>
                            <?php while ($wilaya = $wilayas_result->fetch_assoc()): ?>
                                <option value="<?php echo $wilaya['id']; ?>"
                                    <?php echo ($wilaya['id'] == $facility['wilaya_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($wilaya['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" 
                           value="<?php echo htmlspecialchars($facility['address']); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Opening Hours</label>
                    <input type="text" name="opening_hours" class="form-control" 
                           value="<?php echo htmlspecialchars($facility['opening_hours']); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($facility['description']); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Facebook URL (Optional)</label>
                        <input type="url" name="facebook_url" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['facebook_url']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Instagram URL (Optional)</label>
                        <input type="url" name="instagram_url" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['instagram_url']); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Twitter URL (Optional)</label>
                        <input type="url" name="twitter_url" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['twitter_url']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Website URL (Optional)</label>
                        <input type="url" name="website_url" class="form-control" 
                               value="<?php echo htmlspecialchars($facility['website_url']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Available Sports</label>
                    <div class="row">
                        <?php while ($sport = $sports_result->fetch_assoc()): ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="sports[]" value="<?php echo $sport['id']; ?>"
                                           id="sport_<?php echo $sport['id']; ?>"
                                           <?php echo in_array($sport['id'], $current_sport_ids) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sport_<?php echo $sport['id']; ?>">
                                        <?php echo htmlspecialchars($sport['name']); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Facility Information
                    </button>
                </div>
            </form>

            <div class="card mt-4">
                <div class="card-header">
                    <i class="fas fa-images"></i> Image Upload
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" class="image-upload-container">
                        <input type="hidden" name="upload_image" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Image Title (Optional)</label>
                            <input type="text" name="image_title" class="form-control" placeholder="Describe this image">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Image</label>
                            <input type="file" name="facility_image" class="form-control" accept="image/*" required>
                            <small class="text-muted">Max file size: 5MB. Allowed types: JPEG, PNG, GIF, WebP</small>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                    </form>

                    <div class="mt-4">
                        <h3>Uploaded Images</h3>
                        <?php 
                        if ($uploaded_images->num_rows > 0): 
                            while ($image = $uploaded_images->fetch_assoc()): 
                        ?>
                            <div class="d-inline-block text-center m-2">
                                <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                     class="uploaded-image" 
                                     alt="<?php echo htmlspecialchars($image['title']); ?>">
                                <p><?php echo htmlspecialchars($image['title'] ?: 'Untitled'); ?></p>
                                </div>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                            <p class="text-muted">No images uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Client-side validation for image upload
        const imageUploadForm = document.querySelector('form[enctype="multipart/form-data"]');
        const imageInput = imageUploadForm.querySelector('input[type="file"]');
        
        imageUploadForm.addEventListener('submit', function(e) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!imageInput.files[0]) {
                e.preventDefault();
                alert('Please select an image to upload.');
                return;
            }
            
            const file = imageInput.files[0];
            
            if (!allowedTypes.includes(file.type)) {
                e.preventDefault();
                alert('Invalid file type. Please upload JPEG, PNG, GIF, or WebP images.');
                return;
            }
            
            if (file.size > maxSize) {
                e.preventDefault();
                alert('File is too large. Maximum size is 5MB.');
                return;
            }
        });

        // Optional: Preview uploaded image
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const imgPreview = document.createElement('img');
                    imgPreview.src = event.target.result;
                    imgPreview.classList.add('img-fluid', 'mt-3');
                    imgPreview.style.maxHeight = '200px';
                    
                    // Remove previous previews
                    const oldPreviews = document.querySelectorAll('.img-preview');
                    oldPreviews.forEach(preview => preview.remove());
                    
                    imgPreview.classList.add('img-preview');
                    e.target.closest('.image-upload-container').appendChild(imgPreview);
                };
                reader.readAsDataURL(file);
            }
        });
    });
</script>
</body>
</html>