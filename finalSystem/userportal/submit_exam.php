<?php
include('../php/connection.php');
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['user'];

// Fetch the exam_id for this user
$query = "
  SELECT ue.exam_id
    FROM confirmed_exams ce
    JOIN uploaded_exams ue ON ce.schedule = ue.schedule
   WHERE ce.username = ?
   LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    die("No exam found for your account.");
}

$exam_id = $result->fetch_assoc()['exam_id'];

// Fetch all questions for this exam
$questionQuery = $conn->prepare("SELECT question_no FROM questions WHERE exam_id = ?");
$questionQuery->bind_param("i", $exam_id);
$questionQuery->execute();
$questionsResult = $questionQuery->get_result();
$allQuestions = [];
while ($row = $questionsResult->fetch_assoc()) {
    $allQuestions[] = $row['question_no'];
}
$questionQuery->close();

// Sanitize answers array
$submittedAnswers = $_POST['answers'] ?? [];

// Insert answers into exam_answers
$insertStmt = $conn->prepare("
    INSERT INTO exam_answers (email, exam_id, question_no, answer, submitted_at)
    VALUES (?, ?, ?, ?, NOW())
");

foreach ($allQuestions as $qno) {
    $answer = isset($submittedAnswers[$qno]) ? $submittedAnswers[$qno] : 'N/A';
    $insertStmt->bind_param("siss", $email, $exam_id, $qno, $answer);
    $insertStmt->execute();
}
$insertStmt->close();

// Update exam_attempts to mark submission
$updateStmt = $conn->prepare("
    UPDATE exam_attempts
       SET ended_at = NOW(), is_submitted = 1
     WHERE email = ? AND exam_id = ?
");
$updateStmt->bind_param("si", $email, $exam_id);
$updateStmt->execute();
$updateStmt->close();

// ✅ Update current_stage to 4 in check_status
$stageStmt = $conn->prepare("
    UPDATE check_status
       SET current_stage = 4
     WHERE username = ?
");
$stageStmt->bind_param("s", $email);
$stageStmt->execute();
$stageStmt->close();

// Clear exam session
unset($_SESSION['exam_started']);

// Redirect to final confirmation
header("Location: useradmission4.php");
exit();
?>
