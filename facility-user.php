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

// Fetch all wilayas
$wilayas = $conn->query("SELECT * FROM wilaya ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Fetch all sports
$sports = $conn->query("SELECT * FROM sport ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Function to get facilities by wilaya and sport
function getFacilities($conn, $wilaya_id = null, $sport_id = null) {
    $query = "
        SELECT DISTINCT f.*, w.name as wilaya_name, GROUP_CONCAT(DISTINCT s.name) as sports,
        GROUP_CONCAT(DISTINCT fi.image_path) as images,
        (SELECT COUNT(*) FROM facility_sports WHERE facility_id = f.id) as sport_count
        FROM facilities f
        LEFT JOIN wilaya w ON f.wilaya_id = w.id
        LEFT JOIN facility_sports fs ON f.id = fs.facility_id
        LEFT JOIN sport s ON fs.sport_id = s.id
        LEFT JOIN facility_image fi ON f.id = fi.facility_id
        WHERE f.status = 'approved' AND f.is_deleted = 0
    ";
    
    if ($wilaya_id) {
        $query .= " AND f.wilaya_id = " . intval($wilaya_id);
    }
    if ($sport_id) {
        $query .= " AND fs.sport_id = " . intval($sport_id);
    }
    
    $query .= " GROUP BY f.id ORDER BY f.company_name";
    
    return $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get selected wilaya and sport from URL parameters
$selected_wilaya = isset($_GET['wilaya']) ? intval($_GET['wilaya']) : null;
$selected_sport = isset($_GET['sport']) ? intval($_GET['sport']) : null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Facilities - SportsConnect</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* Core colors */
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #60a5fa;
            --secondary-color: #7c3aed;
            --accent-color: #f59e0b;
            
            /* Background colors */
            --bg-primary: #f8fafc;
            --bg-secondary: #f1f5f9;
            --bg-card: #ffffff;
            
            /* Text colors */
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-light: #94a3b8;
            
            /* Status colors */
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            
            /* Gradients */
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            --gradient-accent: linear-gradient(135deg, var(--accent-color), #e11d48);
            
            /* Shadows */
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            
            /* Border Radius */
            --radius-sm: 0.375rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            
            /* Transitions */
            --transition-fast: 150ms ease;
            --transition-normal: 250ms ease;
            --transition-slow: 350ms ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        nav {
            background-color: var(--bg-card);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 1rem 0;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
            position: relative;
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform var(--transition-normal);
        }

        .logo:hover::after {
            transform: scaleX(1);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: var(--radius-lg);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-normal);
            border: 1px solid transparent;
        }

        .back-button:hover {
            background-color: var(--bg-primary);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Filters Section */
        .filters {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin: 2rem auto;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        select {
            padding: 1rem 2rem 1rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-lg);
            flex: 1;
            min-width: 220px;
            font-size: 1rem;
            color: var(--text-primary);
            background-color: var(--bg-card);
            cursor: pointer;
            transition: all var(--transition-normal);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1.5em;
        }

        select:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }

        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Facilities Grid */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
            position: relative;
            z-index: 80;
        }

        .facility-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-normal);
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .facility-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: var(--shadow-xl);
        }

        .facility-image {
            width: 100%;
            height: 260px;
            object-fit: cover;
            transition: transform var(--transition-slow);
        }

        .facility-card:hover .facility-image {
            transform: scale(1.05);
        }

        .facility-info {
            padding: 2rem;
            position: relative;
        }

        .facility-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.3;
            position: relative;
            padding-bottom: 1rem;
        }

        .facility-name::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--gradient-primary);
            border-radius: var(--radius-sm);
        }

        .facility-location {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 1rem;
            padding: 0.5rem 0;
        }

        .facility-location i {
            color: var(--primary-color);
        }

        .facility-sports {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin: 1.5rem 0;
        }

        .sport-tag {
            background: #eff6ff;
            color: var(--primary-color);
            padding: 0.5rem 1.25rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 500;
            transition: all var(--transition-normal);
            border: 1px solid transparent;
        }

        .sport-tag:hover {
            background: #dbeafe;
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .contact-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.95rem;
            color: var(--text-secondary);
            display: grid;
            gap: 1rem;
        }

        .contact-info div {
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all var(--transition-normal);
        }

        .contact-info div:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .contact-info i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .wilaya-section {
    position: relative;
    margin-top: 4rem;
}

.wilaya-title {
    font-size: 2.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    padding: 1rem 0;
    border-bottom: 3px solid #e2e8f0;
    position: sticky;
    top: 80px; /* Adjust this value based on your nav height */
    background: var(--bg-primary);
    z-index: 90;
    margin-bottom: 2.5rem;
}

.wilaya-title::after {
    content: '';
    position: absolute;
    bottom: -3px;
    left: 0;
    width: 120px;
    height: 3px;
    background: var(--gradient-primary);
}
.wilaya-title {
    transition: box-shadow var(--transition-normal);
}

/* Add shadow when scrolled */
.wilaya-title.scrolled {
    box-shadow: var(--shadow-sm);
}

