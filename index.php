<?php  
session_start();
include 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];  
    $password = $_POST['password'];

    $username = trim($username);
    $password = trim($password);

    $stmtReception = $conn->prepare("SELECT * FROM reception WHERE cin = :cin");
    $stmtReception->bindParam(':cin', $username, PDO::PARAM_INT); 
    $stmtReception->execute();
    $resultReception = $stmtReception->fetch(PDO::FETCH_ASSOC);

    $stmtEmployer = $conn->prepare("SELECT * FROM employer WHERE cin = :cin");
    $stmtEmployer->bindParam(':cin', $username, PDO::PARAM_INT); 
    $stmtEmployer->execute();
    $resultEmployer = $stmtEmployer->fetch(PDO::FETCH_ASSOC);

    if ($resultReception) {
        if ($password === $resultReception['password']) {
            $_SESSION['cin'] = $resultReception['cin'];
            $_SESSION['role'] = 'reception';
            header('Location: reception.php');
            exit;
        } else {
            echo "<p class='error-message'>Invalid password</p>";
        }
    } 
    else if ($resultEmployer) {
        if ($password === $resultEmployer['password']) {
            $_SESSION['cin'] = $resultEmployer['cin'];
            $_SESSION['role'] = 'employer';
            header('Location: employer.php');
            exit;
        } else {
            echo "<p class='error-message'>Invalid password</p>";
        }
    } else {
        echo "<p class='error-message'>No user found with that CIN</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Arial', sans-serif;
            background: url('banner1.jpg') no-repeat center center fixed;
            background-size: cover;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background 0.1s ease;
        }

        @keyframes bgAnimation {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 0%; }
        }

        body {
            animation: bgAnimation 30s ease-in-out infinite;
        }

        .login-page {
            width: 100%;
            max-width: 400px;
            padding: 40px;
            background: rgba(28, 28, 28, 0.8);
            box-shadow: 10px 10px 20px rgba(0, 0, 0, 0.3), -10px -10px 20px rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            animation: slideUp 0.8s ease-out forwards;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .login-container h2 {
            font-size: 28px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 30px;
            animation: fadeIn 1s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .login-form input {
            width: 90%;
            padding: 15px;
            margin: 10px 0;
            border-radius: 25px;
            border: none;
            background: #333;
            color: white;
            font-size: 16px;
            outline: none;
            transition: all 0.3s ease-in-out;
            box-shadow: inset 5px 5px 10px rgba(0, 0, 0, 0.4), inset -5px -5px 10px rgba(255, 255, 255, 0.1);
            background-position: 10px center;
            background-repeat: no-repeat;
            padding-left: 10px;
            transition: transform 0.3s ease-in-out;
        }

        .login-form input[type="text"] {
            background-image: url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/svgs/solid/user.svg');
            background-size: 20px;
        }

        .login-form input[type="password"] {
            background-image: url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/svgs/solid/lock.svg');
            background-size: 20px;
        }

        .login-form input:focus {
            background: #444;
            box-shadow: inset 5px 5px 10px rgba(0, 0, 0, 0.6), inset -5px -5px 10px rgba(255, 255, 255, 0.1);
            transform: scale(1.05);
        }

        .login-form .btn {
            width: 100%;
            padding: 15px;
            background-color: #ff7e5f;
            color: white;
            font-size: 18px;
            font-weight: 400;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: transform 0.3s ease, background-color 0.3s ease;
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.3), -5px -5px 15px rgba(255, 255, 255, 0.2);
        }

        .login-form .btn:hover {
            transform: scale(1.1);
            background-color: #feb47b;
            box-shadow: 5px 5px 25px rgba(0, 0, 0, 0.3), -5px -5px 25px rgba(255, 255, 255, 0.2);
        }

        .error-message {
            color: #ff3547;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .login-page {
                width: 80%;
                padding: 10px;
            }

            .login-container h2 {
                font-size: 24px;
            }

            .login-form input {
                padding: 12px;
            }

            .login-form .btn {
                padding: 12px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
        
    <div class="login-page">
        <div class="login-container">
        <img src="login.gif" alt="Logo" style="height: 50px; margin-right: 10px;">
            <h2>Login</h2>
            <form action="index.php" method="POST" class="login-form">
                <input type="text" name="username" placeholder="Username (CIN)" maxlength="8" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
    </div>

</body>
</html>
