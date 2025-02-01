<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Verify user
    $stmt = $conn->prepare("SELECT id FROM users LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
    } else {
        die("No user found. Please log in.");
    }

    // Fetch facility details
    $facility_id = isset($_GET['facility_id']) ? intval($_GET['facility_id']) : 0;
    $stmt = $conn->prepare("SELECT f.*, w.name as wilaya_name FROM facilities f JOIN wilaya w ON f.wilaya_id = w.id WHERE f.id = ?");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $facility = $stmt->get_result()->fetch_assoc();

    // Add this after your existing facility query
    $stmt = $conn->prepare("
        SELECT fs.*, s.name as sport_name 
        FROM facility_sports fs
        JOIN sport s ON fs.sport_id = s.id
        WHERE fs.facility_id = ?
    ");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $available_sports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Prevent duplicate submission using POST-REDIRECT-GET pattern
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check if a submission is already processed
        if (!isset($_SESSION['last_form_data']) || 
            $_SESSION['last_form_data'] !== $_POST) {
            
            $check_in_date = $_POST['check_in_date'];
            $check_out_date = $_POST['check_out_date'];
            $arrival_time = $_POST['arrival_time'];
            $departure_time = $_POST['departure_time'];
            $sport_id = $_POST['sport_id'];
            
            // Insert the new booking with sport and departure time
            $stmt = $conn->prepare("
                INSERT INTO reservations 
                (facility_id, user_id, sport_id, check_in_date, check_out_date, 
                 check_in_time, check_out_time, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
            ");
            $stmt->bind_param("iiissss", $facility_id, $user_id, $sport_id, 
                             $check_in_date, $check_out_date, $arrival_time, 
                             $departure_time);
            
            if ($stmt->execute()) {
                // Store form data in session to prevent resubmission
                $_SESSION['last_form_data'] = $_POST;
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?facility_id=" . $facility_id);
                exit();
            } else {
                $error = "An error occurred while making the reservation.";
            }
        }
    }

    // Update the query for fetching user's reservations to include sport information
    $stmt = $conn->prepare("
        SELECT r.*, s.name as sport_name 
        FROM reservations r
        LEFT JOIN sport s ON r.sport_id = s.id
        WHERE r.facility_id = ? AND r.user_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("ii", $facility_id, $user_id);
    $stmt->execute();
    $user_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get booked dates
    $stmt = $conn->prepare("SELECT check_in_date, check_out_date FROM reservations WHERE facility_id = ? AND status = 'approved'");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $existing_bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Reservation - <?php echo htmlspecialchars($facility['company_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #2563eb;
            --light-blue: #3b82f6;
            --lighter-blue: #60a5fa;
            --accent-blue: #1d4ed8;
            --white: #ffffff;
            --off-white: #f8fafc;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--white), var(--gray-50));
            color: #1e293b;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .booking-container {
            background: var(--white);
            border-radius: 24px;
            padding: 2.5rem;
            margin: 3rem auto;
            max-width: 800px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.6s ease-out;
            border: 1px solid var(--gray-200);
        }

        .glass-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            animation: scaleIn 0.5s ease-out;
        }

        .glass-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.1);
        }

        h2 {
    background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
    background-clip: text;
    -moz-background-clip: text;
    -webkit-text-fill-color: transparent; /* For older Safari browsers */
    color: transparent; /* Fallback for unsupported browsers */
    font-weight: 800;
    font-size: 2.5rem;
    margin-bottom: 2rem;
    text-align: center;
    letter-spacing: -0.025em;
    animation: slideIn 0.5s ease-out;
     }
        .form-control {
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            color: #1e293b;
            padding: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
        }

        .form-control:focus {
            background: var(--white);
            border-color: var(--light-blue);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
        }

        .form-label {
            color: #475569;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue), var(--light-blue));
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 50%);
            transform: scale(0);
            transition: transform 0.6s;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover::after {
            transform: scale(1);
        }

        .reservation-history {
            margin-top: 3rem;
            animation: fadeIn 0.8s ease-out;
        }

        .reservation-card {
            background: var(--white);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid transparent;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
        }

        .reservation-card:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .reservation-card.pending {
            border-left-color: var(--warning);
        }

        .reservation-card.approved {
            border-left-color: var(--success);
        }

        .reservation-card.rejected {
            border-left-color: var(--danger);
        }

        .badge {
            padding: 0.75rem 1.25rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .badge:hover {
            transform: scale(1.05);
        }

        #days-result {
            background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
            font-size: 1.25rem;
            color: var(--primary-blue);
            margin: 1.5rem 0;
            border: 2px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        #days-result:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(37, 99, 235, 0.1);
        }

        .flatpickr-calendar {
            background: var(--white);
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border-radius: 16px;
            padding: 1rem;
            animation: scaleIn 0.3s ease-out;
        }

        .flatpickr-day {
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .flatpickr-day.selected {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
        }

        .flatpickr-day:hover {
            background: var(--lighter-blue);
            color: var(--white);
        }

        @media (max-width: 768px) {
            .booking-container {
                margin: 1rem;
                padding: 1.5rem;
            }

            h2 {
                font-size: 2rem;
            }
        }

        /* Custom Animations */
        .fade-up {
            animation: fadeIn 0.6s ease-out;
        }

        .slide-in {
            animation: slideIn 0.5s ease-out;
        }

        .scale-in {
            animation: scaleIn 0.4s ease-out;
        }
        /* Enhanced Back Button Container */
/* Static Back Button Container */
.back-button-container {
    position: absolute;
    top: 1rem;
    left: 1rem;
    z-index: 1000;
    width: fit-content;
}

/* Simplified Back Button Styling */
.back-button {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #2563eb;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.back-button:hover {
    background: #f8fafc;
    color: #1d4ed8;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.08);
}

/* Icon Animation */
/* Simple Icon Styling */
.back-button i {
    font-size: 0.9rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .back-button-container {
        top: 0.75rem;
        left: 0.75rem;
    }
}

.back-button:hover i {
    transform: translateX(-4px);
}

/* Underline Animation */
.back-button-text {
    position: relative;
}

.back-button-text::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    background: #2563eb;
    transform: scaleX(0);
    transform-origin: right;
    transition: transform 0.3s ease;
}

.back-button:hover .back-button-text::after {
    transform: scaleX(1);
    transform-origin: left;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .back-button-container {
        position: absolute;
        top: 1rem;
        left: 1rem;
        right: 1rem;
    }
    
    .back-button {
        width: fit-content;
    }
}

/* Initial Animation */
@keyframes slideFromLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.back-button {
    animation: slideFromLeft 0.5s ease-out;
}
    </style>
</head>
<body>
<div class="back-button-container">
    <a href="facility-user.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span class="back-button-text">Back to Home</span>
    </a>
</div>
<div class="booking-container mx-auto">
    <h2 class="text-center">
        <i class="fas fa-calendar-alt me-2"></i>
        Book <?php echo htmlspecialchars($facility['company_name']); ?>
    </h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <form id="booking-form" method="POST">
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-calendar me-2"></i>Select Reservation Dates
            </label>
            <input type="text" id="date-range" class="form-control" placeholder="Click to select dates" readonly>
            <input type="hidden" name="check_in_date" id="check_in_date">
            <input type="hidden" name="check_out_date" id="check_out_date">
        </div>
        
        <div class="mb-3">
            <label class="form-label">
                <i class="fas fa-clock me-2"></i>Estimated Arrival Time
            </label>
            <input type="time" name="arrival_time" class="form-control" required>
        </div>
        
        <div class="glass-card">
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-running me-2"></i>Select Sport
                </label>
                <select name="sport_id" class="form-control" required>
                    <option value="">Choose a sport...</option>
                    <?php foreach ($available_sports as $sport): ?>
                        <option value="<?php echo htmlspecialchars($sport['sport_id']); ?>">
                            <?php echo htmlspecialchars($sport['sport_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-clock me-2"></i>Departure Time
                </label>
                <input type="time" name="departure_time" class="form-control" required>
            </div>
        </div>
        
        <div id="days-result" class="mb-3">
            <i class="fas fa-info-circle me-2"></i>
            You will stay for <strong id="days-count"></strong> day(s)
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mt-3 d-flex align-items-center justify-content-center">
            <i class="fas fa-paper-plane me-2"></i>Submit Reservation Request
        </button>
    </form>
           <div class="reservation-history">
                <h3>
                    <i class="fas fa-history me-2"></i>Your Reservation History
                </h3>
                <?php foreach ($user_reservations as $reservation): ?>
    <div class="reservation-card <?php echo htmlspecialchars($reservation['status']); ?>">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="mb-2">
                    <i class="fas fa-calendar-check me-2"></i>
                    <strong>Dates:</strong> 
                    <?php echo htmlspecialchars($reservation['check_in_date']); ?> 
                    to 
                    <?php echo htmlspecialchars($reservation['check_out_date']); ?>
                </div>
                <div class="mb-2">
                    <i class="fas fa-clock me-2"></i>
                    <strong>Time:</strong>
                    <?php echo htmlspecialchars(date('h:i A', strtotime($reservation['check_in_time']))); ?> 
                    to 
                    <?php echo htmlspecialchars(date('h:i A', strtotime($reservation['check_out_time']))); ?>
                </div>
                <div>
                    <i class="fas fa-running me-2"></i>
                    <strong>Sport:</strong>
                    <?php echo htmlspecialchars($reservation['sport_name']); ?>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <span class="badge 
                    <?php 
                    switch($reservation['status']) {
                        case 'pending': echo 'bg-warning'; break;
                        case 'approved': echo 'bg-success'; break;
                        case 'rejected': echo 'bg-danger'; break;
                        default: echo 'bg-secondary';
                    }
                    ?>">
                    <?php echo ucfirst(htmlspecialchars($reservation['status'])); ?>
                </span>
            </div>
        </div>
    </div>
<?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.02)';
                });
                control.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        // Booked dates from PHP
        const bookedDates = <?php echo json_encode(array_map(function($booking) {
            return [
                'from' => $booking['check_in_date'],
                'to' => $booking['check_out_date']
            ];
        }, $existing_bookings)); ?>;

        // Prepare disabled dates
        const disabledDates = bookedDates.flatMap(booking => {
            const dates = [];
            let current = new Date(booking.from);
            const end = new Date(booking.to);
            
            while (current <= end) {
                dates.push(current.toISOString().split('T')[0]);
                current.setDate(current.getDate() + 1);
            }
            return dates;
        });

        // Initialize date range picker
        const picker = flatpickr("#date-range", {
            mode: "range",
            minDate: "today",
            disable: disabledDates,
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    const start = selectedDates[0];
                    const end = selectedDates[1];
                    
                    // Set hidden inputs
                    document.getElementById('check_in_date').value = 
                        start.toISOString().split('T')[0];
                    document.getElementById('check_out_date').value = 
                        end.toISOString().split('T')[0];
                    
                    // Calculate and show days
                    const days = Math.round((end - start) / (1000 * 60 * 60 * 24));
                    document.getElementById('days-count').textContent = days;
                    document.getElementById('days-result').style.display = 'block';
                }
            }
        });
    });
    </script>
</body>
</html>