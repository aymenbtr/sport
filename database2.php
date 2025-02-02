<?php
header('Content-Type: application/json');
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Handle file upload and form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = $_POST['name'];
    
    if (isset($_FILES['photo'])) {
        $photo = $_FILES['photo']['name'];
        $target_dir = "uploads/";
        $target_file = $target_dir . basename($_FILES["photo"]["name"]);

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO wilaya (id, name, photo) VALUES ('$id', '$name', '$photo')";
            if ($conn->query($sql) === TRUE) {
                echo json_encode(["success" => "New wilaya added successfully!"]);
            } else {
                echo json_encode(["error" => "Error: " . $sql . "<br>" . $conn->error]);
            }
        } else {
            echo json_encode(["error" => "Sorry, there was an error uploading your file."]);
        }
    }
    exit;
}

// Handle GET request to fetch wilayas
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $wilayas_sql = "SELECT id, name, photo FROM wilaya ORDER BY id";
    $wilayas_result = $conn->query($wilayas_sql);
    
    $wilayas = [];
    if ($wilayas_result->num_rows > 0) {
        while($row = $wilayas_result->fetch_assoc()) {
            $wilayas[] = $row;
        }
    }
    
    echo json_encode($wilayas);
    exit;
}

$conn->close();
?>