<?php
include('../php/connection.php');
session_start();

// Redirect if not logged in or exam not started
if (!isset($_SESSION['user']) || !isset($_SESSION['exam_started'])) {
    header("Location: useradmission3.php");
    exit();
}

$email = $conn->real_escape_string($_SESSION['user']);

// Get exam ID
$confirmed = $conn->query("
    SELECT e.exam_id 
    FROM confirmed_exams c 
    JOIN uploaded_exams e ON c.schedule = e.schedule 
    WHERE c.username='$email'
")->fetch_assoc();
$exam_id = $confirmed['exam_id'] ?? null;

if (!$exam_id) {
    die("No exam assigned.");
}

// Prevent multiple attempts
$check = $conn->query("
    SELECT * FROM exam_attempts 
    WHERE email='$email' AND exam_id=$exam_id AND is_submitted=1
");
if ($check->num_rows > 0) {
    header("Location: useradmission4.php");
    exit();
}

// Get or create exam attempt
$attemptQuery = $conn->query("
    SELECT * FROM exam_attempts 
    WHERE email='$email' AND exam_id=$exam_id
");

if ($attemptQuery->num_rows == 0) {
    $conn->query("
        INSERT INTO exam_attempts (email, exam_id, started_at) 
        VALUES ('$email', $exam_id, NOW())
    ");
    $started_at = new DateTime();
} else {
    $attemptRow = $attemptQuery->fetch_assoc();
    $started_at_str = $attemptRow['started_at'];

    if (!$started_at_str || $started_at_str === '0000-00-00 00:00:00') {
        $started_at = new DateTime();
        $conn->query("
            UPDATE exam_attempts 
            SET started_at = NOW() 
            WHERE email='$email' AND exam_id=$exam_id
        ");
    } else {
        $started_at = new DateTime($started_at_str);
    }
}

// Get exam time in minutes and calculate seconds
$durationQuery = $conn->query("
    SELECT time_min FROM uploaded_exams 
    WHERE exam_id=$exam_id
");
$time_minutes = ($durationQuery && $row = $durationQuery->fetch_assoc()) ? (int)$row['time_min'] : 1;
$time_seconds = $time_minutes * 60;

// Calculate remaining time
$now = new DateTime();
$elapsed = $now->getTimestamp() - $started_at->getTimestamp();
$remaining_seconds = max($time_seconds - $elapsed, 0);

// Auto-submit if expired
if ($remaining_seconds === 0) {
    header("Location: submit_exam.php");
    exit();
}

// Fetch questions
$questions = $conn->query("
    SELECT * FROM questions 
    WHERE exam_id=$exam_id 
    ORDER BY question_no ASC
")->fetch_all(MYSQLI_ASSOC);
$question_numbers = array_column($questions, 'question_no');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Take Exam</title>
  <link rel="stylesheet" href="../css/userexam.css">
  <style>
    body.exam-mode { font-family: sans-serif; background: #f5f5f5; margin: 0; }
    .main { display: flex; }
    .topbar { position: fixed; top: 0; left: 0; right: 0; background: #333; color: white; padding: 10px 20px; display: flex; justify-content: space-between; }
    .content { margin-top: 60px; flex: 1; padding: 20px; }
    .question-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .hide { display: none; }
    #navigator { width: 200px; padding: 20px; background: #fff; border-right: 1px solid #ddd; }
    #navigator button { width: 100%; margin-bottom: 5px; padding: 8px; border: 1px solid #ccc; background: #eee; cursor: pointer; }
    #navigator button.active { background: #007bff; color: white; }
  </style>
</head>
<body class="exam-mode">

<div class="topbar">
  <div>Welcome, <?php echo htmlspecialchars($email); ?></div>
  <div>Time left: <span id="countdown"></span></div>
</div>

<div class="main">
  <!-- Sidebar Navigator -->
  <div id="navigator">
    <h4>Questions</h4>
    <?php foreach ($question_numbers as $i => $no): ?>
      <button type="button" class="nav-btn" onclick="goToQuestion(<?php echo $i; ?>)">Q<?php echo $no; ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Main Content -->
  <div class="content">
    <form id="examForm" method="post" action="submit_exam.php">
      <?php foreach ($questions as $index => $q): ?>
        <div class="question-card <?php echo $index > 0 ? 'hide' : ''; ?>" id="q<?php echo $index; ?>">
          <h4>Q<?php echo $q['question_no']; ?>: <?php echo htmlspecialchars($q['question']); ?></h4>
          <?php for ($i = 1; $i <= 4; $i++): $opt = 'opt'.$i; ?>
            <label>
              <input type="radio" name="answers[<?php echo $q['question_no']; ?>]" value="<?php echo htmlspecialchars($q[$opt]); ?>">
              <?php echo htmlspecialchars($q[$opt]); ?>
            </label><br>
          <?php endfor; ?>
        </div>
      <?php endforeach; ?>
      <div style="margin-top: 20px;">
        <button type="button" id="prevBtn">Previous</button>
        <button type="button" id="nextBtn">Next</button>
        <button type="submit" id="submitBtn">Submit</button>
      </div>
    </form>
  </div>
</div>

<script>
  const cards = document.querySelectorAll('.question-card');
  const navButtons = document.querySelectorAll('.nav-btn');
  const total = cards.length;
  let current = 0;
  const countdownEl = document.getElementById('countdown');
  const unansweredQuestions = <?php echo json_encode($question_numbers); ?>;
  let timeLeft = <?php echo $remaining_seconds; ?>;

  function showQuestion(index) {
    cards.forEach((c, i) => c.classList.toggle('hide', i !== index));
    navButtons.forEach(btn => btn.classList.remove('active'));
    navButtons[index].classList.add('active');
    current = index;
  }

  function goToQuestion(index) {
    showQuestion(index);
  }

  document.getElementById('nextBtn').onclick = () => {
    if (current < total - 1) showQuestion(current + 1);
  };

  document.getElementById('prevBtn').onclick = () => {
    if (current > 0) showQuestion(current - 1);
  };

  function updateTimer() {
    let min = Math.floor(timeLeft / 60);
    let sec = timeLeft % 60;
    countdownEl.textContent = `${min}m ${sec < 10 ? '0' + sec : sec}s`;
    if (timeLeft-- <= 0) {
      clearInterval(timer);
      autoSubmit();
    }
  }

  function autoSubmit() {
    const inputs = document.querySelectorAll('input[type=radio]');
    const answered = new Set();
    inputs.forEach(i => { if (i.checked) answered.add(i.name); });

    unansweredQuestions.forEach(qNo => {
      if (!answered.has(`answers[${qNo}]`)) {
        let hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = `answers[${qNo}]`;
        hidden.value = 'N/A';
        document.getElementById('examForm').appendChild(hidden);
      }
    });

    document.getElementById('examForm').submit();
  }

  const timer = setInterval(updateTimer, 1000);
  showQuestion(0);
</script>

</body>
</html>
