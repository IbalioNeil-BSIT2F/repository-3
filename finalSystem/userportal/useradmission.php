<?php
include('..\\php\\connection.php');
include('..\\php\\check_status_helper.php');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['user'];

// Handle control number button click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_cn'])) {
    $stmt = $conn->prepare("UPDATE check_status SET control_number_click = 1 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    // Refresh page to trigger generation
    header("Location: useradmission.php");
    exit();
}

// Fetch current check_status
$stmt = $conn->prepare("SELECT * FROM check_status WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

$control_number = '';
$control_number_click = 0;
$allCompleted = false;

if ($row = $result->fetch_assoc()) {
    $control_number = $row['control_number'];
    $control_number_click = $row['control_number_click'];
    $allCompleted = $row['admission_info_completed'] &&
                    $row['personal_info_completed'] &&
                    $row['family_bg_completed'] &&
                    $row['education_bg_completed'] &&
                    $row['med_his_info_completed'];
}

// Only generate control number if click is made and all forms are done
checkAndGenerateControlNumber($conn, $username);

// Re-fetch in case control_number just generated
$stmt = $conn->prepare("SELECT control_number FROM check_status WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$control_result = $stmt->get_result();
if ($control = $control_result->fetch_assoc()) {
    if (!empty($control['control_number'])) {
        header("Location: useradmission2.php");
        exit();
    }
}

// Fetch user info
$stmt = $conn->prepare("SELECT * FROM accounts WHERE email = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admission Overview</title>
  <link rel="stylesheet" href="..\css\useradmission.css">
</head>
<body>

  <div class="sidebar">
    <div>
      <div class="logo">LOGO</div>
      <div class="nav-top">
        <button class="nav-btn" onclick="window.location.href='userdashboard.php'">Dashboard</button>
        <button class="nav-btn" onclick="window.location.href='useradmission.php'">Admission Overview</button>
        <button class="nav-btn" onclick="window.location.href='userprodandprog.php'">Procedures and Programs</button>
      </div>
    </div>
    <div class="nav-bottom">
      <button class="nav-btn">Settings</button>
      <button class="nav-btn">Help</button>
    </div>
  </div>

  <div class="main">
    <div class="topbar">
      <div class="left">
        <button class="backbtn">← Back</button>
      </div>
      <div class="right">
        <p>Welcome, <span><?php echo htmlspecialchars($_SESSION['user']); ?></span></p>
        <a href="..\php\logout.php"><button class="btn font-weight-bold">Logout</button></a>
      </div>
    </div>

    <div class="content">
      <section class="hero">
        <div class="hero-text"></div>
        <div class="hero-image">
          <img src="10221634-58ca-41c8-9dc7-b5c3ce73cc59.png" alt="CvSU Students" />
        </div>
      </section>

      <div class="dashboard-cards">
        <div class="card">
          <h3>Applicant Data</h3>
          <p><a href="admission_info.php">Admission Information</a></p>
          <p><a href="personal_info.php">Personal Information</a></p>
          <p><a href="family_bg.php">Family Background</a></p>
          <p><a href="education_bg.php">Educational Background</a></p>
          <p><a href="med_his_info.php">Medical History Information</a></p>
        </div>

        <div class="card card2">
          <p>You can't modify your application form once you get your control number</p>

          <?php if ($allCompleted && !$control_number_click): ?>
            <form method="POST">
              <button class="button2" type="submit" name="generate_cn">Get Control Number</button>
            </form>
          <?php elseif ($control_number_click): ?>
            <p style="color: green;">Control number is being generated. Redirecting...</p>
          <?php else: ?>
            <button class="button2" disabled>Get Control Number</button>
            <p style="color: red;">Complete all 5 forms to unlock your Control Number.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
