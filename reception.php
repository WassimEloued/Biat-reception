<?php   
session_start();

if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'reception') {
    header('Location: index.php');
    exit();
}

include 'db.php';

$searchVisitor = isset($_GET['search_visitor']) ? $_GET['search_visitor'] : '';
$searchEmployer = isset($_GET['search_employer']) ? $_GET['search_employer'] : '';

$visitorQuery = "SELECT * FROM visiteur";
$employerQuery = "SELECT * FROM employer";

$visitorParams = [];
$employerParams = [];

if ($searchVisitor) {
    $visitorQuery .= " WHERE cin LIKE ? OR nom LIKE ?";
    $visitorParams[] = '%' . $searchVisitor . '%';
    $visitorParams[] = '%' . $searchVisitor . '%';
}

if ($searchEmployer) {
    $employerQuery .= " WHERE cin LIKE ? OR nom LIKE ?";
    $employerParams[] = '%' . $searchEmployer . '%';
    $employerParams[] = '%' . $searchEmployer . '%';
}

$stmtVisitors = $conn->prepare($visitorQuery);
$stmtVisitors->execute($visitorParams);
$visitors = $stmtVisitors->fetchAll();

$stmtEmployers = $conn->prepare($employerQuery);
$stmtEmployers->execute($employerParams);
$employers = $stmtEmployers->fetchAll();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_employer'])) {
        $employer_cin = $_POST['employer_cin'];
        $employer_name = $_POST['employer_name'];
        $employer_phone = $_POST['employer_phone'];
        $employer_email = $_POST['employer_email'];
        $employer_password = $_POST['employer_password'];

        $checkEmployerStmt = $conn->prepare("SELECT COUNT(*) FROM employer WHERE cin = ?");
        $checkEmployerStmt->execute([$employer_cin]);
        $employerCount = $checkEmployerStmt->fetchColumn();

        if ($employerCount > 0) {
            $errorMessage = "An employer with this CIN already exists!";
        } else {
            if (!preg_match('/^\d+$/', $employer_cin)) {
                $errorMessage = "CIN must be a number.";
            }
            elseif (empty($employer_name)) {
                $errorMessage = "Employer name cannot be empty.";
            }
            elseif (!empty($employer_phone) && !preg_match('/^\d{8}$/', $employer_phone)) {
                $errorMessage = "Phone number must be 8 digits.";
            }
            elseif (!empty($employer_email) && !filter_var($employer_email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email format.";
            }
            elseif (strlen($employer_password) < 8) {
                $errorMessage = "Password must be at least 8 characters long.";
            } else {
                $stmt = $conn->prepare("INSERT INTO employer (cin, nom, telephone, email, password) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$employer_cin, $employer_name, $employer_phone, $employer_email, $employer_password]);

                $successMessage = "Employer successfully added!";
            }
        }
    }
    if (isset($_POST['add_visitor'])) { 
        $visitor_cin = $_POST['visitor_cin'];
        $visitor_name = $_POST['visitor_name'];
        $visitor_phone = $_POST['visitor_phone'] ?? '';
        $visitor_email = $_POST['visitor_email'] ?? '';
    
        $checkVisitorStmt = $conn->prepare("SELECT COUNT(*) FROM visiteur WHERE cin = ?");
        $checkVisitorStmt->execute([$visitor_cin]);
        $visitorCount = $checkVisitorStmt->fetchColumn();
    
        if ($visitorCount > 0) {
            $errorMessage = "A visitor with this CIN already exists!";
        } else {
            if (!preg_match('/^[01]\d{7}$/', $visitor_cin)) {
                $errorMessage = "CIN must be 8 digits starting with 0 or 1.";
            } elseif (!preg_match('/^[a-zA-Z]+\s+[a-zA-Z]+$/', $visitor_name)) {
                $errorMessage = "Name must contain two parts, like 'First Last'.";
            } elseif (!empty($visitor_phone) && !preg_match('/^\d{8}$/', $visitor_phone)) {
                $errorMessage = "Phone number must be 8 digits.";
            } elseif (!empty($visitor_email) && !filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email format.";
            } else {
                $stmt = $conn->prepare("INSERT INTO visiteur (cin, nom, telephone, email) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$visitor_cin, $visitor_name, $visitor_phone, $visitor_email])) {
                    $successMessage = "Visitor successfully added!";
                } else {
                    $errorMessage = "There was an issue adding the visitor.";
                }
            }
        }
    }
}
// all visits
$searchVisit = isset($_GET['search_visit']) ? $_GET['search_visit'] : '';

