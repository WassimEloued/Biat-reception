<?php      
session_start();

session_regenerate_id(true);

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'employer') {
    header('Location: index.php');
    exit();
}

include 'db.php';

$cin = $_SESSION['cin'];

$stmt = $conn->prepare("SELECT * FROM employer WHERE cin = ?");
$stmt->execute([$cin]);
$employer = $stmt->fetch();

if (!$employer) {
    echo "Employer not found.";
    exit();
}

if (isset($_POST['update_status'])) {
    $currentStatus = $_POST['current_status'];
    $newStatus = ($currentStatus == 'oui') ? 'non' : 'oui';

    $updateStmt = $conn->prepare("UPDATE employer SET disp = ? WHERE cin = ?");
    $updateStmt->execute([$newStatus, $cin]);

    $_SESSION['disp'] = $newStatus;
    $employer['disp'] = $newStatus;
}

$archiveStmt = $conn->prepare("
    UPDATE visit 
    SET end_time = NOW() 
    WHERE end_time IS NULL 
    AND employer_cin = :employer_cin
    AND created_at <= NOW()
");
$archiveStmt->execute(['employer_cin' => $cin]);

$nextVisitorStmt = $conn->prepare("
    SELECT v.visitor_cin, v.created_at AS date_heure_arrivee, v.duration, v.end_time, 
           vi.nom, vi.telephone, vi.email
    FROM visit v
    JOIN visiteur vi ON vi.cin = v.visitor_cin
    WHERE v.employer_cin = :employer_cin 
    AND v.end_time > NOW() 
    AND v.end_time IS NOT NULL
    ORDER BY v.end_time ASC LIMIT 1
");
$nextVisitorStmt->execute(['employer_cin' => $cin]);
$nextVisitor = $nextVisitorStmt->fetch();

$endedVisitorStmt = $conn->prepare("
    SELECT v.visitor_cin, v.created_at AS date_heure_arrivee, v.duration, v.end_time, 
           vi.nom, vi.telephone, vi.email
    FROM visit v
    JOIN visiteur vi ON vi.cin = v.visitor_cin
    WHERE v.employer_cin = :employer_cin 
    AND v.end_time <= NOW() 
    AND v.end_time IS NOT NULL
    ORDER BY v.end_time DESC LIMIT 1
");
$endedVisitorStmt->execute(['employer_cin' => $cin]);
$endedVisitor = $endedVisitorStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            background-color: #2C3E50;
        }
        .container {
            margin-top: 20px;
            z-index: 2;
            position: relative;
            color: white;
        }

        .table-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }

        h1, h3 {
            color: white;
        }

        .alert {
            color: white;
            background-color: #28a745;
        }

        .table th, .table td {
            text-align: center;
            vertical-align: middle;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-dark tbody tr:nth-child(odd) {
            background-color: #343a40;
        }

        .table-dark tbody tr:nth-child(even) {
            background-color: #454d55;
        }

        .table-dark th {
            background-color: #2d3338;
            color: #fff;
        }
        nav{
            background-color: #0089B7;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-light" style="width: 100%; margin: 0;">
    <a class="navbar-brand" href="employer.php" style="display: flex; align-items:center; padding: 8px;">
        <img src="logo.jpg" alt="Logo" style="height: 50px; margin-right: 10px;">
    </a>
    <div class="d-flex align-items-center justify-content-between w-100">
        <h1 class="navbar-text me-3">Bienvenue, <?= htmlspecialchars($employer['nom']); ?>!</h1>

        <div class="status-container d-flex align-items-center me-3">
            <h3 class="navbar-text me-3">Disponible: <strong><?= ucfirst($employer['disp']); ?></strong></h3>
            <form method="POST" action="employer.php">
                <input type="hidden" name="current_status" value="<?= $employer['disp']; ?>">
                <button type="submit" name="update_status" class="btn btn-warning btn-sm">
                    <?= $employer['disp'] == 'oui' ? 'Deactivate' : 'Activate' ?>
                </button>
            </form>
        </div>

        <div class="dropdown">
            <span class="profile-icon" data-bs-toggle="dropdown">
                <img src="profile.png" alt="Profile Icon" width="32" height="32">
            </span>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#">CIN: <?= htmlspecialchars($employer['cin']); ?></a></li>
                <li><a class="dropdown-item" href="#">Name: <?= htmlspecialchars($employer['nom']); ?></a></li>
                <li><a class="dropdown-item" href="#">Email: <?= htmlspecialchars($employer['email']); ?></a></li>
                <li><a class="dropdown-item" href="change-password.php">Change Password</a></li>
                <li><a class="dropdown-item" href="archive.php">View Archived Visitors</a></li>
                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

        <div class="table-container">
            <h3>Visitor</h3>
            <?php if ($nextVisitor): ?>
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>CIN</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Visit Start</th>
                            <th>Visit End</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?= htmlspecialchars($nextVisitor['visitor_cin']); ?></td>
                            <td><?= htmlspecialchars($nextVisitor['nom']); ?></td>
                            <td><?= htmlspecialchars($nextVisitor['telephone']); ?></td>
                            <td><?= htmlspecialchars($nextVisitor['email']); ?></td>
                            <td><?= date('H:i', strtotime($nextVisitor['date_heure_arrivee'])); ?></td> 
                            <td><?= date('H:i', strtotime($nextVisitor['end_time'])); ?></td> 
                            <td><?= htmlspecialchars($nextVisitor['duration']) . ' minutes'; ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No upcoming visitors.</p>
            <?php endif; ?>
            
            <?php if ($endedVisitor): ?>
                <p><strong>Visitor Session Ended:</strong> <?= htmlspecialchars($endedVisitor['nom']); ?>'s visit has ended.</p>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        var audio = new Audio('alert.mp3'); 
        if (startTime === now) {
            console.log("Sound should play now (startTime match)");
            audio.play();
        }

        if (endTime === now) {
            console.log("Sound should play now (endTime match)");
            audio.play();
        }

    </script>

</body>
</html>
