<?php
include('../php/connection.php');
session_start();
date_default_timezone_set('Asia/Manila');

//--- Redirect if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}
$email = $_SESSION['user'];
$msg = '';

//--- Check current stage
$stageQuery = $conn->query("SELECT current_stage, control_number FROM check_status WHERE username='$email'");
$stageData = $stageQuery->fetch_assoc();
$current_stage = (int) ($stageData['current_stage'] ?? 1);
$control_number = $stageData['control_number'] ?? 'N/A';

//--- If user already passed this stage, redirect to next
if ($current_stage >= 4) {
    header("Location: useradmission4.php");
    exit();
}

//--- Get program from admission_info
$prog = $conn->query("SELECT program FROM admission_info WHERE username='$email'")->fetch_assoc();
$program = $prog['program'] ?? '';

//--- Load schedules
$schedules = $conn->query("SELECT schedule, meeting_link FROM uploaded_exams WHERE category='$program'")
                  ->fetch_all(MYSQLI_ASSOC);

//--- Handle schedule confirmation
if (isset($_POST['confirm_schedule'])) {
    $sel = $_POST['selected_schedule'];
    $meet = $_POST['selected_link'];

    // Check if already confirmed
    $exists = $conn->query("SELECT id FROM user_chosen_schedule WHERE email='$email'")->num_rows;
    if (!$exists) {
        $stmt1 = $conn->prepare("INSERT INTO user_chosen_schedule(email, chosen_schedule) VALUES (?, ?)");
        $stmt1->bind_param("ss", $email, $sel);
        $stmt1->execute();

        $stmt2 = $conn->prepare("INSERT INTO confirmed_exams(username, schedule, meeting_link) VALUES (?, ?, ?)");
        $stmt2->bind_param("sss", $email, $sel, $meet);
        $stmt2->execute();

        $msg = "Schedule confirmed!";
    } else {
        $msg = "You've already confirmed.";
    }
}

//--- Get confirmed schedule and meeting link
$chosen = $conn->query("SELECT * FROM user_chosen_schedule WHERE email='$email'")->fetch_assoc();
$confirmed_schedule = $chosen['chosen_schedule'] ?? '';
$meeting_data = $conn->query("SELECT meeting_link FROM uploaded_exams WHERE category='$program' AND schedule='$confirmed_schedule'")
                     ->fetch_assoc();
$meeting_link_final = $meeting_data['meeting_link'] ?? '';

$now = time();
$exam_time = strtotime($confirmed_schedule);
$is_ready = $exam_time && $now >= $exam_time;

//--- Update button_activate when time has passed
if ($is_ready && isset($chosen) && !$chosen['button_activate']) {
    $conn->query("UPDATE user_chosen_schedule SET button_activate=1 WHERE email='$email'");
}

//--- Handle Start Now button
if (isset($_POST['start_exam']) && $is_ready) {
    $_SESSION['exam_started'] = true;

    // ✅ Set current_stage to 4
    $conn->query("UPDATE check_status SET current_stage = 4 WHERE username = '$email'");

    header("Location: userexam.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Admission 3</title>
  <link rel="stylesheet" href="../css/user.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
  <div>
    <div class="logo">LOGO</div>
    <div class="nav-top">
      <button class="nav-btn" onclick="window.location.href='userdashboard.php'">Dashboard</button>
      <button class="nav-btn" onclick="window.location.href='useradmission<?php echo $current_stage; ?>.php'">Admission Overview</button>
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
  <!-- Topbar -->
  <div class="topbar">
    <div class="left">
      <button class="backbtn">← Back</button>
    </div>
    <div class="center"></div>
    <div class="right">
      <p>Welcome, <span><?php echo htmlspecialchars($email); ?></span></p>
      <a href="../php/logout.php"><button class="btn font-weight-bold">Logout</button></a>
    </div>
  </div>

  <div class="content">
    <div class="card">
      <h3>Your Control Number</h3>
      <div class="control-number"><?php echo htmlspecialchars($control_number); ?></div>

      <?php if ($confirmed_schedule): ?>
        <p><strong>Confirmed Schedule:</strong> <?php echo date('F d, Y / h:i A', strtotime($confirmed_schedule)); ?></p>
      <?php else: ?>
        <form method="post">
          <select name="selected_schedule" required>
            <option value="">Choose Schedule – <?php echo htmlspecialchars($program); ?></option>
            <?php foreach ($schedules as $s): ?>
              <option value="<?php echo $s['schedule']; ?>"
                      data-link="<?php echo htmlspecialchars($s['meeting_link']); ?>">
                <?php echo date('F d, Y / h:i A', strtotime($s['schedule'])); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="selected_link" id="selected_link">
          <button type="submit" name="confirm_schedule">Confirm Schedule</button>
        </form>
      <?php endif; ?>

      <p style="color: green;"><?php echo $msg; ?></p>
    </div>

    <div class="card">
      <b>MUST READ:</b><br>
      Answer honestly under proctor rules.<br><hr>

      <?php if ($meeting_link_final): ?>
        <a
          href="<?php echo $is_ready ? htmlspecialchars($meeting_link_final) : '#'; ?>"
          class="btn-link <?php echo $is_ready ? '' : 'disabled'; ?>"
          target="_blank"
          onclick="<?php echo $is_ready ? '' : 'return false;'; ?>">
          Join Meeting
        </a>
      <?php else: ?>
        <span>No meeting link yet</span>
      <?php endif; ?>

      <form method="post">
        <button class="btn" name="start_exam" <?php echo $is_ready ? '' : 'disabled'; ?>>Start Now</button>
      </form>
    </div>
  </div>
</div>

<script>
  const sel = document.querySelector('select[name="selected_schedule"]');
  sel?.addEventListener('change', () => {
    document.getElementById('selected_link').value =
      sel.options[sel.selectedIndex].dataset.link;
  });
</script>

</body>
</html>
