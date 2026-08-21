<?php
require_once 'auth.php';
require_once 'ai.php';
require_once 'notifications_helper.php';
require_login();

$user_id = $_SESSION['user_id'];
$userNotifs = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'candidate') 
    ? getCandidateNotifications($pdo, $user_id) 
    : getEmployerNotifications($pdo, $user_id);
$notifItems = $userNotifs['items'];
$unreadCount = $userNotifs['unread_count'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (!empty($name) && !empty($email)) {
            // Check if email is already taken by another user
            $check_email = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->execute([$email, $user_id]);
            if ($check_email->fetch()) {
                $_SESSION['error'] = "The email address '$email' is already registered by another account.";
            } else {
                $up = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                if ($up->execute([$name, $email, $user_id])) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['toast'] = "Account name and email address updated successfully!";
                } else {
                    $_SESSION['error'] = "Failed to update profile information.";
                }
            }
        } else {
            $_SESSION['error'] = "Name and Email cannot be empty.";
        }
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'update_api_key') {
        $api_key = trim($_POST['api_key'] ?? '');
        $model = trim($_POST['ai_model'] ?? 'claude-3-5-sonnet-20241022');
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $smtp_port = trim($_POST['smtp_port'] ?? '587');
        $smtp_from = trim($_POST['smtp_from'] ?? '');

        $config_file = __DIR__ . '/config.json';
        $curr_config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
        if (!is_array($curr_config)) $curr_config = [];

        if (!empty($api_key)) {
            $curr_config['api_key'] = $api_key;
            $curr_config['ai_model'] = $model;
        }
        $curr_config['smtp_host'] = $smtp_host;
        $curr_config['smtp_user'] = $smtp_user;
        if (!empty($smtp_pass)) {
            $curr_config['smtp_pass'] = $smtp_pass;
        }
        $curr_config['smtp_port'] = $smtp_port;
        $curr_config['smtp_from'] = $smtp_from;

        file_put_contents($config_file, json_encode($curr_config, JSON_PRETTY_PRINT));
        $_SESSION['toast'] = "Settings & PHPMailer Mailer Config saved!";
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'test_smtp') {
        $target_email = trim($_POST['test_target_email'] ?? '');
        if (empty($target_email)) {
            $target_email = 'nuriman.kadir01@s.unikl.edu.my';
        }
        
        require_once __DIR__ . '/mailer.php';
        $res = send_questionnaire_email(
            $user['name'],
            $target_email,
            "Software Engineer Position",
            "Screening Questionnaire Assessment",
            "test_token_" . time(),
            [
                "What is your expected salary and notice period?",
                "What relevant technical experience do you bring to this role?"
            ]
        );
        if (!empty($res['success'])) {
            $_SESSION['toast'] = "Test questionnaire email dispatched to " . htmlspecialchars($target_email) . " via PHPMailer!";
        } else {
            $_SESSION['error'] = "PHPMailer SMTP Test Failed: " . ($res['error'] ?? 'Unknown error');
        }
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'update_password') {
        $current_pw = $_POST['current_password'];
        $new_pw = $_POST['new_password'];
        
        if (password_verify($current_pw, $user['password_hash'])) {
            $hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $user_id])) {
                $_SESSION['toast'] = "Password updated successfully.";
            } else {
                $_SESSION['error'] = "Failed to update password.";
            }
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'upload_picture') {
        $file = $_FILES['profile_picture'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_INI_SIZE || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $_SESSION['error'] = "Uploaded image exceeds the maximum allowed size limit (10MB).";
        } elseif ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $upload_dir = 'uploads/profiles/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                
                if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                    $_SESSION['error'] = "Directory 'uploads/profiles/' is not writable. Please check FTP permissions.";
                } else {
                    $path = $upload_dir . uniqid() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->execute([$path, $user_id]);
                        $_SESSION['toast'] = "Profile picture updated.";
                    } else {
                        $_SESSION['error'] = "Failed to save profile picture.";
                    }
                }
            } else {
                $_SESSION['error'] = "Invalid image format. Use JPG, PNG or WEBP.";
            }
        }
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'upload_resume') {
        $file = $_FILES['default_resume'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_INI_SIZE || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
            $_SESSION['error'] = "Uploaded resume exceeds the maximum allowed size limit (10MB).";
        } elseif ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $upload_dir = 'uploads/resumes/';
                if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
                
                if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
                    $_SESSION['error'] = "Directory 'uploads/resumes/' is not writable. Please check FTP permissions.";
                } else {
                    $path = $upload_dir . uniqid() . '_' . basename($file['name']);
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $stmt = $pdo->prepare("UPDATE users SET default_resume = ? WHERE id = ?");
                        $stmt->execute([$path, $user_id]);
                        $_SESSION['toast'] = "Default PDF resume saved successfully.";
                    } else {
                        $_SESSION['error'] = "Failed to save uploaded resume file.";
                    }
                }
            } else {
                $_SESSION['error'] = "Only PDF resumes are supported.";
            }
        }
        header("Location: profile.php");
        exit;
    }
    elseif ($action === 'delete_account') {
        $confirm_text = trim($_POST['confirm_delete'] ?? '');
        if ($confirm_text === 'DELETE') {
            // Unlink candidate profile picture if exists
            if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                @unlink($user['profile_picture']);
            }
            // Unlink candidate default resume if exists
            if (!empty($user['default_resume']) && file_exists($user['default_resume'])) {
                @unlink($user['default_resume']);
            }
            // Delete candidate applications
            $del_cand = $pdo->prepare("DELETE FROM candidates WHERE user_id = ? OR (email = ? AND email IS NOT NULL AND email != '')");
            $del_cand->execute([$user_id, $user['email']]);

            // Delete user record from users table
            $del_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del_user->execute([$user_id]);

            // Clear session and redirect with toast
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['toast'] = "Your candidate account has been permanently deleted.";
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['error'] = "Please type 'DELETE' in capital letters to confirm account deletion.";
            header("Location: profile.php");
            exit;
        }
    }
}

