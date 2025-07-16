<?php 
session_start();

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'reception') {
    header('Location: index.php');
    exit();
}

include 'db.php';

if (!isset($_GET['visitor_cin']) || empty($_GET['visitor_cin'])) {
    die("Visitor CIN is missing or invalid.");
}

$visitor_cin = $_GET['visitor_cin'];

$stmtVisitor = $conn->prepare("SELECT * FROM visiteur WHERE cin = ?");
$stmtVisitor->execute([$visitor_cin]);
$visitor = $stmtVisitor->fetch(PDO::FETCH_ASSOC);

if (!$visitor) {
    die("Visitor not found.");
}

$employerQuery = "SELECT * FROM employer";
$stmtEmployers = $conn->prepare($employerQuery);
$stmtEmployers->execute();
$employers = $stmtEmployers->fetchAll(PDO::FETCH_ASSOC);

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['submit_visit'])) {
        $badge_id = $_POST['badge_id'];
        $employer_cin = $_POST['employer_cin'];
        $visit_description = $_POST['visit_description'];
        $visit_duration = $_POST['visit_duration'];

        if (empty($badge_id) || empty($employer_cin) || empty($visitor_cin) || empty($visit_description) || empty($visit_duration)) {
            $errorMessage = "Please fill in all fields.";
        } else {
            try {
                // Check if employer is available
                $stmtEmployerCheck = $conn->prepare("SELECT disp FROM employer WHERE cin = ?");
                $stmtEmployerCheck->execute([$employer_cin]);
                $employer = $stmtEmployerCheck->fetch(PDO::FETCH_ASSOC);

                if ($employer && $employer['disp'] === 'non') {
                    $errorMessage = "Direction is not disponible.";
                } else {
                    // Check if badge is available
                    $stmtBadgeCheck = $conn->prepare("SELECT * FROM badge WHERE badge_id = ? AND status = 'Out'");
                    $stmtBadgeCheck->execute([$badge_id]);

                    if ($stmtBadgeCheck->rowCount() === 0) {
                        $errorMessage = "The selected badge is already in use.";
                    } else {
                        // Check if visitor has an ongoing visit
                        $stmtVisitorOngoingCheck = $conn->prepare(
                            "SELECT * FROM visit WHERE visitor_cin = ? AND NOW() < end_time"
                        );
                        $stmtVisitorOngoingCheck->execute([$visitor_cin]);

                        if ($stmtVisitorOngoingCheck->rowCount() > 0) {
                            $errorMessage = "This visitor already has an ongoing visit.";
                        } else {
                            // Check if employer has more than 10 ongoing visits
                            $stmtEmployerOngoingCheck = $conn->prepare(
                                "SELECT * FROM visit WHERE employer_cin = ? AND NOW() < end_time"
                            );
                            $stmtEmployerOngoingCheck->execute([$employer_cin]);

                            if ($stmtEmployerOngoingCheck->rowCount() > 10) {
                                $errorMessage = "This Direction already has 10 ongoing visits. Please wait until it ends.";
                            } else {
                                // Insert visit record
                                $conn->beginTransaction();

                                // Update badge status
                                $updateBadgeQuery = "UPDATE badge SET status = 'In' WHERE badge_id = ?";
                                $stmtUpdateBadge = $conn->prepare($updateBadgeQuery);
                                $stmtUpdateBadge->execute([$badge_id]);

                                // Insert visit
                                $stmtVisit = $conn->prepare(
                                    "INSERT INTO visit (visitor_cin, badge_id, employer_cin, description, duration) 
                                     VALUES (?, ?, ?, ?, ?)"
                                );
                                $stmtVisit->execute([$visitor_cin, $badge_id, $employer_cin, $visit_description, $visit_duration]);

                                $conn->commit();
                                $successMessage = "Visit successfully assigned to the employer.";
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $conn->rollBack();
                if ($e->getCode() == 23000) {
                    $errorMessage = "Duplicate entry detected. Please ensure the visit details are unique.";
                } else {
                    $errorMessage = "An error occurred: " . $e->getMessage();
                }
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
    <title>Visitor Visit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #2C3E50; 
            color: white;
            overflow-x: hidden;
        }
        .table-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 15px;
            border-radius: 10px;
        }
        .table-container table, th, td {
            color: black;
        }
        .navbar{
            background-color:#0089B7;
        }
        .nav-link{
            background-color:#0089B7;
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
        .mb-3{
            background-color: da;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-xl navbar-dark" style="width: 100%; margin: 0;">
    <a class="navbar-brand" href="reception.php" style="display: flex; align-items:center ;padding:8px;">
        <img src="logo.jpg" alt="Logo" style="height: 50px; margin-right: 10px;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="text-center">Visitor Visit</h1>  
        </div>
        <div class="ml-auto">
            <a href="reception.php" class="btn btn-blue-light">Dashboard</a>
        </div>   
    </div>
    </nav>
    <br>
        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                <?= $successMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <?= $errorMessage ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h3>Visitor Information</h3>
            <table class="table table-bordered">
                <tr>
                    <th>CIN</th>
                    <td><?= htmlspecialchars($visitor['cin']) ?></td>
                </tr>
                <tr>
                    <th>Name</th>
                    <td><?= htmlspecialchars($visitor['nom']) ?></td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td><?= htmlspecialchars($visitor['telephone']) ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?= htmlspecialchars($visitor['email']) ?></td>
                </tr>
            </table>
        </div>

        <div class="form-section">
            <h3>Assign Visit to Direction</h3>
            <form method="POST" action="visitor_visit.php?visitor_cin=<?= $visitor_cin ?>">
                <div class="mb-3">
                    <label for="employer_cin" class="form-label">Select Direction</label>
                    <select name="employer_cin" id="employer_cin" class="form-control" required>
                        <option value="">Select Direction</option>
                        <?php foreach ($employers as $employer): ?>
                            <option value="<?= $employer['cin'] ?>"><?= htmlspecialchars($employer['nom']) ?> (<?= htmlspecialchars($employer['cin']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="badge_id" class="form-label">Select Badge</label>
                    <select name="badge_id" id="badge_id" class="form-control" required>
                        <option value="">Select Badge</option>
                        <?php
                        $stmtBadges = $conn->prepare("SELECT badge_id FROM badge");
                        $stmtBadges->execute();
                        $badges = $stmtBadges->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($badges as $badge): ?>
                            <option value="<?= $badge['badge_id'] ?>">Badge #<?= $badge['badge_id'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="visit_description" class="form-label">Visit Description</label>
                    <input type="text" name="visit_description" id="visit_description" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="visit_duration" class="form-label">Visit Duration (in minutes)</label>
                    <input type="number" name="visit_duration" id="visit_duration" class="form-control" required>
                </div>

                <button type="submit" name="submit_visit" class="btn btn-primary">Assign Visit</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
