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
    header("Location: facility-management.php?error=Please login to access your dashboard");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    // Clear session token from database
    $stmt = $conn->prepare("UPDATE facilities SET session_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['facility_id']);
    $stmt->execute();
    
    session_destroy();
    header("Location: facility-management.php");
    exit();
}

// Check account status
function checkAccountStatus($conn) {
    $stmt = $conn->prepare("SELECT status, is_deleted FROM facilities WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['facility_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $facility = $result->fetch_assoc();
        if ($facility['is_deleted'] == 1 || $facility['status'] !== 'approved') {
            session_destroy();
            header("Location: facility-management.php?error=Account no longer active");
            exit();
        }
    }
}

checkAccountStatus($conn);

// Fetch facility details
$stmt = $conn->prepare("
    SELECT f.*, w.name as wilaya_name 
    FROM facilities f 
    LEFT JOIN wilaya w ON f.wilaya_id = w.id 
    WHERE f.id = ?
");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();
// Fetch facility sports
$stmt = $conn->prepare("
    SELECT s.name, s.id
    FROM facility_sports fs 
    JOIN sport s ON fs.sport_id = s.id 
    WHERE fs.facility_id = ?
    ORDER BY s.name
");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$sports_result = $stmt->get_result();
$facility_sports = [];
while ($sport = $sports_result->fetch_assoc()) {
    $facility_sports[] = $sport;
}

// Fetch ALL facility images
$stmt = $conn->prepare("
    SELECT id, image_path 
    FROM facility_image 
    WHERE facility_id = ? 
    ORDER BY id ASC
");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$images_result = $stmt->get_result();
$facility_images = [];
while ($image = $images_result->fetch_assoc()) {
    $facility_images[] = $image;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Dashboard - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-color: #1f2937;
            --border-color: #e5e7eb;
            --success-color: #22c55e;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        nav {
            background-color: var(--primary-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo i {
            font-size: 1.8rem;
        }

        nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        nav a:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
        }

        .dashboard {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            margin-bottom: 2rem;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 2rem;
        }

        .card {
            background-color: var(--card-background);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            margin-bottom: 1.5rem;
        }

        .info-item strong {
            color: var(--secondary-color);
            display: block;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .sports-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .sport-tag {
            background-color: var(--accent-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .sport-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .image-container {
            position: relative;
            padding-top: 75%;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .image-container:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .image-container img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .social-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-link {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }

        .social-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: capitalize;
            background-color: var(--success-color);
            color: white;
            margin-bottom: 1rem;
        }

        .admin-message {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background-color: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .modification-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            margin-top: 1.5rem;
            width: 100%;
            justify-content: center;
        }

        .modification-button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr;
            }

            .dashboard {
                padding: 0 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }

            .image-gallery {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .social-links {
                flex-direction: column;
            }

            .social-link {
                width: 100%;
            }

            .card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .image-gallery {
                grid-template-columns: 1fr;
            }

            .sport-tag {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<nav>
<div>
<a href="intro.php" class="btn btn-secondary back-button">
<i class="fas fa-arrow-left me-2"></i> Back to home
</a>
    </div>
<div class="logo">
<i class="fas fa-running"></i>
SportsConnect
</div>
<div>
<a href="?logout=1">
<i class="fas fa-sign-out-alt"></i>
Logout
</a>
<?php if (isset($_SESSION['facility_id'])): ?>
<a href="admin-login.php" class="btn btn-primary">
<i class="fas fa-users-cog"></i> Admin Control Panel
</a>
<?php endif; ?>
</div>
</nav>
<div class="dashboard">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($facility['company_name']); ?></h1>
            <p>Manage your facility information and view your current status</p>
        </div>
        
        <div class="grid-container">
            <div class="main-content">
                <div class="card">
                    <h2>
                        <i class="fas fa-info-circle"></i>
                        Facility Information
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>
                                <i class="fas fa-envelope"></i>
                                Email
                            </strong>
                            <?php echo htmlspecialchars($facility['email']); ?>
                        </div>
                        <div class="info-item">
                            <strong>
                                <i class="fas fa-phone"></i>
                                Phone
                            </strong>
                            <?php echo htmlspecialchars($facility['phone']); ?>
                        </div>
                        <div class="info-item">
                            <strong>
                                <i class="fas fa-map-marker-alt"></i>
                                Address
                            </strong>
                            <?php echo htmlspecialchars($facility['address']); ?>
                        </div>
                        <div class="info-item">
                            <strong>
                                <i class="fas fa-map"></i>
                                Wilaya
                            </strong>
                            <?php echo htmlspecialchars($facility['wilaya_name']); ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <strong>
                            <i class="far fa-clock"></i>
                            Opening Hours
                        </strong>
                        <?php echo htmlspecialchars($facility['opening_hours']); ?>
                    </div>

                    <div class="info-item">
                        <strong>
                            <i class="fas fa-basketball-ball"></i>
                            Available Sports
                        </strong>
                        <div class="sports-list">
                            <?php foreach ($facility_sports as $sport): ?>
                                <span class="sport-tag">
                                    <i class="fas fa-medal"></i>
                                    <?php echo htmlspecialchars($sport['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="info-item">
                        <strong>
                            <i class="fas fa-align-left"></i>
                            Description
                        </strong>
                        <?php echo nl2br(htmlspecialchars($facility['description'])); ?>
                    </div>

                    <div class="info-item">
                        <strong>
                            <i class="fas fa-share-alt"></i>
                            Social Media
                        </strong>
                        <div class="social-links">
                            <?php if ($facility['facebook_url']): ?>
                                <a href="<?php echo htmlspecialchars($facility['facebook_url']); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-facebook"></i> Facebook
                                </a>
                            <?php endif; ?>
                            <?php if ($facility['instagram_url']): ?>
                                <a href="<?php echo htmlspecialchars($facility['instagram_url']); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-instagram"></i> Instagram
                                </a>
                            <?php endif; ?>
                            <?php if ($facility['twitter_url']): ?>
                                <a href="<?php echo htmlspecialchars($facility['twitter_url']); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-twitter"></i> Twitter
                                </a>
                            <?php endif; ?>
                            <?php if ($facility['website_url']): ?>
                                <a href="<?php echo htmlspecialchars($facility['website_url']); ?>" target="_blank" class="social-link">
                                    <i class="fas fa-globe"></i> Website
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2>
                        <i class="fas fa-images"></i>
                        Facility Images
                    </h2>
                    <?php if (count($facility_images) > 0): ?>
                        <div class="image-gallery">
                            <?php foreach ($facility_images as $image): ?>
                                <a href="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                   data-lightbox="facility-images" 
                                   data-title="Facility Image" 
                                   class="image-container">
                                    <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                                         alt="Facility Image">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No images uploaded yet.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <div class="card">
                    <h2>
                        <i class="fas fa-chart-line"></i>
                        Account Status
                    </h2>
                    <div class="status-badge">
                        <i class="fas fa-check-circle"></i>
                        <?php echo ucfirst(htmlspecialchars($facility['status'])); ?>
                    </div>
                    <?php if ($facility['admin_message']): ?>
                        <div class="admin-message">
                            <strong>
                                <i class="fas fa-comment-alt"></i>
                                Admin Message:
                            </strong><br>
                            <?php echo htmlspecialchars($facility['admin_message']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="modify-facility.php" class="modification-button">
                        <i class="fas fa-edit"></i>
                        Modify Facility Information
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>
    <script>
        // Initialize Lightbox
        lightbox.option({
            'resizeDuration': 200,
            'wrapAround': true,
            'albumLabel': 'Image %1 of %2',
            'fadeDuration': 300,
            'imageFadeDuration': 300
        });

        // Automatic session check every 30 seconds
        setInterval(function() {
            fetch('check_session.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.valid) {
                        window.location.href = 'facility-management.php?error=' + encodeURIComponent(data.message);
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 30000);

        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>