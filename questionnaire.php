<?php
require_once 'auth.php';
require_role('employer');
require_once 'notifications_helper.php';

$empNotifs = getEmployerNotifications($pdo, $_SESSION['user_id']);
$notifItems = $empNotifs['items'];
$unreadCount = $empNotifs['unread_count'];

$employer_id = $_SESSION['user_id'];

// Handle Questionnaire Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_questionnaire') {
    $del_id = $_POST['questionnaire_id'] ?? null;
    if ($del_id) {
        $del_stmt = $pdo->prepare("DELETE FROM questionnaires WHERE id = ? AND (employer_id = ? OR employer_id IS NULL)");
        $del_stmt->execute([$del_id, $employer_id]);
        $_SESSION['toast'] = "Questionnaire template deleted.";
        header("Location: questionnaire.php");
        exit;
    }
}

// Handle Questionnaire Save (Insert or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_questionnaire') {
    $q_id = $_POST['questionnaire_id'] ?? null;
    $title = trim($_POST['title'] ?? 'Screening Questionnaire');
    $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
    $raw_questions = $_POST['questions'] ?? [];
    
    $clean_questions = [];
    foreach ($raw_questions as $q) {
        $q_text = trim($q);
        if ($q_text !== '') {
            $clean_questions[] = $q_text;
        }
    }

    if (empty($clean_questions)) {
        $error = "Please add at least one question to the questionnaire.";
    } else {
        $json = json_encode($clean_questions);
        if ($q_id) {
            $update_stmt = $pdo->prepare("UPDATE questionnaires SET title = ?, job_id = ?, questions_json = ? WHERE id = ? AND (employer_id = ? OR employer_id IS NULL)");
            $update_stmt->execute([$title, $job_id, $json, $q_id, $employer_id]);
            $_SESSION['toast'] = "Questionnaire updated successfully!";
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO questionnaires (job_id, employer_id, title, questions_json) VALUES (?, ?, ?, ?)");
            $insert_stmt->execute([$job_id, $employer_id, $title, $json]);
            $_SESSION['toast'] = "New Questionnaire template saved successfully!";
        }
        header("Location: questionnaire.php");
        exit;
    }
}

// Fetch all jobs for optional assignment dropdown
$jobs_stmt = $pdo->prepare("SELECT id, job_title FROM jobs WHERE employer_id = ? OR employer_id IS NULL ORDER BY created_at DESC");
$jobs_stmt->execute([$employer_id]);
$my_jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all saved questionnaires created by employer
$q_stmt = $pdo->prepare("SELECT q.*, j.job_title FROM questionnaires q LEFT JOIN jobs j ON q.job_id = j.id WHERE q.employer_id = ? OR q.employer_id IS NULL ORDER BY q.created_at DESC");
$q_stmt->execute([$employer_id]);
$questionnaires = $q_stmt->fetchAll(PDO::FETCH_ASSOC);

// If editing a specific questionnaire via GET param
$editing_q = null;
$edit_id = $_GET['edit'] ?? null;
if ($edit_id) {
    foreach ($questionnaires as $q) {
        if ($q['id'] == $edit_id) {
            $editing_q = $q;
            break;
        }
    }
}