$cand_applications = [];
if ($user['role'] === 'candidate') {
    try {
        $c_stmt = $pdo->prepare("
            SELECT c.*, j.job_title, j.department, u.name as employer_name 
            FROM candidates c 
            JOIN jobs j ON c.job_id = j.id 
            LEFT JOIN users u ON j.employer_id = u.id 
            WHERE c.user_id = ? 
            ORDER BY c.created_at DESC
        ");
        $c_stmt->execute([$user_id]);
        $cand_applications = $c_stmt->fetchAll();
    } catch (\Throwable $e) {
        $cand_applications = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile & Account Settings - HireSense</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-pic {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--acc);
            margin-bottom: 12px;
            background: var(--dim);
            box-shadow: var(--shadow-md);
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
                <?php if($user['role'] === 'candidate'): ?>
                    <a href="jobs.php">📋 Job Board</a>
                    <a href="candidate_dashboard.php">👤 My Applications</a>
                    <a href="profile.php" class="active">⚙️ Profile Settings</a>
                <?php elseif($user['role'] === 'employer'): ?>
                    <a href="employer_dashboard.php">👥 Applications & Stats</a>
                    <a href="job_dashboard.php">💼 My Jobs</a>
                    <a href="questionnaire.php">📋 Questionnaires</a>
                    <a href="profile.php" class="active">⚙️ Settings</a>
                <?php else: ?>
                    <a href="admin_dashboard.php">⚙️ Dashboard</a>
                    <a href="profile.php" class="active">⚙️ Profile Settings</a>
                <?php endif; ?>
            </nav>

            <div class="header-right-actions">
                <?php if($user['role'] === 'candidate'): ?>
                    <div class="notif-bell-wrapper" style="position:relative; margin-right:8px;">
                        <button type="button" class="notif-bell-btn" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications">
                            🔔
                            <?php if($unreadCount > 0): ?>
                                <span class="notif-badge" id="notifBadgeCount"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <span style="font-weight:800; font-size:13px; color:var(--txt);">Notifications</span>
                                <?php if($unreadCount > 0): ?>
                                    <button type="button" class="notif-mark-all" onclick="markAllNotifsRead()">Mark all as read</button>
                                <?php endif; ?>
                            </div>

                            <div class="notif-list">
                                <?php if(empty($notifItems)): ?>
                                    <div style="padding:24px; text-align:center; color:var(--mut); font-size:12px;">
                                        ✨ No notifications right now
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
                <?php endif; ?>

                <span class="user-info-text" style="font-size:12px; color:var(--mut); margin-right:10px;">Logged in as <?= htmlspecialchars($user['name']) ?> (<?= ucfirst($user['role']) ?>)</span>
                <a href="logout.php" class="btn-secondary" style="padding:6px 14px; font-size:12px;">Logout</a>
            </div>
        </div>
    </header>
    
    <main style="max-width:900px;">
        <?php if(isset($_SESSION['toast'])): ?>
            <div style="position:fixed; top:20px; right:20px; z-index:3000; background:rgba(0, 232, 122, 0.18); border:1px solid rgba(0, 232, 122, 0.5); border-radius:10px; padding:10px 18px; color:var(--grn); font-size:13px; font-weight:700;">
                <?= htmlspecialchars($_SESSION['toast']) ?>
                <?php unset($_SESSION['toast']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div style="background:rgba(255, 77, 106, 0.1); border:1px solid rgba(255, 77, 106, 0.35); border-radius:12px; padding:11px 18px; margin-bottom:20px; color:var(--red); font-size:13px;">
                ⚠️ <?= htmlspecialchars($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div style="font-size:24px; font-weight:800; margin-bottom:20px; color:var(--txt);">
            ⚙️ <?= $user['role'] === 'employer' ? 'Employer Account Settings' : 'Candidate Profile Settings' ?>
        </div>
        
        <!-- Top Profile Avatar Header Card -->
        <div class="panel" style="margin-bottom:24px; display:flex; gap:28px; align-items:center; flex-wrap:wrap;">
            <div style="text-align:center;">
                <?php if(!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                    <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-pic">
                <?php else: ?>
                    <div class="profile-pic" style="display:flex; align-items:center; justify-content:center; font-size:36px; color:#fff; background:linear-gradient(135deg, #8000FF, #00E87A);">
                        <?= strtoupper(substr($user['name'] ?: 'U', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_picture">
                    <input type="file" name="profile_picture" id="pic_input" accept="image/png, image/jpeg, image/webp" style="display:none" onchange="this.form.submit()">
                    <button type="button" class="btn-secondary" style="padding:5px 12px; font-size:11px;" onclick="document.getElementById('pic_input').click()">Change Avatar</button>
                </form>
            </div>
            <div>
                <div style="font-size:22px; font-weight:800; color:var(--txt); margin-bottom:4px;"><?= htmlspecialchars($user['name']) ?></div>
                <div style="font-size:14px; color:var(--mut); margin-bottom:10px;"><?= htmlspecialchars($user['email']) ?></div>
                <span class="chip" style="background:rgba(59, 130, 246, 0.1); border-color:rgba(59, 130, 246, 0.3); color:var(--acc); font-weight:700;">
                    <?= strtoupper(htmlspecialchars($user['role'])) ?> ACCOUNT
                </span>
            </div>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 1fr; gap:24px;">
            <!-- Profile Info Form -->
            <div class="panel">
                <div class="panel-title">👤 Account Details</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:6px;">Full Name / Company Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:6px;">Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <button type="submit" class="btn-primary">Save Profile Info &rarr;</button>
                </form>
            </div>

            <!-- Password Security Form -->
            <div class="panel">
                <div class="panel-title">🔒 Password Security</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div style="margin-bottom:14px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:4px;">Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:4px;">New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn-primary">Update Password &rarr;</button>
                </form>
            </div>

            <?php if($user['role'] === 'employer'): ?>
                <?php 
                    $c_file = __DIR__ . '/config.json';
                    $cfg = file_exists($c_file) ? json_decode(file_get_contents($c_file), true) : [];
                    if (!is_array($cfg)) $cfg = [];
                ?>
                <!-- Employer API & Mailer Configuration Card -->
                <div class="panel" style="grid-column: 1 / -1;">
                    <div class="panel-title">🔑 Anthropic AI & PHPMailer SMTP Settings</div>
                    <p style="font-size:13px; color:var(--mut); margin-bottom:16px;">Configure your AI Screening API key and optional SMTP mailer credentials for candidate questionnaire notifications.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_api_key">
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:6px;">Anthropic API Key</label>
                                <input type="password" name="api_key" placeholder="sk-ant-api..." value="<?= htmlspecialchars(get_api_key() ?? '') ?>" required>
                            </div>
                            
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:var(--mut); margin-bottom:6px;">Claude AI Model</label>
                                <select name="ai_model">
                                    <option value="claude-3-5-sonnet-20241022" <?= (($_SESSION['ai_model'] ?? '') === 'claude-3-5-sonnet-20241022') ? 'selected' : '' ?>>Claude 3.5 Sonnet (Recommended)</option>
                                    <option value="claude-3-5-haiku-20241022" <?= (($_SESSION['ai_model'] ?? '') === 'claude-3-5-haiku-20241022') ? 'selected' : '' ?>>Claude 3.5 Haiku (Fast)</option>
                                    <option value="claude-3-haiku-20240307" <?= (($_SESSION['ai_model'] ?? '') === 'claude-3-haiku-20240307') ? 'selected' : '' ?>>Claude 3 Haiku</option>
                                </select>
                            </div>
                        </div>

                        <div style="border-top:1px dashed var(--bdr); padding-top:16px; margin-top:16px;">
                            <div style="font-size:13px; font-weight:700; color:var(--txt); margin-bottom:6px;">📧 PHPMailer SMTP Settings (For Candidate Email Notifications)</div>
                            <div style="background:rgba(59, 130, 246, 0.08); border:1px solid rgba(59, 130, 246, 0.25); border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:12px; color:var(--txt); line-height:1.5;">
                                💡 <strong>Gmail Users:</strong> Google requires a 16-character <strong>App Password</strong> instead of your personal login password.<br>
                                Generate one in your Google Account: <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--acc); font-weight:700;">myaccount.google.com/apppasswords</a> (2-Step Verification must be ON).
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:600; color:var(--mut); margin-bottom:4px;">SMTP Host</label>
                                    <input type="text" name="smtp_host" placeholder="e.g. smtp.gmail.com" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>">
                                </div>
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:600; color:var(--mut); margin-bottom:4px;">SMTP User / Email</label>
                                    <input type="text" name="smtp_user" placeholder="your.email@gmail.com" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>">
                                </div>
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:600; color:var(--mut); margin-bottom:4px;">SMTP Password (App Password)</label>
                                    <input type="password" name="smtp_pass" placeholder="16-character App Password" value="<?= htmlspecialchars($cfg['smtp_pass'] ?? '') ?>">
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px;">
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:600; color:var(--mut); margin-bottom:4px;">SMTP Port</label>
                                    <input type="number" name="smtp_port" placeholder="587" value="<?= htmlspecialchars($cfg['smtp_port'] ?? '587') ?>">
                                </div>
                                <div>
                                    <label style="display:block; font-size:11px; font-weight:600; color:var(--mut); margin-bottom:4px;">Sender Email (From)</label>
                                    <input type="email" name="smtp_from" placeholder="your.email@gmail.com" value="<?= htmlspecialchars($cfg['smtp_from'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div style="display:flex; gap:12px; align-items:center;">
                            <button type="submit" class="btn-primary" style="width:auto; padding:10px 24px;">Save Configuration &rarr;</button>
                        </div>
                    </form>

                    <form method="POST" onsubmit="return confirm('Send test questionnaire email to ' + this.test_target_email.value + '?');" style="margin-top:16px; border-top:1px dashed var(--bdr); padding-top:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="action" value="test_smtp">
                        <div style="flex:1; min-width:240px;">
                            <label style="display:block; font-size:11px; font-weight:700; color:var(--mut); margin-bottom:4px;">Test Email Target Recipient</label>
                            <input type="email" name="test_target_email" value="nuriman.kadir01@s.unikl.edu.my" required onkeydown="if(event.key === 'Enter'){ event.preventDefault(); return false; }" style="padding:8px 12px; font-size:12px;">
                        </div>
                        <div style="margin-top:18px;">
                            <button type="submit" class="btn-secondary" style="padding:9px 18px; font-size:12px; font-weight:700; white-space:nowrap;">🧪 Send Test Email &rarr;</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Candidate Resume Section -->

                <!-- Candidate Resume Section -->
                <div class="panel" style="grid-column: 1 / -1;">
                    <div class="panel-title">📄 Saved Default Resume</div>
                    <p style="font-size:13px; color:var(--mut); margin-bottom:16px;">Upload a default PDF resume to apply for jobs faster without re-uploading every time.</p>
                    
                    <?php if($user['default_resume']): ?>
                        <div style="background:rgba(0, 232, 122, 0.08); border:1px solid rgba(0, 232, 122, 0.25); border-radius:8px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; gap:12px;">
                            <span style="font-size:24px;">📄</span>
                            <div style="flex:1;">
                                <div style="font-size:13px; font-weight:700; color:var(--grn);">Default Resume Saved</div>
                                <div style="font-size:11px; color:var(--mut);"><?= htmlspecialchars(basename($user['default_resume'])) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" style="display:flex; gap:12px; align-items:center;">
                        <input type="hidden" name="action" value="upload_resume">
                        <input type="file" name="default_resume" accept=".pdf" required style="flex:1;">
                        <button type="submit" class="btn-primary" style="width:auto;">Upload Resume</button>
                    </form>
                </div>

                <!-- Danger Zone: Account Deletion Panel -->
                <div class="panel" style="grid-column: 1 / -1; border-color: rgba(255, 77, 106, 0.35); background: rgba(255, 77, 106, 0.03);">
                    <div class="panel-title" style="color: var(--red);">⚠️ Danger Zone: Permanent Account Deletion</div>
                    <p style="font-size:13px; color:var(--mut); margin-bottom:16px;">
                        Deleting your candidate account will permanently remove your profile, saved default resume, avatar, and all submitted job applications. 
                        <strong>This action is immediate and cannot be undone.</strong>
                    </p>

                    <form method="POST" onsubmit="return confirm('Are you completely sure you want to permanently delete your candidate account? All your data will be removed forever.');">
                        <input type="hidden" name="action" value="delete_account">
                        <div style="margin-bottom:14px; max-width:400px;">
                            <label style="display:block; font-size:11px; font-weight:700; color:var(--red); margin-bottom:6px;">Type DELETE to confirm account deletion:</label>
                            <input type="text" name="confirm_delete" placeholder="DELETE" required style="padding:9px 12px; font-size:13px; font-weight:700; letter-spacing:1px; border-color: rgba(255, 77, 106, 0.4);">
                        </div>
                        <button type="submit" class="btn-secondary" style="padding:10px 20px; font-size:13px; font-weight:800; color:var(--red); border-color:rgba(255, 77, 106, 0.5); background:rgba(255, 77, 106, 0.1);">
                            🗑️ Permanently Delete My Account
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <script>
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

