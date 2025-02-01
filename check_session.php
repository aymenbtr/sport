<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

header('Content-Type: application/json');

if (!isset($_SESSION['facility_id']) || !isset($_SESSION['browser_session_id'])) {
    echo json_encode(['valid' => false, 'message' => 'Session expired']);
    exit();
}

$stmt = $conn->prepare("SELECT active_session_id FROM facilities WHERE id = ? AND is_deleted = 0");
$stmt->bind_param("i", $_SESSION['facility_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['valid' => false, 'message' => 'Facility not found']);
    exit();
}

$facility = $result->fetch_assoc();
if ($facility['active_session_id'] !== $_SESSION['browser_session_id']) {
    echo json_encode(['valid' => false, 'message' => 'Another session is already active']);
    exit();
}

echo json_encode(['valid' => true]);
?>