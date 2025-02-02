<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "test";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Handle POST request (form submission)
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Check if the required fields exist
        if (!isset($_POST['id']) || !isset($_POST['name']) || !isset($_FILES['photo'])) {
            throw new Exception("Missing required fields");
        }

        // Validate and sanitize inputs
        $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new Exception("Invalid ID format");
        }

        $name = trim($_POST['name']);
        if (empty($name)) {
            throw new Exception("Name cannot be empty");
        }

        // Handle file upload
        $photo = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($photo['type'], $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, PNG and GIF are allowed.");
        }

        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Generate unique filename
        $file_extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $unique_filename;

        if (move_uploaded_file($photo['tmp_name'], $target_file)) {
            // Use prepared statement to prevent SQL injection
            $stmt = $conn->prepare("INSERT INTO wilaya (id, name, photo) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $id, $name, $unique_filename);
            
            if ($stmt->execute()) {
                echo json_encode([
                    "success" => "New wilaya added successfully!",
                    "data" => [
                        "id" => $id,
                        "name" => $name,
                        "photo" => $unique_filename
                    ]
                ]);
            } else {
                throw new Exception("Error inserting data: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Failed to upload file");
        }
    }
    // Handle GET request (fetch wilayas)
    else if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $stmt = $conn->prepare("SELECT id, name, photo FROM wilaya ORDER BY id");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $wilayas = [];
        while ($row = $result->fetch_assoc()) {
            $wilayas[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'photo' => $row['photo']
            ];
        }
        
        echo json_encode($wilayas);
        $stmt->close();
    } else {
        throw new Exception("Method not allowed");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>