$visitQuery = "
    SELECT v.*, e.nom AS employer_name, vis.nom AS visitor_name
    FROM archived_visit v
    JOIN employer e ON v.employer_cin = e.cin
    JOIN visiteur vis ON v.visitor_cin = vis.cin
    WHERE 1";

$visitParams = [];

if (!empty($searchVisit)) {
    $visitQuery .= " AND (e.nom LIKE ? OR e.cin LIKE ? OR vis.nom LIKE ? OR vis.cin LIKE ?)";
    $visitParams = ['%' . $searchVisit . '%', '%' . $searchVisit . '%', '%' . $searchVisit . '%', '%' . $searchVisit . '%'];
}

$stmtVisits = $conn->prepare($visitQuery);
$stmtVisits->execute($visitParams);
$visitData = $stmtVisits->fetchAll();
if (isset($_GET['delete_visitor'])) {
    $visitor_cin = $_GET['delete_visitor'];
    echo "<script>
        if(confirm('Are you sure you want to delete this visitor?')) {
            window.location.href = 'reception.php?delete_visitor_action=$visitor_cin';
        }
    </script>";
}

if (isset($_GET['delete_employer'])) {
    $employer_cin = $_GET['delete_employer'];
    echo "<script>
        if(confirm('Are you sure you want to delete this employer?')) {
            window.location.href = 'reception.php?delete_employer_action=$employer_cin';
        }
    </script>";
}

if (isset($_GET['delete_visitor_action'])) {
    $visitor_cin = $_GET['delete_visitor_action'];
    $stmt = $conn->prepare("DELETE FROM visiteur WHERE cin = ?");
    $stmt->execute([$visitor_cin]);
    header("Location: reception.php");
    exit();
}

if (isset($_GET['delete_employer_action'])) {
    $employer_cin = $_GET['delete_employer_action'];
    $stmt = $conn->prepare("DELETE FROM employer WHERE cin = ?");
    $stmt->execute([$employer_cin]);
    header("Location: reception.php");
    exit();
}
//visiting
if (isset($_GET['mark_returned'])) { 
    $visitor_cin = $_GET['mark_returned'];
    $badge_id = $_GET['badge_id'];
    $conn->beginTransaction();

    try {
        $archiveVisitQuery = "
            INSERT INTO archived_visit (visit_id, visitor_cin, badge_id, employer_cin, description, duration, created_at, end_time, archived_at)
            SELECT visit_id, visitor_cin, badge_id, employer_cin, description, duration, created_at, end_time, NOW()
            FROM visit
            WHERE visitor_cin = ? AND badge_id = ? AND etat = 'Active'
            LIMIT 1
        ";

        $archiveVisitStmt = $conn->prepare($archiveVisitQuery);
        $archiveVisitStmt->execute([$visitor_cin, $badge_id]);

        if ($archiveVisitStmt->rowCount() == 0) {
            throw new Exception("Archiving failed, visit was not inserted.");
        }

        $deleteVisitQuery = "DELETE FROM visit WHERE visitor_cin = ? AND badge_id = ? AND etat = 'Active'";
        $deleteVisitStmt = $conn->prepare($deleteVisitQuery);
        $deleteVisitStmt->execute([$visitor_cin, $badge_id]);

        $updateBadgeQuery = "UPDATE badge SET status = 'Out' WHERE badge_id = ?";
        $updateBadgeStmt = $conn->prepare($updateBadgeQuery);
        $updateBadgeStmt->execute([$badge_id]);

        $conn->commit();
        echo 'success'; 
    } catch (Exception $e) {
        $conn->rollBack();
        echo 'error: ' . $e->getMessage();
    }

    exit();
}
$visitingQuery = "
    SELECT 
        v.cin AS visitor_cin, 
        v.nom AS visitor_name, 
        v.telephone AS visitor_phone, 
        va.badge_id, 
        va.duration, 
        e.nom AS employer_name, 
        b.status AS badge_status
    FROM visit va
    LEFT JOIN visiteur v ON va.visitor_cin = v.cin
    LEFT JOIN employer e ON va.employer_cin = e.cin
    LEFT JOIN badge b ON va.badge_id = b.badge_id
    WHERE va.etat = 'Active' AND b.status = 'In'
