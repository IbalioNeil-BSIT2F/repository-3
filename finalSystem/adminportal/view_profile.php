<?php
include('../php/connection.php');
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['username'])) {
    echo "Invalid request.";
    exit();
}

$username = $_GET['username'];

// Fetch all applicant data
$admission = $conn->query("SELECT * FROM admission_info WHERE username = '$username'")->fetch_assoc();
$personal = $conn->query("SELECT * FROM personal_info WHERE username = '$username'")->fetch_assoc();
$family = $conn->query("SELECT * FROM family_bg WHERE username = '$username'")->fetch_assoc();
$education = $conn->query("SELECT * FROM education_bg WHERE username = '$username'")->fetch_assoc();
$medical = $conn->query("SELECT * FROM med_his_info WHERE username = '$username'")->fetch_assoc();
$status = $conn->query("SELECT * FROM application_status WHERE username = '$username'")->fetch_assoc();
$control = $conn->query("SELECT control_number FROM check_status WHERE username = '$username'")->fetch_assoc();

$entry = $admission['entry'] ?? '';
$control_number = $control['control_number'] ?? 'N/A';

// Get uploaded files
if ($entry === 'New') {
    $files = $conn->query("SELECT * FROM freshmen_files WHERE username = '$username'")->fetch_assoc();
} elseif ($entry === 'Transferee') {
    $files = $conn->query("SELECT * FROM transferee_files WHERE username = '$username'")->fetch_assoc();
} elseif ($entry === 'Second Courser') {
    $files = $conn->query("SELECT * FROM second_courser_files WHERE username = '$username'")->fetch_assoc();
} else {
    $files = [];
}

// Handle approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept'])) {
        $new_status = 1;
    } elseif (isset($_POST['reject'])) {
        $new_status = 3;
    }
    
    $stmt = $conn->prepare("INSERT INTO application_status (username, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
    $stmt->bind_param("si", $username, $new_status);
    $stmt->execute();
    $stmt->close();

    header("Location: adminadmission.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Profile</title>
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
  <div class="sidebar">
    <div class="logo">LOGO</div>
    <div class="nav-top">
        <button class="nav-btn" onclick="window.location.href='admindashboard.php'">Dashboard</button>
        <button class="nav-btn" onclick="window.location.href='adminadmission.php'">Manage Admission</button>
        <button class="nav-btn" onclick="window.location.href='admingrading.php'">Manage Grade</button>
        <button class="nav-btn" onclick="window.location.href='exam_category.php'">Manage Exam</button>
    </div>
    <div class="nav-bottom">
      <button>Settings</button>
      <button>Help</button>
    </div>
  </div>

  <div class="main">
    <div class="topbar">
      <div class="left"><a href="adminadmission.php">&larr; Back</a></div>
      <div class="right">
        <p>Welcome, <span><?php echo $_SESSION['admin']; ?></span></p>
        <a href="../php/logout.php"><button>Logout</button></a>
      </div>
    </div>

    <div class="content">
      <h2>Applicant Profile</h2>
      <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
      <p><strong>Control Number:</strong> <?php echo htmlspecialchars($control_number); ?></p>

      <h3>Personal Information</h3>
      <?php foreach ($personal as $k => $v) echo "<p><strong>$k:</strong> " . htmlspecialchars($v) . "</p>"; ?>

      <h3>Admission Information</h3>
      <?php foreach ($admission as $k => $v) echo "<p><strong>$k:</strong> " . htmlspecialchars($v) . "</p>"; ?>

      <h3>Family Background</h3>
      <?php foreach ($family as $k => $v) echo "<p><strong>$k:</strong> " . htmlspecialchars($v) . "</p>"; ?>

      <h3>Educational Background</h3>
      <?php foreach ($education as $k => $v) echo "<p><strong>$k:</strong> " . htmlspecialchars($v) . "</p>"; ?>

      <h3>Medical History</h3>
      <?php foreach ($medical as $k => $v) echo "<p><strong>$k:</strong> " . htmlspecialchars($v) . "</p>"; ?>

      <h3>Uploaded Files</h3>
      <?php if ($files): foreach ($files as $label => $file): 
        if (!in_array($label, ['id', 'submitted_at', 'username', 'control_number']) && $file): ?>
          <p><strong><?php echo htmlspecialchars($label); ?>:</strong> <a href="../uploads/<?php echo htmlspecialchars($file); ?>" target="_blank">View</a></p>
      <?php endif; endforeach; else: echo "<p>No files found.</p>"; endif; ?>

      <h3>Application Status</h3>
      <p>Status: <strong>
        <?php
        $stat = $status['status'] ?? 0;
        echo $stat == 1 ? 'Accepted' : ($stat == 3 ? 'Rejected' : 'Pending');
        ?>
      </strong></p>

      <?php if (!isset($status['status']) || $status['status'] == 0): ?>
      <form method="POST">
        <button type="submit" name="accept">Accept</button>
        <button type="submit" name="reject">Reject</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
