<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $categorie_id = $_POST['categorie_id'];

    $sql = "INSERT INTO sport (name, description, categorie_id) VALUES ('$name', '$description', '$categorie_id')";
    if ($conn->query($sql) === TRUE) {
        echo "New sport added successfully!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Sport</title>
</head>
<body>
    <h1>Add New Sport</h1>
    <form method="post" action="sport.php">
        <label for="name">Sport Name:</label><br>
        <input type="text" id="name" name="name" required><br><br>
        <label for="description">Description:</label><br>
        <textarea id="description" name="description" required></textarea><br><br>
        <label for="categorie_id">Category:</label><br>
        <select id="categorie_id" name="categorie_id" required>
            <?php
            $sql = "SELECT id, name FROM sportcategorie";
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<option value='" . $row["id"] . "'>" . $row["name"] . "</option>";
                }
            }
            ?>
        </select><br><br>
        <input type="submit" value="Add Sport">
    </form>

    <h2>Existing Sports</h2>
    <?php
    $sql = "SELECT s.id, s.name, s.description, c.name AS category_name
            FROM sport s
            JOIN sportcategorie c ON s.categorie_id = c.id";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<ul>";
        while($row = $result->fetch_assoc()) {
            echo "<li><strong>" . $row["name"] . ":</strong> " . $row["description"] . " (Category: " . $row["category_name"] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "No sports found.";
    }
    $conn->close();
    ?>
</body>
</html>
