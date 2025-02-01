<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add a new category
if (isset($_POST['add'])) {
    $category = $_POST['category'];

    if (!empty($category)) {
        $sql = "INSERT INTO sportcategorie (name) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $category);
        if ($stmt->execute()) {
            $message = ["type" => "success", "text" => "Category added successfully."];
        } else {
            $message = ["type" => "error", "text" => "Error adding category: " . $conn->error];
        }
        $stmt->close();
    } else {
        $message = ["type" => "error", "text" => "Please enter a category name."];
    }
}

// Retrieve category for modification
$category_name = "";
if (isset($_POST['fetch'])) {
    $category_id = $_POST['category_id'];

    if (!empty($category_id)) {
        $sql = "SELECT name FROM sportcategorie WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $stmt->bind_result($category_name);
        $stmt->fetch();
        $stmt->close();

        if (empty($category_name)) {
            $message = ["type" => "error", "text" => "No category found with the provided ID."];
        }
    } else {
        $message = ["type" => "error", "text" => "Please provide a category ID."];
    }
}

// Modify a category
if (isset($_POST['modify'])) {
    $category_id = $_POST['category_id'];
    $new_name = $_POST['new_name'];

    if (!empty($category_id) && !empty($new_name)) {
        $sql = "UPDATE sportcategorie SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_name, $category_id);
        if ($stmt->execute()) {
            $message = ["type" => "success", "text" => "Category modified successfully."];
        } else {
            $message = ["type" => "error", "text" => "Error modifying category: " . $conn->error];
        }
        $stmt->close();
    } else {
        $message = ["type" => "error", "text" => "Please provide both category ID and new name."];
    }
}

// Delete a category
if (isset($_POST['delete'])) {
    $category_id = $_POST['category_id'];

    if (!empty($category_id)) {
        $sql = "DELETE FROM sportcategorie WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $message = ["type" => "success", "text" => "Category deleted successfully."];
        } else {
            $message = ["type" => "error", "text" => "Error deleting category: " . $conn->error];
        }
        $stmt->close();
    } else {
        $message = ["type" => "error", "text" => "Please provide a category ID."];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports Category Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a90e2;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        h1 {
            text-align: center;
            color: var(--dark-color);
            margin-bottom: 2rem;
            font-size: 2.5rem;
            position: relative;
            padding-bottom: 1rem;
        }

        h1::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.2rem;
            color: var(--dark-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        button {
            background: var(--primary-color);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }

        button:hover {
            background: #357abd;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .icon {
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sports Categories</h1>

        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-plus-circle icon"></i>
                Add New Category
            </h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="category">Category Name:</label>
                    <input type="text" name="category" id="category" required>
                </div>
                <button type="submit" name="add">
                    <i class="fas fa-plus"></i>
                    Add Category
                </button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-edit icon"></i>
                Modify Category
            </h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="category_id">Category ID:</label>
                    <input type="number" name="category_id" id="category_id" value="<?php echo isset($_POST['category_id']) ? htmlspecialchars($_POST['category_id']) : ''; ?>" required>
                </div>
                <button type="submit" name="fetch">
                    <i class="fas fa-search"></i>
                    Fetch Category
                </button>
            </form>

            <?php if (!empty($category_name)): ?>
            <form method="POST" action="" class="mt-4">
                <div class="form-group">
                    <label>Current Name: <?php echo htmlspecialchars($category_name); ?></label>
                    <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($_POST['category_id']); ?>">
                    <input type="text" name="new_name" placeholder="Enter new name" required>
                </div>
                <button type="submit" name="modify">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="card-title">
                <i class="fas fa-trash-alt icon"></i>
                Delete Category
            </h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="category_id">Category ID:</label>
                    <input type="number" name="category_id" id="category_id" required>
                </div>
                <button type="submit" name="delete" style="background: var(--error-color);">
                    <i class="fas fa-trash-alt"></i>
                    Delete Category
                </button>
            </form>
        </div>
    </div>
</body>
</html>