// If pre-selecting for a specific job via GET param
$job_id_param = $_GET['job_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Questionnaires - HireSense</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .q-item {
            background: var(--surf);
            border: 1px solid var(--bdr);
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex !important;
            flex-direction: row !important;
            gap: 10px !important;
            align-items: center !important;
        }
        .q-item input {
            flex: 1 !important;
            min-width: 0 !important;
            margin: 0 !important;
        }
        .q-item button {
            flex-shrink: 0 !important;
            width: 32px !important;
            height: 32px !important;
            min-height: 32px !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
        }

        .swipe-hint {
            display: none;
        }

        .template-card {
            background: var(--card);
            border: 1px solid var(--bdr);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        .template-card:hover {
            border-color: var(--acc);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 900px) {
            html, body {
                overflow-x: hidden !important;
                width: 100% !important;
                max-width: 100vw !important;
            }
            .swipe-hint {
                display: inline-block !important;
            }
            .grid-2 {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
                box-sizing: border-box !important;
            }
            .templates-wrapper-col {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
                box-sizing: border-box !important;
            }
            .templates-slider-container {
                display: flex !important;
                overflow-x: auto !important;
                overflow-y: hidden !important;
                scroll-snap-type: x mandatory !important;
                gap: 12px !important;
                padding: 4px 0 14px 0 !important;
                -webkit-overflow-scrolling: touch !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
            }
            .templates-slider-container::-webkit-scrollbar {
                height: 4px;
            }
            .templates-slider-container::-webkit-scrollbar-thumb {
                background: var(--bdr);
                border-radius: 4px;
            }
            .templates-slider-container .template-card {
                flex: 0 0 85% !important;
                min-width: 260px !important;
                max-width: 85% !important;
                width: 85% !important;
                scroll-snap-align: center !important;
                margin-bottom: 0 !important;
                padding: 16px !important;
                box-sizing: border-box !important;
            }
            .page-title-row {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .page-title-btn {
                width: 100% !important;
                text-align: center !important;
            }
        }

        @media (max-width: 500px) {
            main {
                padding: 16px 12px !important;
            }
            .panel {
                padding: 18px 14px !important;
            }
        }
    </style>
    <link rel="icon" type="image/png" href="logo/logo.png">
    <link rel="shortcut icon" type="image/png" href="logo/logo.png">
</head>
<body>
    <div class="bg-watermark-logo"><img src="logo/logo.png" alt="HireSense Watermark Logo"></div>
    <header>
        <div class="header-inner">
            <div style="display:flex; align-items:center; gap:10px;">
                <div class="logo-box"><img src="logo/logo.png" alt="HireSense Logo" style="width:36px; height:36px; max-width:36px; max-height:36px; object-fit:contain;"></div>
                <div>
                    <div style="font-size:15px; font-weight:800; line-height:1" class="header-brand-title">HireSense Job Portal</div>
                    <div style="font-size:9px; color:var(--mut); letter-spacing:0.8px">RECRUITMENT PLATFORM</div>
                </div>
            </div>
            
            <nav style="display:flex; gap:4px; margin-left:24px">
                <a href="employer_dashboard.php">👥 Applications & Stats</a>
                <a href="job_dashboard.php">💼 My Jobs</a>
                <a href="questionnaire.php" class="active">📋 Questionnaires</a>
                <a href="profile.php">⚙️ Settings</a>
            </nav>

            <div class="header-right-actions">
                <div class="notif-bell-wrapper" style="position:relative; margin-right:8px;">
                    <button type="button" class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications">
                        🔔
                        <?php if($unreadCount > 0): ?>
                            <span class="notif-badge" id="notifBadgeCount"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <span style="font-weight:800; font-size:13px; color:var(--txt);">Employer Notifications</span>
                            <?php if($unreadCount > 0): ?>
                                <button type="button" class="notif-mark-all" onclick="markAllNotifsRead()">Mark all as read</button>
                            <?php endif; ?>
                        </div>

                        <div class="notif-list">
                            <?php if(empty($notifItems)): ?>
                                <div style="padding:24px; text-align:center; color:var(--mut); font-size:12px;">
                                    ✨ No new application notifications
                                </div>
                            <?php else: ?>
                                <?php foreach($notifItems as $item): ?>
                                    <a href="<?= htmlspecialchars($item['link']) ?>" class="notif-item <?= $item['is_read'] ? 'read' : 'unread' ?>" onclick="markNotifRead('<?= htmlspecialchars($item['key']) ?>')">
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:3px;">
                                            <span class="notif-item-title"><?= $item['title'] ?></span>
                                            <?php if(!$item['is_read']): ?>
                                                <span class="unread-dot"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="notif-item-msg"><?= $item['message'] ?></div>
                                        <div class="notif-item-time"><?= date('M j, g:i a', strtotime($item['time'])) ?></div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <span class="user-info-text" style="font-size:12px; color:var(--mut); margin-right:10px;">Logged in as <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= ucfirst($_SESSION['user_role'] ?? 'Employer') ?>)</span>
                <a href="logout.php" class="btn-secondary" style="padding:6px 14px; font-size:12px;">Logout</a>
            </div>
        </div>
    </header>

    <main style="max-width:1100px;">
        <?php if(isset($_SESSION['toast'])): ?>
            <div style="position:fixed; top:20px; right:20px; z-index:3000; background:rgba(0, 232, 122, 0.18); border:1px solid rgba(0, 232, 122, 0.5); border-radius:10px; padding:10px 18px; color:var(--grn); font-size:13px; font-weight:700;">
                <?= htmlspecialchars($_SESSION['toast']) ?>
                <?php unset($_SESSION['toast']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div style="background:rgba(255, 77, 106, 0.1); border:1px solid rgba(255, 77, 106, 0.35); border-radius:12px; padding:11px 18px; margin-bottom:20px; color:var(--red); font-size:13px;">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="page-title-row" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
            <div>
                <h1 style="font-size:26px; font-weight:800; color:var(--txt); margin:0;">📋 Saved Questionnaire Templates</h1>
                <p style="font-size:13px; color:var(--mut); margin-top:4px; margin-bottom:0;">Build reusable questionnaire sets to dispatch to candidates with 1 click.</p>
            </div>
            <a href="#editorForm" onclick="resetForm()" class="btn-primary page-title-btn" style="padding:10px 20px; font-size:14px; text-decoration:none; width:auto; border-radius:10px;">+ Create New Questionnaire</a>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 1fr; gap:28px;">
            
            <!-- Saved Questionnaire Templates List -->
            <div class="templates-wrapper-col">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div style="font-size:16px; font-weight:800; color:var(--txt);">📁 Saved Templates Library (<?= count($questionnaires) ?>)</div>
                    <div class="swipe-hint" style="font-size:11px; color:var(--acc); font-weight:600;">Swipe cards 👈 👉</div>
                </div>

                <?php if(empty($questionnaires)): ?>
                    <div class="panel" style="text-align:center; padding:40px 20px; border-style:dashed;">
                        <div style="font-size:36px; margin-bottom:10px;">📋</div>
                        <div style="font-size:16px; font-weight:700; color:var(--txt); margin-bottom:4px;">No Questionnaires Saved Yet</div>
                        <p style="font-size:12px; color:var(--mut); margin-bottom:0;">Fill out the form on the right to save your first question template.</p>
                    </div>
                <?php else: ?>
                    <div class="templates-slider-container">
                        <?php foreach($questionnaires as $q): ?>
                            <?php 
                                $q_list = json_decode($q['questions_json'], true) ?: []; 
                            ?>
                            <div class="template-card">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                    <div>
                                        <div style="font-size:17px; font-weight:800; color:var(--txt);"><?= htmlspecialchars($q['title']) ?></div>
                                        <div style="font-size:11px; color:var(--mut); margin-top:2px;">
                                            <?= !empty($q['job_title']) ? '💼 ' . htmlspecialchars($q['job_title']) : '🌐 Reusable Template (All Jobs)' ?>
                                        </div>
                                    </div>
                                    <span class="chip" style="background:rgba(59, 130, 246, 0.1); color:var(--acc); border-color:rgba(59, 130, 246, 0.3);">
                                        <?= count($q_list) ?> Questions
                                    </span>
                                </div>

                                <div style="background:var(--surf); border:1px solid var(--bdr); border-radius:8px; padding:10px 14px; margin-top:12px; margin-bottom:14px; max-height:110px; overflow-y:auto;">
                                    <?php foreach($q_list as $idx => $q_item): ?>
                                        <div style="font-size:12px; color:var(--txt); margin-bottom:4px;">
                                            <strong style="color:var(--acc);">Q<?= $idx + 1 ?>:</strong> <?= htmlspecialchars($q_item) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                                    <a href="questionnaire.php?edit=<?= $q['id'] ?>#editorForm" class="btn-secondary" style="padding:5px 12px; font-size:11px; text-decoration:none;">✏️ Edit Template</a>
                                    
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this questionnaire template?');" style="margin:0;">
                                        <input type="hidden" name="action" value="delete_questionnaire">
                                        <input type="hidden" name="questionnaire_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="btn-secondary" style="padding:5px 12px; font-size:11px; color:var(--red); border-color:rgba(255,77,106,0.3);">🗑️ Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create / Edit Questionnaire Form -->
            <div class="panel" id="editorForm" style="height:fit-content;">
                <div class="panel-title" id="formTitle">
                    <?= $editing_q ? '✏️ Edit Questionnaire Template' : '➕ Create & Save Questionnaire' ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_questionnaire">
                    <input type="hidden" name="questionnaire_id" id="questionnaire_id" value="<?= $editing_q['id'] ?? '' ?>">

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; color:var(--mut); margin-bottom:6px; font-weight:600;">Questionnaire Title</label>
                        <input type="text" name="title" id="qTitle" value="<?= htmlspecialchars($editing_q['title'] ?? 'Pre-Interview Screening Questions') ?>" placeholder="e.g. Technical & Salary Screening" required>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-size:12px; color:var(--mut); margin-bottom:6px; font-weight:600;">Associated Job Posting (Optional)</label>
                        <select name="job_id" id="qJobId">
                            <option value="">🌐 General Template (Reusable for any Job)</option>
                            <?php foreach($my_jobs as $jb): ?>
                                <option value="<?= $jb['id'] ?>" <?= (($editing_q['job_id'] ?? $job_id_param) == $jb['id']) ? 'selected' : '' ?>>
                                    💼 <?= htmlspecialchars($jb['job_title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:18px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label style="font-size:13px; color:var(--txt); font-weight:700;">Questions List</label>
                            <button type="button" onclick="addQuestion()" class="btn-secondary" style="padding:4px 12px; font-size:11px;">+ Add Question</button>
                        </div>

                        <div id="questionsContainer">
                            <?php 
                                $edit_questions = $editing_q ? (json_decode($editing_q['questions_json'], true) ?: []) : [
                                    "What is your expected salary and notice period?",
                                    "Why are you interested in joining our team?",
                                    "What relevant technical experience do you bring to this role?"
                                ];
                            ?>
                            <?php foreach($edit_questions as $idx => $qText): ?>
                                <div class="q-item">
                                    <span style="font-size:12px; font-weight:700; color:var(--acc);" class="q-num">Q<?= $idx + 1 ?>.</span>
                                    <input type="text" name="questions[]" value="<?= htmlspecialchars($qText) ?>" placeholder="Enter question text..." required style="margin:0; flex:1; font-size:13px;">
                                    <button type="button" onclick="removeQuestion(this)" style="background:rgba(255,77,106,0.15); border:none; color:var(--red); padding:6px 10px; border-radius:6px; cursor:pointer; font-weight:700;">✕</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:24px;">
                        <button type="submit" class="btn-primary" style="flex:1; padding:11px; border-radius:10px;">💾 Save Questionnaire Template</button>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <script>
        function addQuestion(text = '') {
            const container = document.getElementById('questionsContainer');
            const count = container.children.length + 1;
            const div = document.createElement('div');
            div.className = 'q-item';
            div.innerHTML = `
                <span style="font-size:12px; font-weight:700; color:var(--acc);" class="q-num">Q${count}.</span>
                <input type="text" name="questions[]" value="${text}" placeholder="Enter question text..." required style="margin:0; flex:1; font-size:13px;">
                <button type="button" onclick="removeQuestion(this)" style="background:rgba(255,77,106,0.15); border:none; color:var(--red); padding:6px 10px; border-radius:6px; cursor:pointer; font-weight:700;">✕</button>
            `;
            container.appendChild(div);
        }

        function removeQuestion(btn) {
            btn.parentElement.remove();
            reindexQuestions();
        }

        function reindexQuestions() {
            const container = document.getElementById('questionsContainer');
            const nums = container.getElementsByClassName('q-num');
            for (let i = 0; i < nums.length; i++) {
                nums[i].innerText = 'Q' + (i + 1) + '.';
            }
        }

        function resetForm() {
            document.getElementById('questionnaire_id').value = '';
            document.getElementById('qTitle').value = 'Pre-Interview Screening Questions';
            document.getElementById('qJobId').value = '';
            document.getElementById('formTitle').innerText = '➕ Create & Save Questionnaire';
        }

        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notifDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        document.addEventListener('click', function(e) {
            const wrapper = document.querySelector('.notif-bell-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                const dropdown = document.getElementById('notifDropdown');
                if (dropdown) dropdown.classList.remove('show');
            }
        });

        function markNotifRead(key) {
            fetch('mark_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_key: key })
            }).catch(err => console.error(err));
        }

        function markAllNotifsRead() {
            fetch('mark_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all: true })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    const badge = document.getElementById('notifBadgeCount');
                    if (badge) badge.remove();
                    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                    document.querySelectorAll('.unread-dot').forEach(el => el.remove());
                }
            }).catch(err => console.error(err));
        }
    </script>
    <script src="theme.js"></script>
</body>
</html>

