<?php
header('Content-Type: application/json');
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

    // Handle file upload and form submission
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!isset($_POST['id']) || !isset($_POST['name']) || !isset($_FILES['photo'])) {
            throw new Exception("Missing required fields");
        }

        $id = $_POST['id'];
        $name = $_POST['name'];
        $photo = $_FILES['photo']['name'];
        
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $target_file = $target_dir . basename($_FILES["photo"]["name"]);

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO wilaya (id, name, photo) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $id, $name, $photo);
            
            if ($stmt->execute()) {
                echo json_encode(["success" => "New wilaya added successfully!"]);
            } else {
                throw new Exception("Error inserting data: " . $stmt->error);
            }
        } else {
            throw new Exception("Sorry, there was an error uploading your file.");
        }
        exit;
    }

    // Handle GET request to fetch wilayas
    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        $wilayas_sql = "SELECT id, name, photo FROM wilaya ORDER BY id";
        $wilayas_result = $conn->query($wilayas_sql);
        
        if (!$wilayas_result) {
            throw new Exception("Error fetching data: " . $conn->error);
        }
        
        $wilayas = [];
        while($row = $wilayas_result->fetch_assoc()) {
            $wilayas[] = $row;
        }
        
        echo json_encode($wilayas);
        exit;
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
