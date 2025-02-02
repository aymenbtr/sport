<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$response = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $photo = $_FILES['photo']['name'];
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["photo"]["name"]);

    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
        $sql = "INSERT INTO wilaya (id, name, photo) VALUES ('$id', '$name', '$photo')";
        if ($conn->query($sql) === TRUE) {
            $response['message'] = "New wilaya added successfully!";
            $response['success'] = true;
        } else {
            $response['message'] = "Error: " . $sql . "<br>" . $conn->error;
            $response['success'] = false;
        }
    } else {
        $response['message'] = "Sorry, there was an error uploading your file.";
        $response['success'] = false;
    }
} else {
    $sql = "SELECT id, name, photo FROM wilaya ORDER BY id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>