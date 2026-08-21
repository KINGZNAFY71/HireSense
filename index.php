<?php
session_start();

$total_live_roles = 0;
$fresh_jobs = [];

if (file_exists('db.php')) {
    try {
        require_once 'db.php';
        // Get total count of active roles
        $countStmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status = 'Active' OR status IS NULL");
        $total_live_roles = (int) $countStmt->fetchColumn();

        // Fetch exactly 3 latest active jobs
        $jobsStmt = $pdo->query("SELECT id, job_title, department, employment_type FROM jobs WHERE status = 'Active' OR status IS NULL ORDER BY created_at DESC, id DESC LIMIT 3");
        $fresh_jobs = array_slice($jobsStmt->fetchAll(PDO::FETCH_ASSOC), 0, 3);
    } catch (\Throwable $e) {
        // Fallback gracefully if database or table is not ready yet
        $total_live_roles = 0;
        $fresh_jobs = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HireSense - Applicant Tracking & Resume Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" href="logo/logo.png">
    <link rel="shortcut icon" type="image/png" href="logo/logo.png">
    <style>
        /* CSS Variables for Light Mode (Default) and Dark Mode */
        :root {
            --bg-body: #F7F5FC;
            --txt-primary: #120D2C;
            --txt-secondary: #56546C;
            --header-bg: rgba(255, 255, 255, 0.85);
            --header-border: rgba(128, 0, 255, 0.1);
            --nav-link-color: #4A4A68;
            --nav-link-hover: #7B00FF;
            --brand-name-color: #1A1A2E;
            
            --hero-bg: radial-gradient(circle at 80% 20%, #8616FF 0%, #6300E3 45%, #42009E 100%);
            --hero-txt-title: #FFFFFF;
            --hero-txt-desc: rgba(255, 255, 255, 0.9);
            --hero-badge-bg: rgba(255, 255, 255, 0.2);
            --hero-badge-border: rgba(255, 255, 255, 0.35);
            --hero-badge-txt: #FFFFFF;
            --hero-chip-bg: rgba(255, 255, 255, 0.18);
            --hero-chip-border: rgba(255, 255, 255, 0.28);
            --hero-chip-txt: #FFFFFF;

            --btn-join-bg: #8000FF;
            --btn-join-txt: #FFFFFF;
            --btn-join-hover: #6900D4;

            --outer-card-bg: rgba(255, 255, 255, 0.2);
            --outer-card-border: rgba(255, 255, 255, 0.35);
            --inner-card-bg: #FFFFFF;
            --inner-card-txt: #1A1A2E;
            --job-card-bg: #F8F7FD;
            --job-card-border: #E8E6F5;

            --glass-card-bg: #FFFFFF;
            --glass-card-border: rgba(128, 0, 255, 0.12);
            --glass-card-shadow: 0 10px 30px rgba(128, 0, 255, 0.06);
            --glass-card-title: #120D2C;
            --glass-card-desc: #56546C;
            --icon-box-bg: rgba(128, 0, 255, 0.08);
            --icon-box-border: rgba(128, 0, 255, 0.2);

            --ambient-1: radial-gradient(circle, rgba(140, 0, 255, 0.15) 0%, rgba(90, 0, 200, 0.05) 50%, transparent 70%);
            --ambient-2: radial-gradient(circle, rgba(120, 0, 230, 0.12) 0%, rgba(40, 10, 90, 0.03) 60%, transparent 75%);

            --font-heading: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
        }

        [data-theme="dark"] {
            --bg-body: #070417;
            --txt-primary: #FFFFFF;
            --txt-secondary: rgba(255, 255, 255, 0.75);
            --header-bg: rgba(10, 6, 30, 0.85);
            --header-border: rgba(255, 255, 255, 0.08);
            --nav-link-color: rgba(255, 255, 255, 0.7);
            --nav-link-hover: #FFFFFF;
            --brand-name-color: #FFFFFF;

            --hero-bg: radial-gradient(circle at 80% 20%, #9B26FF 0%, #6F00FF 45%, #0B0320 100%);
            --hero-txt-title: #FFFFFF;
            --hero-txt-desc: rgba(255, 255, 255, 0.75);
            --hero-badge-bg: rgba(157, 38, 255, 0.15);
            --hero-badge-border: rgba(180, 80, 255, 0.35);
            --hero-badge-txt: #E2B9FF;
            --hero-chip-bg: rgba(255, 255, 255, 0.05);
            --hero-chip-border: rgba(255, 255, 255, 0.12);
            --hero-chip-txt: rgba(255, 255, 255, 0.85);

            --btn-join-bg: linear-gradient(135deg, #ECE2FF, #D9C3FF);
            --btn-join-txt: #5C00C7;
            --btn-join-hover: #FFFFFF;

            --outer-card-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.14) 0%, rgba(255, 255, 255, 0.03) 100%);
            --outer-card-border: rgba(255, 255, 255, 0.18);
            --inner-card-bg: #FFFFFF;
            --inner-card-txt: #1A1A2E;
            --job-card-bg: #FBFBFE;
            --job-card-border: #EAEAF4;

            --glass-card-bg: rgba(255, 255, 255, 0.03);
            --glass-card-border: rgba(255, 255, 255, 0.08);
            --glass-card-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            --glass-card-title: #FFFFFF;
            --glass-card-desc: rgba(255, 255, 255, 0.65);
            --icon-box-bg: rgba(157, 38, 255, 0.15);
            --icon-box-border: rgba(157, 38, 255, 0.3);

            --ambient-1: radial-gradient(circle, rgba(140, 0, 255, 0.35) 0%, rgba(90, 0, 200, 0.1) 50%, transparent 70%);
            --ambient-2: radial-gradient(circle, rgba(120, 0, 230, 0.2) 0%, rgba(40, 10, 90, 0.05) 60%, transparent 75%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--txt-primary);
            overflow-x: hidden;
            min-height: 100vh;
            line-height: 1.5;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Smooth Mesh Background Elements */
        .ambient-glow-1 {
            position: absolute;
            top: -10%;
            right: 5%;
            width: 650px;
            height: 650px;
            background: var(--ambient-1);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
            transition: background 0.4s ease;
        }

        .ambient-glow-2 {
            position: absolute;
            top: 40%;
            left: -10%;
            width: 700px;
            height: 700px;
            background: var(--ambient-2);
            filter: blur(100px);
            pointer-events: none;
            z-index: 0;
            transition: background 0.4s ease;
        }

        /* Sleek Modern Header */
        .site-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 6%;
            background: var(--header-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--header-border);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background 0.3s ease, border-color 0.3s ease;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-badge {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #9D26FF, #6800E8);
            color: #FFFFFF;
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 18px rgba(138, 43, 226, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .brand-name {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 700;
            color: var(--brand-name-color);
            letter-spacing: -0.5px;
            transition: color 0.3s ease;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .nav-link {
            color: var(--nav-link-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.25s ease;
        }

        .nav-link:hover {
            color: var(--nav-link-hover);
        }

        .btn-join {
            background: var(--btn-join-bg);
            color: var(--btn-join-txt);
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(128, 0, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .btn-join:hover {
            transform: translateY(-2px);
            background: var(--btn-join-hover);
            box-shadow: 0 8px 25px rgba(128, 0, 255, 0.35);
        }

        /* Hero Section */
        .hero-section {
            padding: 70px 6% 90px;
            position: relative;
            z-index: 2;
            background: var(--hero-bg);
            border-radius: 0 0 32px 32px;
            transition: background 0.4s ease;
            overflow: hidden;
        }

        .hero-bg-watermark {
            position: absolute;
            top: 50%;
            left: 2%;
            transform: translateY(-50%);
            width: 480px;
            max-width: 45vw;
            opacity: 0.16;
            pointer-events: none;
            z-index: 1;
            user-select: none;
        }

        .hero-bg-watermark img {
            width: 100%;
            height: auto;
            display: block;
        }

        .hero-container {
            position: relative;
            z-index: 2;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 60px;
            align-items: center;
        }

        .hero-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .badge-pill {
            background: var(--hero-badge-bg);
            border: 1px solid var(--hero-badge-border);
            backdrop-filter: blur(12px);
            color: var(--hero-badge-txt);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 28px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            background: #B354FF;
            border-radius: 50%;
            box-shadow: 0 0 10px #B354FF;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        .hero-title {
            font-family: var(--font-heading);
            font-size: 56px;
            font-weight: 700;
            line-height: 1.1;
            color: var(--hero-txt-title);
            letter-spacing: -1.5px;
            margin-bottom: 24px;
        }

        .hero-title span {
            background: linear-gradient(135deg, #FFFFFF 30%, #E3C4FF 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            font-size: 17px;
            line-height: 1.65;
            color: var(--hero-txt-desc);
            max-width: 530px;
            margin-bottom: 38px;
            font-weight: 400;
        }

        .cta-group {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 44px;
        }

        .btn-cta {
            background: linear-gradient(135deg, #9D26FF 0%, #6800E8 100%);
            color: #FFFFFF;
            padding: 15px 34px;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(128, 0, 255, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(157, 38, 255, 0.65);
            background: linear-gradient(135deg, #A836FF 0%, #7300FF 100%);
        }

        .btn-secondary-link {
            color: #FFFFFF;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.25s ease;
        }

        .btn-secondary-link:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .hero-features {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .feature-chip {
            background: var(--hero-chip-bg);
            border: 1px solid var(--hero-chip-border);
            backdrop-filter: blur(10px);
            padding: 9px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            color: var(--hero-chip-txt);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
        }

        .feature-chip:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-1px);
        }

        /* Right Card Container with Glassmorphism */
        .preview-outer-card {
            background: var(--outer-card-bg);
            border: 1px solid var(--outer-card-border);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 28px;
            padding: 16px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.3);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), background 0.3s ease;
        }

        .preview-outer-card:hover {
            transform: translateY(-4px) scale(1.01);
        }

        .preview-inner-card {
            background: var(--inner-card-bg);
            border-radius: 22px;
            padding: 30px 28px;
            color: var(--inner-card-txt);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .card-tag {
            font-size: 11px;
            font-weight: 800;
            color: #8000FF;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .card-title {
            font-family: var(--font-heading);
            font-size: 22px;
            font-weight: 700;
            color: #0E0927;
        }

        .live-roles-badge {
            background: #F2EAFF;
            color: #7B00FF;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid rgba(123, 0, 255, 0.15);
        }

        .job-item-card {
            background: var(--job-card-bg);
            border: 1px solid var(--job-card-border);
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 14px;
            transition: all 0.25s ease;
            text-decoration: none;
            display: block;
        }

        .job-item-card:hover {
            border-color: #9D26FF;
            transform: translateX(4px);
            background: #FFFFFF;
            box-shadow: 0 6px 20px rgba(128, 0, 255, 0.08);
        }

        .job-item-title {
            font-size: 16px;
            font-weight: 700;
            color: #120D2C;
            margin-bottom: 4px;
        }

        .job-item-meta {
            font-size: 13px;
            color: #6C6C8A;
            font-weight: 500;
        }

        .snapshot-box {
            background: #0D0826;
            border-radius: 16px;
            padding: 20px;
            color: #FFFFFF;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .snapshot-box::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(157, 38, 255, 0.3), transparent 70%);
            pointer-events: none;
        }

        .snapshot-title {
            font-size: 13px;
            font-weight: 700;
            color: #CBA4FF;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .snapshot-quote {
            font-size: 13px;
            color: #E2E2F0;
            line-height: 1.55;
            font-style: italic;
        }

        /* Modern Grid Feature Section */
        .features-section {
            max-width: 1280px;
            margin: 0 auto;
            padding: 60px 6% 100px;
            position: relative;
            z-index: 2;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-tag {
            color: #8000FF;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        [data-theme="dark"] .section-tag {
            color: #B866FF;
        }

        .section-title {
            font-family: var(--font-heading);
            font-size: 38px;
            font-weight: 700;
            color: var(--txt-primary);
            transition: color 0.3s ease;
        }

        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .glass-card {
            background: var(--glass-card-bg);
            border: 1px solid var(--glass-card-border);
            border-radius: 24px;
            padding: 36px 30px;
            box-shadow: var(--glass-card-shadow);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .glass-card:hover {
            border-color: rgba(157, 38, 255, 0.4);
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(128, 0, 255, 0.15);
        }

        .card-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: var(--icon-box-bg);
            border: 1px solid var(--icon-box-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 22px;
            transition: background 0.3s ease;
        }

        .glass-card-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 700;
            color: var(--glass-card-title);
            margin-bottom: 12px;
            transition: color 0.3s ease;
        }

        .glass-card-desc {
            font-size: 14px;
            color: var(--glass-card-desc);
            line-height: 1.6;
            transition: color 0.3s ease;
        }

        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 50px;
            }
            .hero-title {
                font-size: 44px;
            }
            .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 840px) {
            html, body {
                overflow-x: hidden !important;
                width: 100% !important;
            }
            .site-header {
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 10px;
                width: 100%;
                box-sizing: border-box;
            }
            .brand-logo {
                flex-shrink: 0;
            }
            .nav-links {
                display: flex;
                gap: 8px;
                align-items: center;
                overflow-x: auto;
                max-width: 100%;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
            }
            .nav-links::-webkit-scrollbar {
                display: none;
            }
            .nav-link {
                white-space: nowrap;
                font-size: 13px;
            }
            .btn-join {
                padding: 8px 16px;
                font-size: 13px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .hero-section {
                padding: 36px 16px 50px;
                overflow-x: hidden;
            }
            .hero-bg-watermark {
                width: 260px;
                max-width: 80vw;
                opacity: 0.1;
                top: 15%;
                left: 50%;
                transform: translateX(-50%);
            }
            .hero-container {
                grid-template-columns: 1fr;
                gap: 36px;
                width: 100%;
            }
            .hero-title {
                font-size: 32px !important;
                line-height: 1.25 !important;
                letter-spacing: -0.5px !important;
                margin-bottom: 16px;
            }
            .hero-description {
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            .cta-group {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }
            .btn-cta, .btn-secondary-link {
                width: 100%;
                justify-content: center;
                text-align: center;
                box-sizing: border-box;
                padding: 14px 20px;
            }
            .hero-features {
                gap: 8px;
                flex-wrap: wrap;
            }
            .feature-chip {
                font-size: 12px;
                padding: 7px 14px;
            }
            .preview-outer-card {
                padding: 12px;
                border-radius: 20px;
            }
            .preview-inner-card {
                padding: 18px 16px;
            }
            .grid-3 {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .section-title {
                font-size: 26px !important;
            }
        }

        @media (max-width: 500px) {
            .hero-title {
                font-size: 27px !important;
            }
            .badge-pill {
                font-size: 12px;
                padding: 6px 14px;
            }
            .site-header {
                padding: 10px 14px;
            }
            .brand-name {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <?php if(isset($_SESSION['toast'])): ?>
        <div style="position:fixed; top:20px; right:20px; z-index:3000; background:rgba(0, 232, 122, 0.18); border:1px solid rgba(0, 232, 122, 0.5); border-radius:10px; padding:10px 18px; color:#00E87A; font-size:13px; font-weight:700; font-family:sans-serif; backdrop-filter:blur(10px);">
            ✓ <?= htmlspecialchars($_SESSION['toast']) ?>
            <?php unset($_SESSION['toast']); ?>
        </div>
    <?php endif; ?>

    <!-- Ambient Glowing Backdrops -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <!-- Header -->
    <header class="site-header">
        <a href="index.php" class="brand-logo">
            <div class="logo-badge" style="background:transparent; border:none; padding:0; overflow:hidden;"><img src="logo/logo.png" alt="HireSense Logo" style="width:100%; height:100%; object-fit:contain;"></div>
            <span class="brand-name">HireSense</span>
        </a>

        <nav class="nav-links">
            <a href="#features" class="nav-link">How it works</a>
            <a href="register.php" class="nav-link">Join HireSense</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <span class="nav-link" style="font-weight: 600;">Hello, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                <?php if($_SESSION['user_role'] === 'employer'): ?>
                    <a href="employer_dashboard.php" class="btn-join">Dashboard</a>
                <?php elseif($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin_dashboard.php" class="btn-join">Dashboard</a>
                <?php else: ?>
                    <a href="candidate_dashboard.php" class="btn-join">Dashboard</a>
                <?php endif; ?>
                <a href="logout.php" class="nav-link">Log out</a>
            <?php else: ?>
                <a href="login.php" class="nav-link">Log in</a>
                <a href="jobs.php" class="btn-join">Browse jobs</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-bg-watermark">
            <img src="logo/logo.png" alt="HireSense Watermark Logo">
        </div>
        <div class="hero-container">
            
            <!-- Left Column: Copy & CTAs -->
            <div class="hero-content">
                <div class="badge-pill">
                    <span class="badge-dot"></span>
                   Designed for real success.
                </div>

                <h1 class="hero-title">
                    Find <span>sharper jobs & candidates</span> without losing the human touch.
                </h1>

                <p class="hero-description">
                    HireSense helps hiring teams review resumes using Claude with context, structure, and clearer interview prompts while keeping every decision human-led.
                </p>

                <div class="cta-group">
                    <a href="apply.php" class="btn-cta">
                        Explore Open Roles
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                    </a>
                    <a href="jobs.php" class="btn-secondary-link">
                        Try Resume Check
                    </a>
                </div>

                <div class="hero-features">
                    <div class="feature-chip">
                        ⚡ Faster shortlist prep
                    </div>
                    <div class="feature-chip">
                        🛡️ Privacy-safe summaries
                    </div>
                    <div class="feature-chip">
                        🤝 Human decisions, always
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive Card Preview -->
            <div class="preview-outer-card">
                <div class="preview-inner-card">
                    <div class="card-header">
                        <div>
                            <div class="card-tag">THIS WEEK</div>
                            <div class="card-title">Fresh opportunities</div>
                        </div>
                        <div class="live-roles-badge"><?= $total_live_roles ?> <?= $total_live_roles === 1 ? 'live role' : 'live roles' ?></div>
                    </div>

                    <?php if (!empty($fresh_jobs)): ?>
                        <?php foreach ($fresh_jobs as $job): ?>
                            <a href="apply.php?job_id=<?= (int)$job['id'] ?>" class="job-item-card">
                                <div class="job-item-title"><?= htmlspecialchars($job['job_title']) ?></div>
                                <div class="job-item-meta">
                                    <?= htmlspecialchars($job['department'] ?? 'General') ?>
                                    <?= !empty($job['employment_type']) ? ' • ' . htmlspecialchars($job['employment_type']) : '' ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <a href="jobs.php" class="job-item-card">
                            <div class="job-item-title">No active roles published</div>
                            <div class="job-item-meta">Check back soon for new opportunities</div>
                        </a>
                    <?php endif; ?>

                    <div class="snapshot-box">
                        <div class="snapshot-title">
                            ✨ Candidate snapshot preview
                        </div>
                        <div class="snapshot-quote">
                            "Strong CRM background with a calm approach to escalations and customer care."
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Sleek Grid Feature Breakdown -->
    <section class="features-section" id="features">
        <div class="section-header">
            <div class="section-tag">ENGINEERED FOR SPEED & EMPATHY</div>
            <h2 class="section-title">Why modern teams choose HireSense</h2>
        </div>

        <div class="grid-3">
            <div class="glass-card">
                <div class="card-icon-box">🧠</div>
                <h3 class="glass-card-title">Structured Resume Insights</h3>
                <p class="glass-card-desc">Instead of unorganized applicant files, our system extracts resume text cleanly, providing structured summaries of candidate experience and capability.</p>
            </div>

            <div class="glass-card">
                <div class="card-icon-box">🎯</div>
                <h3 class="glass-card-title">Custom Questionnaires</h3>
                <p class="glass-card-desc">Send tailored screening questionnaires specific to each position to evaluate skills and work history before inviting candidates to an interview.</p>
            </div>

            <div class="glass-card">
                <div class="card-icon-box">🔒</div>
                <h3 class="glass-card-title">Bias-Free Screening</h3>
                <p class="glass-card-desc">Automatically strip PII to evaluate talent purely on merit, keeping hiring decisions transparent, ethical, and human-guided.</p>
            </div>
        </div>
    </section>

    <script src="theme.js"></script>
</body>
</html>