/* Optional: Add some spacing between sections */
.wilaya-section:not(:last-child) {
    margin-bottom: 4rem;
}

        .no-facilities {
            text-align: center;
            padding: 5rem 2rem;
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            color: var(--text-secondary);
            font-size: 1.25rem;
            box-shadow: var(--shadow-lg);
        }

        .no-facilities i {
            font-size: 4rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
            display: block;
        }

        /* View Details Button */
        .view-details-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--gradient-primary);
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            font-weight: 500;
            margin-top: 1.5rem;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
        }

        .view-details-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255,255,255,0.2), rgba(255,255,255,0));
            transform: translateY(-100%);
            transition: transform var(--transition-normal);
        }

        .view-details-btn:hover::before {
            transform: translateY(0);
        }

        .view-details-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .facilities-grid {
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .nav-container {
                padding: 0 1rem;
            }
            
            .facilities-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .filters {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .facility-card {
                margin: 0 1rem;
            }
            
            .wilaya-title {
                font-size: 1.75rem;
                margin: 2rem 1rem 1.5rem;
            }

            .facility-image {
                height: 220px;
            }

            .facility-info {
                padding: 1.5rem;
            }
            .wilaya-title {
            font-size: 1.75rem;
            top: 72px; /* Adjust for smaller nav on mobile */
            padding: 0.75rem 1rem;
           }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .facility-card {
            animation: fadeIn 0.5s ease forwards;
        }

        .facility-card:nth-child(2) { animation-delay: 0.1s; }
        .facility-card:nth-child(3) { animation-delay: 0.2s; }
        .facility-card:nth-child(4) { animation-delay: 0.3s; }
    </style>
</head>
<body>
    <nav>
    <a href="intro.php" class="btn btn-secondary back-button">
    <i class="fas fa-arrow-left me-2"></i> Back to home
     </a>
        <div class="container">
            <a href="#" class="logo">SportsConnect</a>
        </div>
    </nav>

    <div class="container">
        <div class="filters">
            <form method="GET" class="filter-group">
                <select name="wilaya" onchange="this.form.submit()">
                    <option value="">All Wilayas</option>
                    <?php foreach ($wilayas as $wilaya): ?>
                        <option value="<?php echo $wilaya['id']; ?>" 
                                <?php echo $selected_wilaya == $wilaya['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($wilaya['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sport" onchange="this.form.submit()">
                    <option value="">All Sports</option>
                    <?php foreach ($sports as $sport): ?>
                        <option value="<?php echo $sport['id']; ?>"
                                <?php echo $selected_sport == $sport['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selected_wilaya || $selected_sport): ?>
            <div class="facilities-grid">
                <?php
                $facilities = getFacilities($conn, $selected_wilaya, $selected_sport);
                if (empty($facilities)): ?>
                    <div class="no-facilities">
                        <i class="fas fa-search" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <p>No facilities found matching your criteria.</p>
                    </div>
                <?php else:
                    foreach ($facilities as $facility):
                        $images = explode(',', $facility['images']);
                        $sports = explode(',', $facility['sports']);
                ?>
                    <a href="facility-info.php?id=<?php echo $facility['id']; ?>" class="facility-card">
                        <img src="<?php echo htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($facility['company_name']); ?>" class="facility-image">
                        <div class="facility-info">
                            <div class="facility-name"><?php echo htmlspecialchars($facility['company_name']); ?></div>
                            <div class="facility-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($facility['wilaya_name']); ?>
                            </div>
                            <div class="facility-sports">
                                <?php foreach (array_slice($sports, 0, 3) as $sport): ?>
                                    <span class="sport-tag"><?php echo htmlspecialchars($sport); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($sports) > 3): ?>
                                    <span class="sport-tag">+<?php echo (count($sports) - 3); ?> more</span>
                                <?php endif; ?>
                            </div>
                            <div class="contact-info">
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($facility['phone']); ?></div>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($facility['email']); ?></div>
                            </div>
                        </div>
                    </a>
                <?php
                    endforeach;
                endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($wilayas as $wilaya): 
                $facilities = getFacilities($conn, $wilaya['id']);
                if (!empty($facilities)):
            ?>
                <div class="wilaya-section">
                    <h2 class="wilaya-title"><?php echo htmlspecialchars($wilaya['name']); ?></h2>
                    <div class="facilities-grid">
                        <?php foreach ($facilities as $facility):
                            $images = explode(',', $facility['images']);
                            $sports = explode(',', $facility['sports']);
                        ?>
                            <a href="facility-info.php?id=<?php echo $facility['id']; ?>" class="facility-card">
                                <img src="<?php echo htmlspecialchars($images[0]); ?>" alt="<?php echo htmlspecialchars($facility['company_name']); ?>" class="facility-image">
                                <div class="facility-info">
                                    <div class="facility-name"><?php echo htmlspecialchars($facility['company_name']); ?></div>
                                    <div class="facility-sports">
                                        <?php foreach (array_slice($sports, 0, 3) as $sport): ?>
                                            <span class="sport-tag"><?php echo htmlspecialchars($sport); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($sports) > 3): ?>
                                            <span class="sport-tag">+<?php echo (count($sports) - 3); ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="contact-info">
                                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($facility['address']); ?></div>
                                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($facility['phone']); ?></div>
                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($facility['email']); ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php 
                endif;
            endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
document.addEventListener('scroll', function() {
    const titles = document.querySelectorAll('.wilaya-title');
    titles.forEach(title => {
        if (window.scrollY > 100) {
            title.classList.add('scrolled');
        } else {
            title.classList.remove('scrolled');
        }
    });
});
</script>
</body>
</html>