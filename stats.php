<?php      
session_start();
if (!isset($_SESSION['cin']) || $_SESSION['role'] !== 'reception') {
    header('Location: index.php');
    exit();
}

include 'db.php';

try {
    $direction = isset($_GET['direction']) ? $_GET['direction'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';

    $sql = "SELECT v.employer_cin, e.nom AS employer_name, 
                DATE(v.archived_at) AS day, YEAR(v.archived_at) AS year, MONTH(v.archived_at) AS month, COUNT(*) AS visit_count
            FROM archived_visit v
            JOIN employer e ON v.employer_cin = e.cin
            WHERE 1"; 
    
    if (!empty($direction)) {
        $sql .= " AND v.employer_cin = :direction";
    }

    if (!empty($date) && preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $date)) {
        $sql .= " AND DATE(v.archived_at) = :date";
    }

    $sql .= " GROUP BY v.employer_cin, e.nom, YEAR(v.archived_at), MONTH(v.archived_at)
              ORDER BY visit_count DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($direction)) {
        $stmt->bindParam(':direction', $direction);
    }
    
    if (!empty($date) && preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $date)) {
        $stmt->bindParam(':date', $date);
    }

    $stmt->execute();

    $visitData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $visitData[] = $row;
    }

    $monthlyData = [];
    for ($i = 0; $i < 5; $i++) {
        $month = date("Y-m", strtotime("-$i month"));
        $monthlyData[$month] = array_sum(array_column(array_filter($visitData, function($visit) use ($month) {
            return $visit['year'] . '-' . str_pad($visit['month'], 2, '0', STR_PAD_LEFT) === $month;
        }), 'visit_count'));
    }

    $employerVisitCount = [];
    foreach ($visitData as $visit) {
        if (!isset($employerVisitCount[$visit['employer_name']])) {
            $employerVisitCount[$visit['employer_name']] = 0;
        }
        $employerVisitCount[$visit['employer_name']] += $visit['visit_count'];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Visits Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.22/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            background-color:rgb(255, 255, 255);
            color: #ffffff;
        }
        .table th, .table td {
            color: black;
            text-align: center;
            vertical-align: middle;
        }
        .table th {
            background-color: #0089B7;
            color: white;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f2f2f2;
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
        .chart-container {
            margin-top: 20px;
        }
        .pie-chart-container {
            height: 350px;
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
            <h1 class="text-center">Statistics</h1>  
        </div>
        <div class="ml-auto">
            <button id="dailyBtn" class="btn btn-blue-light">Daily</button>
            <button id="monthlyBtn" class="btn btn-blue-light">Monthly</button>
        </div>   
    </div>
</nav>

<div class="container" id="dailyView">
    <h3 class="text-center">Daily Visits</h3>
    <div class="d-flex justify-content-center mb-4">
        <input type="date" id="datePicker" class="form-control w-25" />
        <button id="filterBtn" class="btn btn-success ms-2">Filter</button>
    </div>
    <div id="dailyTableContainer" class="text-center"></div>
    <div class="text-center my-4">
        <button id="downloadDailyPdfBtn" class="btn btn-primary">Download Daily PDF</button>
    </div>
</div>

<div class="container" id="monthlyView" style="display: none;">
    <h3 class="text-center">Monthly Visits</h3>
    <div class="chart-container">
        <div class="row">
            <div class="col-md-6">
                <canvas id="barChart" style="width:100%; height:400px;"></canvas>
            </div>
            <div class="col-md-6">
                <canvas id="pieChart" style="width:100%; height:400px;"></canvas>
            </div>
        </div>
    </div>
    <div class="text-center my-4">
        <button id="downloadMonthlyPdfBtn" class="btn btn-primary">Download Monthly PDF</button>
    </div>
</div>

<script>
    const visitData = <?php echo json_encode($visitData); ?>;
    const monthlyData = <?php echo json_encode($monthlyData); ?>;
    const employerVisitCount = <?php echo json_encode($employerVisitCount); ?>;

    document.getElementById("dailyBtn").addEventListener("click", function () {
        document.getElementById("dailyView").style.display = "block";
        document.getElementById("monthlyView").style.display = "none";
    });

    document.getElementById("monthlyBtn").addEventListener("click", function () {
        document.getElementById("dailyView").style.display = "none";
        document.getElementById("monthlyView").style.display = "block";

        new Chart(document.getElementById('barChart'), {
            type: 'bar',
            data: {
                labels: Object.keys(monthlyData).map(month => new Date(month).toLocaleString('default', { month: 'long' })),
                datasets: [{
                    label: 'Total Visits',
                    data: Object.values(monthlyData),
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        const pieColors = Array(16).fill(0).map((_, i) => `hsl(${(i * 360) / 16}, 70%, 50%)`); // 16 distinct colors
        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: Object.keys(employerVisitCount),
                datasets: [{
                    data: Object.values(employerVisitCount),
                    backgroundColor: pieColors,
                }]
            },
            options: {
                responsive: true
            }
        });
    });

    document.getElementById("filterBtn").addEventListener("click", function () {
        const selectedDate = document.getElementById("datePicker").value;
        if (selectedDate) {
            const filteredData = visitData.filter(item => item.day === selectedDate);
            let tableHtml = `<table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Employer Name</th>
                                        <th>Date</th>
                                        <th>Visit Count</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            filteredData.forEach(item => {
                tableHtml += `<tr>
                                <td>${item.employer_name}</td>
                                <td>${item.day}</td>
                                <td>${item.visit_count}</td>
                            </tr>`;
            });
            tableHtml += `</tbody></table>`;
            document.getElementById("dailyTableContainer").innerHTML = tableHtml;
        }
    });

    document.getElementById("downloadDailyPdfBtn").addEventListener("click", function () {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Employer Visit Statistics (Daily View)", 20, 20);
        const date = document.getElementById("datePicker").value;
        const filteredData = visitData.filter(item => item.day === date);
        if (filteredData.length > 0) {
            const tableData = filteredData.map(item => [
                item.employer_name, item.day, item.visit_count
            ]);
            doc.autoTable({
                head: [['Employer Name', 'Date', 'Visit Count']],
                body: tableData,
                startY: 30,
                theme: 'grid',
                headStyles: { fillColor: [0, 51, 102], textColor: 255, fontSize: 10 },
                styles: { fontSize: 10, cellPadding: 2, halign: 'center' },
            });
        } else {
            doc.text("No data available for the selected date.", 20, 30);
        }
        doc.save('daily_statistics.pdf');
    });

    document.getElementById("downloadMonthlyPdfBtn").addEventListener("click", function () {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.text("Employer Visit Statistics (Monthly View)", 20, 20);

        const barChart = document.getElementById("barChart");
        const barChartDataUrl = barChart.toDataURL("image/png");
        doc.addImage(barChartDataUrl, 'PNG', 20, 30, 90, 60);

        doc.addPage(); 

        const pieChart = document.getElementById("pieChart");
        const pieChartDataUrl = pieChart.toDataURL("image/png");
        doc.addImage(pieChartDataUrl, 'PNG', 20, 30, 180, 120);

        doc.save('monthly_statistics.pdf');
    });

    document.getElementById("monthlyBtn").click();
</script>

</body>
</html>
