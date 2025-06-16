<?php
include('..\php\connection.php');
session_start();

if (!isset($_GET['username'])) {
    die("Username not specified.");
}
$username = $_GET['username'];

// Fetch latest submitted attempt
$attempt = $conn->prepare("SELECT * FROM exam_attempts WHERE email = ? AND is_submitted = 1 ORDER BY ended_at DESC LIMIT 1");
$attempt->bind_param("s", $username);
$attempt->execute();
$attempt_result = $attempt->get_result();
if ($attempt_result->num_rows === 0) {
    die("No submitted exam found for this user.");
}
$exam_data = $attempt_result->fetch_assoc();
$exam_id = $exam_data['exam_id'];

// Fetch all questions for that exam
$questions_stmt = $conn->prepare("SELECT * FROM questions WHERE exam_id = ?");
$questions_stmt->bind_param("i", $exam_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

// Fetch user answers
$answers_stmt = $conn->prepare("SELECT question_no, answer FROM exam_answers WHERE email = ? AND exam_id = ?");
$answers_stmt->bind_param("si", $username, $exam_id);
$answers_stmt->execute();
$answers_result = $answers_stmt->get_result();
$user_answers = [];
while ($row = $answers_result->fetch_assoc()) {
    $user_answers[$row['question_no']] = $row['answer'];
}

// Grade checking
$total_questions = 0;
$correct_answers = 0;
$questions = [];

while ($row = $questions_result->fetch_assoc()) {
    $qno = $row['question_no'];
    $user_answer = $user_answers[$qno] ?? 'N/A';
    $correct_answer = $row['answer'];
    $is_correct = (strcasecmp(trim($user_answer), trim($correct_answer)) === 0);
    if ($is_correct) $correct_answers++;
    $total_questions++;

    $questions[] = [
        'question_no' => $qno,
        'question' => $row['question'],
        'opt1' => $row['opt1'],
        'opt2' => $row['opt2'],
        'opt3' => $row['opt3'],
        'opt4' => $row['opt4'],
        'correct_answer' => $correct_answer,
        'user_answer' => $user_answer,
        'is_correct' => $is_correct
    ];
}

$score = "$correct_answers / $total_questions";
$passed = $correct_answers >= ceil($total_questions * 0.5); // 50% passing threshold

// Handle confirmation of grade
if (isset($_GET['confirm']) && isset($_GET['result'])) {
    $grade_status = ($_GET['result'] === 'pass') ? 1 : 2;
    $update = $conn->prepare("UPDATE application_status SET grade_status = ? WHERE username = ?");
    $update->bind_param("is", $grade_status, $username);
    $update->execute();
    header("Location: admingrading.php");
    exit();
}
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
    button:disabled { background-color: #ccc !important; cursor: not-allowed; }
  </style>
  <script>
    // Disable reload / leave
    window.onbeforeunload = function() {
      return "Are you sure you want to leave the exam? Your progress may be lost.";
    };
    // Disable F5 and Ctrl+R
    document.addEventListener("keydown", function (e) {
      if ((e.key === "F5") || (e.ctrlKey && e.key === "r")) {
        e.preventDefault();
      }
    });
  </script>
</head>
<body class="exam-mode">

<div class="topbar">
  <div>Welcome, <?php echo htmlspecialchars($email); ?></div>
  <div>Time left: <span id="countdown"></span></div>
</div>

<div class="main">
  <div id="navigator">
    <h4>Questions</h4>
    <?php foreach ($question_numbers as $i => $no): ?>
      <button type="button" class="nav-btn" onclick="goToQuestion(<?php echo $i; ?>)">Q<?php echo $no; ?></button>
    <?php endforeach; ?>
  </div>

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
        <button type="button" id="submitBtn">Submit</button>
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

  document.getElementById('submitBtn').onclick = () => {
    const inputs = document.querySelectorAll('input[type=radio]');
    const answered = new Set();
    inputs.forEach(i => { if (i.checked) answered.add(i.name); });

    const unanswered = unansweredQuestions.filter(qNo => !answered.has(`answers[${qNo}]`));

    if (unanswered.length > 0) {
      alert("Please answer all questions before submitting the exam.");
      const firstUnanswered = document.querySelector(`[name="answers[${unanswered[0]}]"]`);
      if (firstUnanswered) {
        const card = firstUnanswered.closest('.question-card');
        if (card) {
          cards.forEach(c => c.classList.add('hide'));
          card.classList.remove('hide');
          const targetIndex = [...cards].indexOf(card);
          showQuestion(targetIndex);
        }
      }
      return;
    }
    document.getElementById('examForm').submit();
  };

  const timer = setInterval(updateTimer, 1000);
  showQuestion(0);
</script>

</body>
</html>
