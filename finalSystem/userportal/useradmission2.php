<?php
include('../php/connection.php');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

$username = $_SESSION['user'];

// Fetch user status
$stmt = $conn->prepare("SELECT * FROM check_status WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: useradmission.php");
    exit();
}

$status = $result->fetch_assoc();

if ((int)$status['current_stage'] > 2) {
    header("Location: useradmission3.php");
    exit();
}

if ((int)$status['current_stage'] < 2 || empty($status['control_number'])) {
    header("Location: useradmission.php");
    exit();
}

$control_number = $status['control_number'];

// Get user info and admission type
$stmt = $conn->prepare("SELECT firstname, lastname FROM personal_info WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc() ?: ['firstname' => '', 'lastname' => ''];

$stmt = $conn->prepare("SELECT entry, type FROM admission_info WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$type_result = $stmt->get_result();
$type_data = $type_result->fetch_assoc();

$entry = $type_data['entry'] ?? '';
$type = $type_data['type'] ?? '';

$pending_message = '';

// Handle file submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_files'])) {
    $upload_dir = '../uploads/';
    $timestamp = date('Y-m-d H:i:s');

    function save_file($input_name, $username, $upload_dir) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES[$input_name]['tmp_name'];
            $file_name = basename($_FILES[$input_name]['name']);
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_name = $username . '_' . $input_name . '_' . time() . '.' . $ext;
            $dest_path = $upload_dir . $new_name;
            move_uploaded_file($file_tmp, $dest_path);
            return $new_name;
        }
        return null;
    }

    if ($entry === 'New') {
        $report_card = save_file('report_card', $username, $upload_dir);
        $gmc = save_file('gmc', $username, $upload_dir);
        $birth_cert = save_file('birth_cert', $username, $upload_dir);

        $stmt = $conn->prepare("INSERT INTO freshmen_files (username, control_number, report_card, gmc, birth_cert, submitted_at)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE report_card=?, gmc=?, birth_cert=?, submitted_at=?");
        $stmt->bind_param("ssssssssss", $username, $control_number, $report_card, $gmc, $birth_cert, $timestamp,
                                      $report_card, $gmc, $birth_cert, $timestamp);
        $stmt->execute();
    } elseif ($entry === 'Transferee') {
        $tor = save_file('tor', $username, $upload_dir);
        $dismissal = save_file('dismissal', $username, $upload_dir);
        $gmc = save_file('gmc', $username, $upload_dir);
        $nbi = save_file('nbi', $username, $upload_dir);
        $birth_cert = save_file('birth_cert', $username, $upload_dir);

        $stmt = $conn->prepare("INSERT INTO transferee_files (username, control_number, tor, dismissal, gmc, nbi, birth_cert, submitted_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE tor=?, dismissal=?, gmc=?, nbi=?, birth_cert=?, submitted_at=?");
        $stmt->bind_param("ssssssssssssss", $username, $control_number, $tor, $dismissal, $gmc, $nbi, $birth_cert, $timestamp,
                                             $tor, $dismissal, $gmc, $nbi, $birth_cert, $timestamp);
        $stmt->execute();
    } elseif ($entry === 'Second Courser') {
        $tor = save_file('tor', $username, $upload_dir);
        $gmc = save_file('gmc', $username, $upload_dir);
        $birth_cert = save_file('birth_cert', $username, $upload_dir);

        $stmt = $conn->prepare("INSERT INTO second_courser_files (username, control_number, tor, gmc, birth_cert, submitted_at)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE tor=?, gmc=?, birth_cert=?, submitted_at=?");
        $stmt->bind_param("ssssssssss", $username, $control_number, $tor, $gmc, $birth_cert, $timestamp,
                                       $tor, $gmc, $birth_cert, $timestamp);
        $stmt->execute();
    }

    // ✅ Set stage 3
    $stmt = $conn->prepare("UPDATE check_status SET current_stage = 3 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    // ✅ Insert or update application_status to pending (0)
    $stmt = $conn->prepare("INSERT INTO application_status (username, status) 
                            VALUES (?, 0) 
                            ON DUPLICATE KEY UPDATE status = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    // ✅ Display pending message
    $pending_message = 'Your documents have been submitted and are now pending approval.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admission Requirements</title>
  <link rel="stylesheet" href="../css/useradmission.css">
  <style>
    .upload-group input[type="file"] {
      border: 2px solid #aaa;
      border-radius: 5px;
      padding: 6px;
      background-color: #f9f9f9;
      width: 100%;
    }

    .button2:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
    .message {
      padding: 10px;
      background-color: #f0f8ff;
      border: 1px solid #3399ff;
      color: #003366;
      border-radius: 5px;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <div>
      <div class="logo">LOGO</div>
      <div class="nav-top">
        <button class="nav-btn" onclick="window.location.href='userdashboard.php'">Dashboard</button>
        <button class="nav-btn" onclick="window.location.href='useradmission2.php'">Admission Overview</button>
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
        <button class="backbtn" disabled>← Back</button>
      </div>
      <div class="right">
        <p>Welcome, <span><?php echo htmlspecialchars($user_data['firstname'] . ' ' . $user_data['lastname']); ?></span></p>
        <a href="../php/logout.php"><button class="btn">Logout</button></a>
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
        <div class="card control-card">
          <p class="label">Your Control Number</p>
          <h2 class="control-number"><?php echo $control_number; ?></h2>
        </div>

        <div class="card upload-card">
          <?php if (!empty($pending_message)): ?>
            <div class="message"><?php echo $pending_message; ?></div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" id="uploadForm">
            <h3>Admission Requirements: <?php echo htmlspecialchars(ucfirst($entry) . ' / ' . ucfirst($type)); ?></h3>

            <?php if ($entry === 'New'): ?>
              <label for="report_card">Report Card / ALS Certificate</label>
              <div class="upload-group">
                <input type="file" name="report_card" id="report_card" required />
              </div>
              <label for="gmc">Certificate of Good Moral Character</label>
              <div class="upload-group">
                <input type="file" name="gmc" id="gmc" required />
              </div>
              <label for="birth_cert">PSA Birth Certificate</label>
              <div class="upload-group">
                <input type="file" name="birth_cert" id="birth_cert" required />
              </div>

            <?php elseif ($entry === 'Transferee'): ?>
              <label for="tor">Transcript of Records / Certificate of Grades</label>
              <div class="upload-group">
                <input type="file" name="tor" id="tor" required />
              </div>
              <label for="dismissal">Transfer Credentials / Honorable Dismissal</label>
              <div class="upload-group">
                <input type="file" name="dismissal" id="dismissal" required />
              </div>
              <label for="gmc">Certificate of Good Moral Character</label>
              <div class="upload-group">
                <input type="file" name="gmc" id="gmc" required />
              </div>
              <label for="nbi">NBI Clearance</label>
              <div class="upload-group">
                <input type="file" name="nbi" id="nbi" required />
              </div>
              <label for="birth_cert">PSA Birth Certificate</label>
              <div class="upload-group">
                <input type="file" name="birth_cert" id="birth_cert" required />
              </div>

            <?php elseif ($entry === 'Second Courser'): ?>
              <label for="tor">Transcript of Records</label>
              <div class="upload-group">
                <input type="file" name="tor" id="tor" required />
              </div>
              <label for="gmc">Certificate of Good Moral Character</label>
              <div class="upload-group">
                <input type="file" name="gmc" id="gmc" required />
              </div>
              <label for="birth_cert">PSA Birth Certificate</label>
              <div class="upload-group">
                <input type="file" name="birth_cert" id="birth_cert" required />
              </div>
            <?php endif; ?>

            <button class="button2" type="submit" name="submit_files" id="submitBtn">Submit All Files</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
