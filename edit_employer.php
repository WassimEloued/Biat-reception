<?php
session_start();

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'reception') {
    header('Location: index.php');
    exit();
}

include 'db.php';

$employerCIN = $_GET['cin'] ?? null;

if (!$employerCIN) {
    header('Location: reception.php');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM employer WHERE cin = ?");
$stmt->execute([$employerCIN]);
$employer = $stmt->fetch();

if (!$employer) {
    header('Location: reception.php');
    exit();
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {  
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($name)) {
        $errorMessage = "Name cannot be empty.";
    } 
    elseif (!empty($phone) && !preg_match('/^\d{8}$/', $phone)) {
        $errorMessage = "Phone number must be 8 digits.";
    }
    elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format.";
    }
    elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } else {
        $stmt = $conn->prepare("UPDATE employer SET nom = ?, telephone = ?, email = ?, password = ? WHERE cin = ?");
        $stmt->execute([$name, $phone, $email, $password, $employerCIN]);
        $successMessage = "Direction details updated successfully!";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
        }

        video#background-video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
        }

        .container {
            margin-top: 20px;
        }

        h1 {
            color: white;
        }

        .form-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 15px;
            border-radius: 10px;
            color: white;
        }

        .alert {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <video id="background-video" autoplay muted loop>
        <source src="v1.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="container">
        <h1 class="text-center">Edit Direction</h1>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= $successMessage ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= $errorMessage ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="edit_employer.php?cin=<?= htmlspecialchars($employerCIN) ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($employer['nom']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($employer['telephone']) ?>">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($employer['email']) ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password" class="form-control" value="<?= htmlspecialchars($employer['password']) ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="reception.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
