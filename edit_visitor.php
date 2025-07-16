<?php 
session_start();

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'reception') {
    header('Location: index.php');
    exit();
}

include 'db.php';

$visitor_cin = $_GET['cin'];
$stmtVisitor = $conn->prepare("SELECT * FROM visiteur WHERE cin = ?");
$stmtVisitor->execute([$visitor_cin]);
$visitor = $stmtVisitor->fetch();

$successMessage = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
    if (isset($_POST['update_visitor'])) {
        $visitor_name = trim($_POST['visitor_name']);
        $visitor_phone = trim($_POST['visitor_phone']);
        $visitor_email = trim($_POST['visitor_email']);
        $errorMessage = '';

        if (empty($visitor_name)) {
            $errorMessage = "Name cannot be empty.";
        } 
        else {
            if (!empty($visitor_phone) && !preg_match('/^\d{8}$/', $visitor_phone)) {
                $errorMessage = "Phone number must be 8 digits.";
            }
            elseif (!empty($visitor_email) && !filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email format.";
            } else {
                $stmtUpdate = $conn->prepare("UPDATE visiteur SET nom = ?, telephone = ?, email = ? WHERE cin = ?");
                $stmtUpdate->execute([$visitor_name, $visitor_phone, $visitor_email, $visitor_cin]);

                $successMessage = "Visitor details updated successfully!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Visitor</title>
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
            animation: loopBackground 12.2s linear infinite;
        }

        @keyframes loopBackground {
            0% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }

        .container {
            margin-top: 20px;
        }

        h1, h3 {
            color: white;
        }

        .form-section {
            margin-top: 20px;
        }

        .hidden {
            display: none;
        }

        .form-section table {
            width: 100%;
        }

        .form-section td, .form-section th {
            padding: 8px;
        }

        .alert {
            color: white;
            background-color: #28a745;
        }

        .error {
            color: white;
            background-color: #dc3545;
        }

        .scrollable-table {
            overflow-y: auto;
            max-height: 300px;
        }
        .btn-blue-light {
            background-color: lightblue;
            border-color: lightblue;
            color: black;
        }

        .btn-blue-light:hover {
            background-color: deepskyblue;
            border-color: deepskyblue;
            color: white;
        }
        .form-label{
            color:white;
        }

    </style>
</head>
<body>
    <video id="background-video" autoplay muted loop>
        <source src="v1.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <img src="logo.jpg" alt="Logo" style="height: 50px; margin-right: 10px;">
            <h1 class="text-center">Edit Visitor</h1>
            <a href="reception.php" class="btn btn-blue-light">Dashboard</a>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert error alert-dismissible fade show mt-3" role="alert">
                <?= $errorMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h3>Edit Visitor Information</h3>
            <form method="POST" action="edit_visitor.php?cin=<?= $visitor_cin ?>">
                <div class="mb-3">
                    <label for="visitor_name" class="form-label">Visitor Name</label>
                    <input type="text" name="visitor_name" id="visitor_name" class="form-control" value="<?= htmlspecialchars($visitor['nom']) ?>" required>
                </div>

                <div class="mb-3">
                    <label for="visitor_phone" class="form-label">Visitor Phone</label>
                    <input type="text" name="visitor_phone" id="visitor_phone" class="form-control" value="<?= htmlspecialchars($visitor['telephone']) ?>">
                </div>

                <div class="mb-3">
                    <label for="visitor_email" class="form-label">Visitor Email</label>
                    <input type="email" name="visitor_email" id="visitor_email" class="form-control" value="<?= htmlspecialchars($visitor['email']) ?>">
                </div>

                <button type="submit" name="update_visitor" class="btn btn-primary">Update Visitor</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
