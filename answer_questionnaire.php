<?php
session_start();
require_once 'db.php';

$token = $_GET['token'] ?? $_GET['request_id'] ?? $_GET['id'] ?? null;
if (!$token) {
    echo "Invalid or expired questionnaire link.";
    exit;
}

// Fetch request details
$stmt = $pdo->prepare("SELECT qr.*, q.title, q.questions_json, c.name as candidate_name, j.job_title FROM questionnaire_requests qr JOIN questionnaires q ON qr.questionnaire_id = q.id JOIN candidates c ON qr.candidate_id = c.id LEFT JOIN jobs j ON c.job_id = j.id WHERE qr.id = ?");
$stmt->execute([$token]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo "Questionnaire not found.";
    exit;
}

$questions = json_decode($request['questions_json'], true) ?: [];
$submitted = $request['status'] === 'Submitted';
$existing_answers = json_decode($request['answers_json'] ?? '[]', true) ?: [];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$submitted) {
    $raw_answers = $_POST['answers'] ?? [];
    $clean_answers = [];
    foreach ($raw_answers as $idx => $ans) {
        $clean_answers[$idx] = trim($ans);
    }

    $json_answers = json_encode($clean_answers);
    $update_stmt = $pdo->prepare("UPDATE questionnaire_requests SET status = 'Submitted', answers_json = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update_stmt->execute([$json_answers, $token]);

    $_SESSION['toast'] = "Thank you! Your responses have been submitted to the employer.";
    header("Location: answer_questionnaire.php?token=$token");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($request['title']) ?> - HireSense</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .q-box {
            background: var(--surf);
            border: 1px solid var(--bdr);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }
    </style>
</head>
<body style="display:flex; align-items:center; justify-content:center; min-height:100vh; padding:40px 20px;">
    <div class="bg-watermark-logo"><img src="logo/logo.png" alt="HireSense Watermark Logo"></div>
    <div class="panel" style="max-width:650px; width:100%; position:relative; z-index:2;">
        <div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
            <div style="font-size:12px; font-weight:700; color:var(--acc);">HireSense Candidate Portal</div>
            <span class="chip" style="background:var(--dim); color:var(--txt); font-size:11px;"><?= htmlspecialchars($request['job_title'] ?? 'Job Role') ?></span>
        </div>

        <div style="font-size:22px; font-weight:800; color:var(--txt); margin-bottom:6px;"><?= htmlspecialchars($request['title']) ?></div>
        <div style="font-size:13px; color:var(--mut); margin-bottom:24px;">
            Applicant: <strong><?= htmlspecialchars($request['candidate_name']) ?></strong>
        </div>

        <?php if(isset($_SESSION['toast'])): ?>
            <div style="background:rgba(0, 232, 122, 0.15); border:1px solid rgba(0, 232, 122, 0.4); border-radius:10px; padding:14px 18px; color:var(--grn); font-size:13px; font-weight:700; margin-bottom:20px;">
                🎉 <?= htmlspecialchars($_SESSION['toast']) ?>
                <?php unset($_SESSION['toast']); ?>
            </div>
        <?php endif; ?>

        <?php if($submitted): ?>
            <div style="background:rgba(0, 232, 122, 0.08); border:1px solid rgba(0, 232, 122, 0.25); border-radius:12px; padding:20px; margin-bottom:24px;">
                <div style="font-size:16px; font-weight:800; color:var(--grn); margin-bottom:6px;">✓ Questionnaire Completed</div>
                <div style="font-size:13px; color:var(--txt);">Your answers were submitted on <?= date('M d, Y H:i', strtotime($request['submitted_at'])) ?>.</div>
            </div>

            <div style="font-size:15px; font-weight:700; color:var(--txt); margin-bottom:16px;">Your Submitted Answers:</div>

            <?php foreach($questions as $idx => $q): ?>
                <div class="q-box">
                    <div style="font-size:13px; font-weight:700; color:var(--acc); margin-bottom:8px;">Q<?= $idx + 1 ?>. <?= htmlspecialchars($q) ?></div>
                    <div style="font-size:13px; color:var(--txt); background:var(--card); padding:10px 14px; border-radius:8px; border:1px solid var(--bdr);">
                        <?= nl2br(htmlspecialchars($existing_answers[$idx] ?? '-')) ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <a href="candidate_dashboard.php" class="btn-primary" style="display:inline-block; text-align:center; padding:12px 24px; text-decoration:none; margin-top:10px;">Return to Home</a>
        <?php else: ?>
            <form method="POST">
                <?php foreach($questions as $idx => $q): ?>
                    <div class="q-box">
                        <label style="display:block; font-size:13px; font-weight:700; color:var(--txt); margin-bottom:10px;">
                            Q<?= $idx + 1 ?>. <?= htmlspecialchars($q) ?> <span style="color:var(--red)">*</span>
                        </label>
                        <textarea name="answers[<?= $idx ?>]" rows="3" placeholder="Type your answer here..." required style="margin:0;"></textarea>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary" style="padding:14px; font-size:14px; width:100%; margin-top:10px;">Submit Answers to Employer &rarr;</button>
            </form>
        <?php endif; ?>
    </div>
    <script src="theme.js"></script>
</body>
</html>
