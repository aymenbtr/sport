<?php
session_name('user_session');
session_start();

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to access this page.");
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'test');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Move session messages to variables and clear them
$errors = $_SESSION['form_errors'] ?? [];
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['form_errors'], $_SESSION['success_message']);

// Fetch available sports
$user_id = $_SESSION['user_id'];
$sports_query = "
    SELECT s.id, s.name
    FROM sport s
    JOIN user_sports us ON s.id = us.sport_id
    WHERE us.user_id = ?
";
$sports_stmt = $conn->prepare($sports_query);
$sports_stmt->bind_param("i", $user_id);
$sports_stmt->execute();
$sports_result = $sports_stmt->get_result();
$available_sports = [];
while ($row = $sports_result->fetch_assoc()) {
    $available_sports[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect_params = [];
    
    if (isset($_POST['action'])) {
        // Handle status updates
        if ($_POST['action'] === 'update_status') {
            $child_id = $_POST['child_id'];
            $new_status = $_POST['status'];
            
            $update_stmt = $conn->prepare("UPDATE children SET status = ? WHERE id = ? AND user_id = ?");
            $update_stmt->bind_param("sii", $new_status, $child_id, $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['success_message'] = "Status updated successfully.";
            } else {
                $_SESSION['form_errors'][] = "Failed to update status.";
            }
        }
        // Handle deletion
        elseif ($_POST['action'] === 'delete') {
            $child_id = $_POST['child_id'];
            
            $delete_stmt = $conn->prepare("DELETE FROM children WHERE id = ? AND user_id = ?");
            $delete_stmt->bind_param("ii", $child_id, $user_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['success_message'] = "Child record deleted successfully.";
            } else {
                $_SESSION['form_errors'][] = "Failed to delete record.";
            }
        }
    } else {
        // Handle new child submission
        try {
            $conn->begin_transaction();

            if (empty($_POST['child_first_name'])) {
                throw new Exception("Please add at least one child.");
            }

            $stmt = $conn->prepare("
                INSERT INTO children 
                (user_id, first_name, last_name, dob, gender, medical_notes, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");

            $sports_stmt = $conn->prepare("
                INSERT INTO child_sports (child_id, sport_id) 
                VALUES (?, ?)
            ");

            foreach ($_POST['child_first_name'] as $i => $first_name) {
                if (empty($first_name) || empty($_POST['child_last_name'][$i])) {
                    continue;
                }

                $dob = $_POST['child_dob'][$i];
                $medical_notes = $_POST['child_medical_notes'][$i] ?? null;
                
                $stmt->bind_param("isssss", 
                    $user_id,
                    $first_name,
                    $_POST['child_last_name'][$i],
                    $dob,
                    $_POST['child_gender'][$i],
                    $medical_notes
                );

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert child: " . $stmt->error);
                }

                $child_id = $conn->insert_id;

                if (isset($_POST['child_sports'][$i])) {
                    foreach ($_POST['child_sports'][$i] as $sport_id) {
                        $sports_stmt->bind_param("ii", $child_id, $sport_id);
                        if (!$sports_stmt->execute()) {
                            throw new Exception("Failed to insert sports");
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success_message'] = "Your registration has been submitted and is pending approval. Thank you for waiting.";

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['form_errors'][] = $e->getMessage();
        }
    }
    
    // Redirect after processing
    header("Location: " . $_SERVER['PHP_SELF'] . (!empty($redirect_params) ? '?' . http_build_query($redirect_params) : ''));
    exit();
}

// Fetch existing children with status
$children_query = "
    SELECT c.*, 
    GROUP_CONCAT(DISTINCT s.name) as sports 
    FROM children c
    LEFT JOIN child_sports cs ON c.id = cs.child_id
    LEFT JOIN sport s ON cs.sport_id = s.id
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
";
$stmt = $conn->prepare($children_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$existing_children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Your Kids - SportsConnect</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* Modern Reset and Base Styles */
    body {
        font-family: 'Plus Jakarta Sans', sans-serif;
        line-height: 1.6;
        margin: 0;
        min-height: 100vh;
        background: linear-gradient(135deg, #f6f8fd 0%, #f1f4f9 100%);
        color: #1a2b4b;
        padding: 1.5rem;
    }

    .container {
        max-width: 800px;
        margin: 1rem auto;
        background: rgba(255, 255, 255, 0.98);
        padding: 2rem;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05),
                    0 1px 3px rgba(0, 0, 0, 0.05);
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
    }

    .container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #4f46e5, #06b6d4);
    }

    /* Enhanced Typography */
    h1 {
        color: #1e293b;
        font-size: 2.25rem;
        margin-bottom: 2rem;
        font-weight: 700;
        text-align: center;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
        letter-spacing: -0.5px;
        position: relative;
    }

    h1::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 2px;
        background: linear-gradient(90deg, #4f46e5, #06b6d4);
    }

    h2 {
        color: #334155;
        font-size: 1.5rem;
        margin: 2rem 0 1.5rem;
        font-weight: 600;
        letter-spacing: -0.3px;
    }

    /* Redesigned Form Grid */
    /* Form Grid Layout */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;  /* Two equal columns */
    gap: 1.25rem;
    margin-bottom: 1.5rem;
    width: 100%;
}

@media (max-width: 640px) {
    .form-grid {
        grid-template-columns: 1fr;  /* Single column on mobile */
    }
    
    .form-control {
        width: 100%;
    }
    
    .container {
        padding: 1rem;
    }
}

    /* Enhanced Form Controls */
    .form-group {
    margin-bottom: 1.25rem;
    width: 100%;
}

/* Full Width Fields */
.form-group.full-width {
    grid-column: 1 / -1;  /* Span all columns */
}
textarea.form-control {
    width: 100%;
    min-height: 100px;
    resize: vertical;
}

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #334155;
        font-size: 0.95rem;
        transition: color 0.3s ease;
    }

    .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    background: #f8fafc;
    box-sizing: border-box;
}

    .form-control:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        outline: none;
        background: #ffffff;
    }

    .form-group:focus-within label {
        color: #4f46e5;
    }

    /* Animated Child Sections */
    .child-form-section {
    background: #ffffff;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    width: 100%;
    box-sizing: border-box;
}

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Enhanced Sports Checkbox Group */
    .sports-checkbox-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding: 1.25rem;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        background: #ffffff;
        border-radius: 99px;
        border: 2px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;
        font-size: 0.9rem;
    }

    .checkbox-label:hover {
        background: #f1f5f9;
        border-color: #4f46e5;
        transform: translateY(-2px);
    }

    /* Existing Children Cards */
    .existing-children-section {
        margin: 2rem 0;
        padding: 1.5rem;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(148, 163, 184, 0.1);
    }

    .existing-child {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .existing-child:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.06);
    }

    /* Compact Child Info Layout */
    .child-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
    }

    .info-item {
        background: #f8fafc;
        padding: 0.875rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .info-item:hover {
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* Enhanced Buttons */
    .btn {
        padding: 0.75rem 1.25rem;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        letter-spacing: -0.01em;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: white;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 12px -1px rgba(79, 70, 229, 0.3);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }

    /* Enhanced Status Badges */
    .status-badge {
        padding: 0.4rem 0.875rem;
        border-radius: 99px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    /* Decorative Elements */
    .decoration-dot {
        position: absolute;
        border-radius: 50%;
        background: linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%);
        opacity: 0.1;
    }

    .decoration-dot-1 {
        width: 150px;
        height: 150px;
        top: -75px;
        right: -75px;
    }

    .decoration-dot-2 {
        width: 100px;
        height: 100px;
        bottom: -50px;
        left: -50px;
    }

    /* Loading Animation */
    @keyframes shimmer {
        0% {
            background-position: -468px 0;
        }
        100% {
            background-position: 468px 0;
        }
    }

    .loading {
        animation: shimmer 1s linear infinite;
        background: linear-gradient(to right, #f6f7f8 8%, #edeef1 18%, #f6f7f8 33%);
        background-size: 800px 104px;
    }
    .form-group:has(textarea),
     .form-group:has(.sports-checkbox-group) {
    grid-column: 1 / -1;
}
</style>
</head>
<body>
<button type="button" class="btn btn-secondary" onclick="window.location.href='facility-user.php';">
    <i class="fas fa-arrow-left"></i> Back
</button>
<div class="container">
        <h1>Add Your Children</h1>

        <?php if (!empty($errors)): ?>
            <div class="form-error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="form-success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Existing Children Section -->
        <?php if (!empty($existing_children)): ?>
            <div class="existing-children-section">
                <h2>Your Registered Children</h2>
                <?php foreach ($existing_children as $child): ?>
                    <div class="existing-child">
                        <div class="child-header">
                            <h3><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h3>
                            <span class="status-badge status-<?php echo $child['status']; ?>">
                                <?php echo ucfirst(htmlspecialchars($child['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="child-info">
                            <div class="info-item">
                                <div class="info-label">Age</div>
                                <?php 
                                $dob = new DateTime($child['dob']);
                                echo date_diff($dob, date_create('today'))->y; 
                                ?> years
                            </div>
                            
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <?php echo ucfirst(htmlspecialchars($child['gender'])); ?>
                            </div>
                            
                            <?php if (!empty($child['medical_notes'])): ?>
                            <div class="info-item">
                                <div class="info-label">Medical Notes</div>
                                <?php echo htmlspecialchars($child['medical_notes']); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($child['sports'])): ?>
                        <div class="info-item">
                            <div class="info-label">Sports</div>
                            <?php foreach (explode(',', $child['sports']) as $sport): ?>
                                <span class="child-sports-tag"><?php echo htmlspecialchars($sport); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($_SESSION['is_admin'] ?? false): ?>
                        <div class="status-controls">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
                                <button type="submit" name="status" value="approved" class="btn btn-primary">Approve</button>
                                <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="child_id" value="<?php echo $child['id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <!-- Add Children Form -->
        <form method="POST" id="childrenForm" onsubmit="return validateChildrenForm()">
            <div id="children-container">
                <div class="child-form-section">
                    <h2>Add New Child</h2>
                    <div class="child-input-group">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="child_first_name[]" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="child_last_name[]" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date of Birth *</label>
                                <input type="date" name="child_dob[]" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="child_gender[]" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Medical Notes (Optional)</label>
                            <textarea name="child_medical_notes[]" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Sport Interests</label>
                            <div class="sports-checkbox-group">
                                <?php foreach ($available_sports as $sport): ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" 
                                               name="child_sports[0][]" 
                                               value="<?php echo $sport['id']; ?>">
                                        <?php echo htmlspecialchars($sport['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" id="add-child-btn" class="btn btn-secondary">
                    <i class="fas fa-plus"></i> Add Another Child
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Children
                </button>
            </div>
        </form>
    </div>

    <style>
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-control {
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .child-form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .remove-child-btn {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .child-form-section {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addChildBtn = document.getElementById('add-child-btn');
            const childrenContainer = document.getElementById('children-container');
            let childIndex = 1;

            addChildBtn.addEventListener('click', function() {
                const newChildSection = document.createElement('div');
                newChildSection.classList.add('child-form-section');
                newChildSection.style.position = 'relative';

                const sportOptions = `
                    <?php foreach ($available_sports as $sport): ?>
                        <label class="checkbox-label">
                            <input type="checkbox" 
                                   name="child_sports[${childIndex}][]" 
                                   value="<?php echo $sport['id']; ?>">
                            <?php echo htmlspecialchars($sport['name']); ?>
                        </label>
                    <?php endforeach; ?>
                `;

                newChildSection.innerHTML = `
                    <h2>Child ${childIndex + 1}</h2>
                    <button type="button" class="btn btn-danger remove-child-btn">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="child-input-group">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="child_first_name[]" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="child_last_name[]" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Date of Birth *</label>
                                <input type="date" name="child_dob[]" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Gender *</label>
                                <select name="child_gender[]" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Medical Notes (Optional)</label>
                            <textarea name="child_medical_notes[]" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Sport Interests</label>
                            <div class="sports-checkbox-group">
                                ${sportOptions}
                            </div>
                        </div>
                    </div>
                `;

                childrenContainer.appendChild(newChildSection);
                
                const removeBtn = newChildSection.querySelector('.remove-child-btn');
                removeBtn.addEventListener('click', function() {
                    newChildSection.remove();
                });

                childIndex++;
            });

            window.validateChildrenForm = function() {
                const forms = document.querySelectorAll('.child-input-group');
                let isValid = true;
                let errorMessage = '';

                forms.forEach((form, index) => {
                    const firstName = form.querySelector('input[name="child_first_name[]"]').value;
                    const lastName = form.querySelector('input[name="child_last_name[]"]').value;
                    const dob = form.querySelector('input[name="child_dob[]"]').value;
                    const gender = form.querySelector('select[name="child_gender[]"]').value;

                    if (!firstName || !lastName || !dob || !gender) {
                        errorMessage = `Please fill in all required fields for Child ${index + 1}`;
                        isValid = false;
                    }

                    // Validate date of birth
                    if (dob) {
                        const dobDate = new Date(dob);
                        const today = new Date();
                        const age = Math.floor((today - dobDate) / (365.25 * 24 * 60 * 60 * 1000));
                        
                        if (age < 0 || age > 18) {
                            errorMessage = `Child ${index + 1}: Age must be between 0 and 18 years`;
                            isValid = false;
                        }
                    }
                });

                if (!isValid) {
                    alert(errorMessage);
                    return false;
                }

                return true;
            };
        });
    </script>
</body>
</html>
