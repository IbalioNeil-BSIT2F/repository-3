<?php
include('..\php\connection.php');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$username = $_SESSION['user'];

// Fetch grade status
$stmt = $conn->prepare("SELECT grade_status FROM application_status WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$grade_status = 0;
if ($row = $result->fetch_assoc()) {
    $grade_status = $row['grade_status'];
}

// Fetch admission_info
$admission_sql = $conn->prepare("SELECT entry, type, strand, lrn, program FROM admission_info WHERE username = ?");
$admission_sql->bind_param("s", $username);
$admission_sql->execute();
$admission_result = $admission_sql->get_result();
$admission_data = $admission_result->fetch_assoc();

// Fetch personal_info
$personal_sql = $conn->prepare("SELECT firstname, middlename, lastname, phonenumber, birthday, birthplace FROM personal_info WHERE username = ?");
$personal_sql->bind_param("s", $username);
$personal_sql->execute();
$personal_result = $personal_sql->get_result();
$personal_data = $personal_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admission Progress</title>
  <link rel="stylesheet" href="..\css\user.css">
  <style>
    .container-box {
        padding: 20px;
        border-radius: 10px;
        background-color: #f7f7f7;
        margin-bottom: 20px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .show-btn {
        padding: 10px 20px;
        font-size: 1rem;
        font-weight: bold;
        border: none;
        border-radius: 5px;
        background-color: #007bff;
        color: white;
        cursor: pointer;
    }
    .show-btn:disabled {
        background-color: gray;
        cursor: not-allowed;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    td {
        padding: 8px;
        border-bottom: 1px solid #ddd;
    }
    td.label {
        font-weight: bold;
        width: 30%;
        background-color: #f0f0f0;
    }
  </style>
</head>
<body>

<!-- Sidebar -->
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

<!-- Main area -->
<div class="main">
  <div class="topbar">
    <div class="left">
      <button class="backbtn" onclick="history.back()">← Back</button>
    </div>
    <div class="center"></div>
    <div class="right">
      <p>Welcome, <span><?php echo htmlspecialchars($username); ?></span></p>
      <a href="..\php\logout.php"><button class="btn font-weight-bold">Logout</button></a>
    </div>
  </div>

  <div class="content">
    <div class="maincontainer">

      <!-- Briefly Important Information -->
      <div class="container-box">
        <h2>Briefly Important Information</h2>
        <table>
          <tr>
            <td class="label">Full Name</td>
            <td><?php echo htmlspecialchars($personal_data['firstname'] . ' ' . $personal_data['middlename'] . ' ' . $personal_data['lastname']); ?></td>
          </tr>
          <tr>
            <td class="label">Phone Number</td>
            <td><?php echo htmlspecialchars($personal_data['phonenumber']); ?></td>
          </tr>
          <tr>
            <td class="label">Birthday</td>
            <td><?php echo htmlspecialchars($personal_data['birthday']); ?></td>
          </tr>
          <tr>
            <td class="label">Birthplace</td>
            <td><?php echo htmlspecialchars($personal_data['birthplace']); ?></td>
          </tr>
          <tr>
            <td class="label">Entry</td>
            <td><?php echo htmlspecialchars($admission_data['entry']); ?></td>
          </tr>
          <tr>
            <td class="label">Type</td>
            <td><?php echo htmlspecialchars($admission_data['type']); ?></td>
          </tr>
          <tr>
            <td class="label">Strand</td>
            <td><?php echo htmlspecialchars($admission_data['strand']); ?></td>
          </tr>
          <tr>
            <td class="label">LRN</td>
            <td><?php echo htmlspecialchars($admission_data['lrn']); ?></td>
          </tr>
          <tr>
            <td class="label">Program</td>
            <td><?php echo htmlspecialchars($admission_data['program']); ?></td>
          </tr>
        </table>
      </div>

      <!-- Exam Result Section -->
      <div class="container-box">
        <h2>Exam Results</h2>
        <form method="POST">
          <?php if ($grade_status == 1 || $grade_status == 2): ?>
            <button type="submit" name="show_result" class="show-btn">Show Result</button>
          <?php else: ?>
            <button type="button" class="show-btn" disabled>Show Result</button>
          <?php endif; ?>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['show_result'])) {
            if ($grade_status == 1) {
                header("Location: congrats_message.php");
                exit();
            } elseif ($grade_status == 2) {
                header("Location: sorry_message.php");
                exit();
            }
        }
        ?>
      </div>

    </div>
  </div>
</div>

</body>
</html>