";
$visitingStmt = $conn->prepare($visitingQuery);
$visitingStmt->execute();
$visiting = $visitingStmt->fetchAll(PDO::FETCH_ASSOC);
//notif

$stmt = $conn->prepare(" 
    SELECT v.cin, v.nom, v.telephone, v.email, va.badge_id, va.end_time, b.status
    FROM visiteur v
    LEFT JOIN visit va ON v.cin = va.visitor_cin
    LEFT JOIN badge b ON va.badge_id = b.badge_id
    WHERE va.etat='Active' AND b.status = 'In'
");
$stmt->execute();
$visitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$visitorsForCarousel = json_encode($visitors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Visitor System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background-color: #2C3E50; 
            color: white;
            overflow-x: hidden;
        }

        .container {
            margin-top: 0px;
        }

        h1, h3, table th, table td {
            color: white;
        }

        .table-container {
            background: rgba(0, 0, 0, 0.8);
            padding: 15px;
            border-radius: 10px;
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

        .toggle-btn {
            margin-bottom: 15px;
        }

        .table-container table, th, td {
            color: black;
        }

        .center-buttons {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-light-blue {
            background-color: lightblue;
            border-color: lightblue;
            color: black;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .btn-light-blue:hover {
            background-color: #00bfff;
            border-color: #00bfff;
            color: white;
        }

        .scrollable-table {
            overflow-y: auto;
            max-height: 800px;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.5rem;
            }
            .container {
                padding: 10px;
            }
            .table-container {
                padding: 10px;
            }
        }

        .form-label {
            color: white;
        }

        .full-screen-header {
            height: 100%;
            background-color: #343a40;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1rem;
            background-size: cover;
        }

        .logo-section {
            display: flex;
            align-items: center;
        }

        .logo-section img {
            height: 50px;
            margin-right: 10px;
        }

        .logout-section {
            text-align: right;
        }
        
        footer {
        position: fixed;
        right: 0;
        bottom: 0;
        z-index: 10;
        width: auto;  
        max-width: 3000px; 
        padding-right: 0;  
        }

        .carousel {
            background-color:rgba(255, 0, 25, 0.04);  
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .carousel-control-prev, .carousel-control-next {
            color: white;
            width: 15%;
            opacity: 0.5;
            transition: opacity 0.15s ease;
        }

        .carousel-control-prev:hover, .carousel-control-next:hover {
            opacity: 0.9;
        }

        .carousel-indicators li {
            width: 30px;
            height: 3px;
            margin-right: 3px;
            background-color: rgba(255, 255, 255, 0.5);
            transition: opacity 0.6s ease;
        }

        .carousel-indicators .active {
            background-color: white;
            opacity: 1;
        }

        .carousel-caption {
            width: 70%;
            color: white;
            padding-top: 1.25rem;
            padding-bottom: 1.25rem;
        }

        .carousel-control-prev-icon, .carousel-control-next-icon {
            width: 2rem;
        }

        .carousel-control-prev-icon {
            background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'><path d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z'/></svg>");
        }

        .carousel-control-next-icon {
            background-image: url("data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'><path d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z'/></svg>");
        }

        .carousel-inner {
            transition: transform .6s ease-in-out;
        }

        .carousel-item {
            background:rgba(255, 0, 25, 0.62);
            padding: 10px;
            color: white;
        }

        .carousel-item h5 {
            margin: 0;
            font-size: 1.2rem;
        }

        .carousel-item p {
            font-size: 0.9rem;
        }

        .carousel-inner .active {
            opacity: 1;
        }
        .navbar{
            background-color:#0089B7;
        }
        .nav-link{
            background-color:#0089B7;
        }
        .alert-warning{
            background-color:#ffe0b2;
            color:rgba(0,0,0,.87)
        }
        .rec{
            color: black;
        }
        .card {
            margin-top: 20px;
        }

        .card-in {
            background-color: green;
            color: white;
        }

        .card-out {
            background-color: red;
            color: white;
        }

        .card-header, .card-body {
            text-align: center;
        }

        .btn-success {
            background-color: #0089B7;
            border-color: #0089B7;
        }
    </style>
</head>
<nav class="navbar navbar-expand-xl navbar-dark" style="width: 100%; margin: 0;">
    <a class="navbar-brand" href="reception.php" style="display: flex; align-items:center ;padding:8px;">
        <img src="logo.jpg" alt="Logo" style="height: 50px; margin-right: 10px;">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleForm('visitor-form')">Add-Visitor</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleTable('visitor-table')">Visitor-Table</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleForm('employer-form')">Add-Direction</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleTable('employer-table')">Direction-Table</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleTable('visiting')">Visits[current]</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="toggleVisitsTable()">Visits[ALL]</button>
            </li>
            <li class="nav-item">
                <button class="nav-link btn text-dark" onclick="window.location.href='stats.php'"><Statistique>Statistique</Statistique></button>
            </li>
        </ul>
            
    </div>
    <div class="ml-auto">
            <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>   
</nav>
<br>
<div class="container" id="containerDiv">
    <div class="alert alert-block alert-warning">
        <h3 class="rec">Banque Internationale Arabe De Tunisie</h3>
        <form method="POST" action="badge_status.php" accept-charset="UTF-8">
            <div class="form-group">
                <label for="visitor_cin">Check Badge:
                    <input class="btn btn-success input-xlarge" type="submit" value="Check">
                </label>
            </div>
        </form>
    </div>
    <button id="hideButton" class="btn btn-link" style="display:none;">
        <i class="fa fa-times"></i> 
    </button>
</div>
<script>
    document.getElementById('containerDiv').addEventListener('click', function(event) {
        if (!event.target.closest('#hideButton')) {
            var container = document.getElementById('containerDiv');
            container.style.display = 'none';
        }
    });
</script>
<script>
    function hideAlert() {
        var alert = document.querySelector('.alert');
        alert.style.display = 'none';
    }
</script>

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

<div id="visitor-form" class="form-section hidden"> 
    <h3>Add Visitor</h3>
    <form method="POST" action="reception.php">
        <div class="mb-3">
            <label for="visitor_cin" class="form-label">Visitor CIN</label>
            <input type="text" name="visitor_cin" id="visitor_cin" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="visitor_name" class="form-label">Visitor Name</label>
            <input type="text" name="visitor_name" id="visitor_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="visitor_phone" class="form-label">Visitor Phone</label>
            <input type="text" name="visitor_phone" id="visitor_phone" class="form-control" pattern="\d{8}" title="Phone number must be 8 digits">
        </div>
        <div class="mb-3">
            <label for="visitor_email" class="form-label">Visitor Email</label>
            <input type="email" name="visitor_email" id="visitor_email" class="form-control" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Enter a valid email address">
        </div>
        <button type="submit" name="add_visitor" class="btn btn-primary">Add Visitor</button>
    </form>
</div>


<div id="employer-form" class="form-section hidden">
    <h3>Add Direction</h3>
    <form method="POST" action="reception.php">
        <div class="mb-3">
            <label for="employer_cin" class="form-label">Direction ID</label>
            <input type="text" name="employer_cin" id="employer_cin" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="employer_name" class="form-label">Direction Name</label>
            <input type="text" name="employer_name" id="employer_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="employer_phone" class="form-label">Direction Phone</label>
            <input type="text" name="employer_phone" id="employer_phone" class="form-control">
        </div>
        <div class="mb-3">
            <label for="employer_email" class="form-label">Direction Email</label>
            <input type="email" name="employer_email" id="employer_email" class="form-control">
        </div>
        <div class="mb-3">
            <label for="employer_password" class="form-label">Direction Password</label>
            <input type="password" name="employer_password" id="employer_password" class="form-control" required>
        </div>
        <button type="submit" name="add_employer" class="btn btn-primary">Add Direction</button>
    </form>
</div>

<div id="visitor-table" class="table-container mt-5 hidden scrollable-table">
    <h3>Visitors List</h3>
    <input type="text" id="searchVisitor" class="form-control" placeholder="Search visitor...">
    <table class="table table-bordered table-hover mt-3">
        <thead>
            <tr>
                <th>CIN</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $visitorsStmt = $conn->prepare("SELECT * FROM visiteur");
            $visitorsStmt->execute();
            $visitors = $visitorsStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($visitors as $visitor): ?>
                <tr>
                    <td><?= htmlspecialchars($visitor['cin']) ?></td>
                    <td><?= htmlspecialchars($visitor['nom']) ?></td>
                    <td><?= htmlspecialchars($visitor['telephone']) ?></td>
                    <td><?= htmlspecialchars($visitor['email']) ?></td>
                    <td>
                        <a href="edit_visitor.php?cin=<?= htmlspecialchars($visitor['cin']) ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="reception.php?delete_visitor=<?= htmlspecialchars($visitor['cin']) ?>" class="btn btn-danger btn-sm">Delete</a>
                        <a href="visitor_visit.php?visitor_cin=<?= htmlspecialchars($visitor['cin']) ?>" class="btn btn-success btn-sm">Visit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="employer-table" class="table-container mt-5 hidden scrollable-table">
    <h3>Directions List</h3>
    <input type="text" id="searchEmployer" class="form-control" placeholder="Search employer...">
    <table class="table table-bordered table-hover mt-3">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Password</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employers as $employer): ?>
                <tr>
                    <td><?= htmlspecialchars($employer['cin']) ?></td>
                    <td><?= htmlspecialchars($employer['nom']) ?></td>
                    <td><?= htmlspecialchars($employer['telephone']) ?></td>
                    <td><?= htmlspecialchars($employer['email']) ?></td>
                    <td>
                        <span id="password-<?= htmlspecialchars($employer['cin']) ?>" class="password-mask">*****</span>
                        <button type="button" class="btn btn-info btn-sm" onclick="togglePassword('<?= htmlspecialchars($employer['cin']) ?>', '<?= htmlspecialchars($employer['password']) ?>')">Show</button>
                    </td>
                    <td><?=($employer['disp'])?></td>
                    <td>
                        <a href="edit_employer.php?cin=<?= htmlspecialchars($employer['cin']) ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="reception.php?delete_employer=<?= htmlspecialchars($employer['cin']) ?>" class="btn btn-danger btn-sm">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div id="visiting" class="table-container mt-5 hidden scrollable-table">
    <h3>Visitors Currently On Visit</h3>
    <input type="text" id="searchVisit" class="form-control" placeholder="Search visit...">
    <table class="table table-bordered table-hover mt-3">
        <thead>
            <tr>
                <th>CIN</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Direction</th>
                <th>Badge ID</th>
                <th>Duration</th>
                <th>Badge Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($visiting): ?>
                <?php foreach ($visiting as $visitor): ?>
                    <tr>
                        <td><?= htmlspecialchars($visitor['visitor_cin']) ?></td>
                        <td><?= htmlspecialchars($visitor['visitor_name']) ?></td>
                        <td><?= htmlspecialchars($visitor['visitor_phone']) ?></td>
                        <td><?= htmlspecialchars($visitor['employer_name']) ?></td>
                        <td><?= htmlspecialchars($visitor['badge_id']) ?></td>
                        <td><?= htmlspecialchars($visitor['duration']) ?> minutes</td>
                        <td><?= htmlspecialchars($visitor['badge_status']) ?></td>
                        <td>
                        <a href="javascript:void(0);"class="btn btn-success btn-sm return-badge"data-visitor-cin="<?= htmlspecialchars($visitor['visitor_cin']) ?>"data-badge-id="<?= htmlspecialchars($visitor['badge_id']) ?>">Return Badge</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">No visitors are currently on visit.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<div id="visits" class="table-container mt-5 hidden scrollable-table" >
    <h3>All Archived Visits</h3>
    <input type="text" id="searchBar" class="form-control" placeholder="Search Visit">
    <div id="visitsTableContainer">
        <table class="table table-bordered table-hover mt-3">
            <thead>
                <tr>
                    <th>Employer Name</th>
                    <th>Visitor Name</th>
                    <th>Visitor CIN</th>
                    <th>Visit Date Start</th>
                    <th>Visit Date End</th>
                </tr>
            </thead>
            <tbody id="visitsTableBody">
                <?php foreach ($visitData as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['employer_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['visitor_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['visitor_cin']); ?></td>
                        <td><?php echo htmlspecialchars($item['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($item['end_time']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<footer class="fixed-bottom p-3" style="right: 0; bottom: 0; width: auto; z-index: 100; padding-right: 0;" id="visitorCarouselFooter">
<audio id="alert-sound" src="alert.mp3" preload="auto"></audio>
    <div class="container">
        <div class="row justify-content-end">
            <div class="col-12 col-md-4">
                <div id="visitorCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner" id="carouselItems">
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#visitorCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#visitorCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
</footer>
<script>
    document.getElementById('visitorCarouselFooter').addEventListener('click', function(event) { 
        if (!event.target.closest('.carousel-control-prev') && !event.target.closest('.carousel-control-next')) {
            this.style.display = 'none';
        }
    });
    const visitorsForCarousel = <?php echo $visitorsForCarousel; ?>;
    console.log("Visitors Data:", visitorsForCarousel);

    const carouselItemsContainer = document.getElementById('carouselItems');
    const alertSound = document.getElementById('alert-sound');
    const visitorCarouselFooter = document.getElementById('visitorCarouselFooter');

    function createCarouselItem(visitor, isActive) {
        const item = document.createElement('div');
        item.classList.add('carousel-item');
        if (isActive) item.classList.add('active');

        const content = `
            <div class="d-flex flex-column align-items-center p-3">
                <h5>${visitor.nom} (CIN: ${visitor.cin})</h5>
                <p>Email: ${visitor.email}</p>
                <p>Phone: ${visitor.telephone}</p>
                <p>Badge Status: ${visitor.status}</p>
                <p class="text-danger fw-bold">Visit Ended: ${visitor.end_time}</p>
            </div>
        `;
        
        item.innerHTML = content;
        return item;
    }

    function updateVisitorCarousel() {
        if (!visitorsForCarousel || visitorsForCarousel.length === 0) {
            visitorCarouselFooter.style.display = 'none';
            return;
        }

        const overdueVisitors = visitorsForCarousel.filter(visitor => {
            const currentTime = new Date();
            const endTime = new Date(visitor.end_time);
            return endTime < currentTime && visitor.status === "In";
        });

        console.log("Overdue Visitors:", overdueVisitors);

        if (overdueVisitors.length === 0) {
            visitorCarouselFooter.style.display = 'none';
            return;
        }

        visitorCarouselFooter.style.display = 'block';
        carouselItemsContainer.innerHTML = ''; 

        overdueVisitors.forEach((visitor, index) => {
            const isActive = index === 0;
            const carouselItem = createCarouselItem(visitor, isActive);
            carouselItemsContainer.appendChild(carouselItem);
        });

        if (alertSound) alertSound.play();
    }

    document.addEventListener("DOMContentLoaded", updateVisitorCarousel);
</script>
<script>
    const searchVisitInput = document.getElementById('searchVisit');
    const visitRows = document.querySelectorAll('#visiting tbody tr');
    function togglePassword(cin, realPassword) {
        const passwordField = document.getElementById('password-' + cin);
        const currentPassword = passwordField.textContent.trim();
        
        if (currentPassword === '*****') {
            passwordField.textContent = realPassword;
        } else {
            passwordField.textContent = '*****';
        }
    }
    function toggleForm(formId) {
        const form = document.getElementById(formId);
        form.classList.toggle('hidden');
    }

    function toggleTable(tableId) {
        const table = document.getElementById(tableId);
        table.classList.toggle('hidden');
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        const searchVisitorInput = document.getElementById('searchVisitor');
        const searchEmployerInput = document.getElementById('searchEmployer');

        searchVisitorInput.addEventListener('input', function() {
            const searchQuery = searchVisitorInput.value.toLowerCase();
            const visitorRows = document.querySelectorAll('#visitor-table tbody tr');
        
            visitorRows.forEach(row => {
                const visitorCIN = row.cells[0].textContent.toLowerCase();
                const visitorName = row.cells[1].textContent.toLowerCase();
                if (visitorCIN.includes(searchQuery) || visitorName.includes(searchQuery)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        searchEmployerInput.addEventListener('input', function() {
            const searchQuery = searchEmployerInput.value.toLowerCase();
            const employerRows = document.querySelectorAll('#employer-table tbody tr');
        
            employerRows.forEach(row => {
                const employerCIN = row.cells[0].textContent.toLowerCase();
                const employerName = row.cells[1].textContent.toLowerCase();
                if (employerCIN.includes(searchQuery) || employerName.includes(searchQuery)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        searchVisitInput.addEventListener('input', function () {
        const searchQuery = searchVisitInput.value.toLowerCase();

        visitRows.forEach(row => {
            const visitorCIN = row.cells[0].textContent.toLowerCase();
            const visitorName = row.cells[1].textContent.toLowerCase();

            if (visitorCIN.includes(searchQuery) || visitorName.includes(searchQuery)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });});

document.addEventListener("DOMContentLoaded", updateVisitorCarousel);
setInterval(updateVisitorCarousel,10000);
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.return-badge').forEach(button => {
        button.addEventListener('click', function() {
            let visitorCin = this.dataset.visitorCin;
            let badgeId = this.dataset.badgeId;

            if (confirm("Are you sure this visitor is leaving?")) {
                fetch(`?mark_returned=${visitorCin}&badge_id=${badgeId}`)
                    .then(response => response.text())
                    .then(data => {
                        console.log('Server response:', data);  
                        if (data.trim() === 'success') {
                            alert("Badge successfully returned!");
                            location.reload(); 
                        } else {
                            alert("Error: " + data);
                        }
                    })
                    .catch(error => {
                        console.error("Error:", error);
                    });
            }
        });
    });
});
</script>
<script>
function toggleVisitsTable() {
    const visitsView = document.getElementById("visits");
    if (visitsView.style.display === "none" || visitsView.style.display === "") {
        visitsView.style.display = "block";
    } else {
        visitsView.style.display = "none";
    }
}

document.getElementById("searchBar").addEventListener("input", function () {
    const searchQuery = this.value.toLowerCase();
    filterVisits(searchQuery);
});

function filterVisits(query) {
    const rows = document.querySelectorAll("#visitsTableBody tr");

    rows.forEach(row => {
        const employerName = row.cells[0].textContent.toLowerCase();
        const employerCIN = row.cells[1].textContent.toLowerCase();
        const visitorName = row.cells[2].textContent.toLowerCase();
        const visitorCIN = row.cells[3].textContent.toLowerCase();

        if (employerName.includes(query) || employerCIN.includes(query) || visitorName.includes(query) || visitorCIN.includes(query)) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
