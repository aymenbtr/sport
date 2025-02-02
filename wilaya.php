<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $photo = $_FILES['photo']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["photo"]["name"]);

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        $sql = "INSERT INTO wilaya (id, name, photo) VALUES ('$id', '$name', '$photo')";
        if ($conn->query($sql) === TRUE) {
            $success_message = "New wilaya added successfully!";
        } else {
            $error_message = "Error: " . $sql . "<br>" . $conn->error;
        }
    } else {
        $error_message = "Sorry, there was an error uploading your file.";
    }
}

$wilayas_sql = "SELECT id, name, photo FROM wilaya ORDER BY id";
$wilayas_result = $conn->query($wilayas_sql);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wilaya Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --success-color: #059669;
            --error-color: #dc2626;
            --background-color: #f1f5f9;
            --card-background: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--background-color);
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: var(--card-background);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .form-container {
            background: var(--card-background);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 3rem;
        }

        .form-container h2 {
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        input[type="number"],
        input[type="text"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        input[type="number"]:focus,
        input[type="text"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            margin-top: 0.5rem;
            border: 2px dashed #e2e8f0;
            border-radius: var(--border-radius);
            cursor: pointer;
        }

        .submit-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            width: 100%;
        }

        .submit-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .success {
            background-color: #dcfce7;
            color: var(--success-color);
            border: 1px solid #86efac;
        }

        .error {
            background-color: #fee2e2;
            color: var(--error-color);
            border: 1px solid #fecaca;
        }

        .wilayas-container {
            background: var(--card-background);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .wilayas-container h2 {
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .wilayas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem;
        }

        .wilaya-card {
            background: var(--card-background);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .wilaya-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .wilaya-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }

        .wilaya-info {
            padding: 1.5rem;
        }

        .wilaya-info h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .wilaya-id {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .form-container,
            .wilayas-container {
                padding: 1.5rem;
            }

            .wilayas-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Wilaya Management System</h1>
        </div>

        <?php if ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2>Add New Wilaya</h2>
            <form method="post" enctype="multipart/form-data" action="">
                <div class="form-group">
                    <label for="id">Wilaya ID</label>
                    <input type="number" id="id" name="id" required placeholder="Enter Wilaya ID">
                </div>

                <div class="form-group">
                    <label for="name">Wilaya Name</label>
                    <input type="text" id="name" name="name" required placeholder="Enter Wilaya Name">
                </div>

                <div class="form-group">
                    <label for="photo">Wilaya Photo</label>
                    <input type="file" id="photo" name="photo" required accept="image/*">
                </div>

                <button type="submit" name="submit" class="submit-btn">Add Wilaya</button>
            </form>
        </div>

        <div class="wilayas-container">
            <h2>Existing Wilayas</h2>
            <div class="wilayas-grid">
                <?php
                if ($wilayas_result->num_rows > 0) {
                    while($row = $wilayas_result->fetch_assoc()) {
                        echo "<div class='wilaya-card'>";
                        echo "<img src='uploads/" . htmlspecialchars($row["photo"]) . "' alt='" . htmlspecialchars($row["name"]) . "' class='wilaya-image'>";
                        echo "<div class='wilaya-info'>";
                        echo "<h3>" . htmlspecialchars($row["name"]) . "</h3>";
                        echo "<div class='wilaya-id'>ID: " . htmlspecialchars($row["id"]) . "</div>";
                        echo "</div>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>No wilayas found.</p>";
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>