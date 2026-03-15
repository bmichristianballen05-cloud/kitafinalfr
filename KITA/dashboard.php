<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$userId = (int)($user['user_id'] ?? 0);

// Fetch upcoming scheduled interviews
$conn = db();
$schedule = [];
if ($userId > 0) {
    $stmt = $conn->prepare(
        "SELECT a.interview_datetime, a.interview_type, a.interview_notes, j.job_title, e.company_name
         FROM applications a
         INNER JOIN jobs j ON j.job_id = a.job_id
         LEFT JOIN employers e ON e.id = j.employer_id
         WHERE a.student_id = ? AND a.status IN ('interview_scheduled', 'call_scheduled') AND a.interview_datetime >= NOW()
         ORDER BY a.interview_datetime ASC
         LIMIT 5"
    );
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $schedule[] = $row;
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Grand+Hotel&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
    <style>
        :root {
            --bg: #000000;
            --line: #262626;
            --text: #f5f5f5;
            --sub: #a8a8a8;
            --link: #8ab4ff;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Manrope", sans-serif;
            color: var(--text);
            background: var(--bg);
            overflow: hidden;
        }

        .ig-shell {
            width: min(1300px, 100%);
            height: 100vh;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 86px minmax(520px, 1fr) 360px;
            background: #000;
        }

        .ig-left {
            border-right: 1px solid var(--line);
            padding: 18px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .ig-logo {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 24px;
            text-decoration: none;
        }

        .ig-nav {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
            align-items: center;
        }

        .ig-nav li a {
            color: #f4f4f4;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            position: relative;
            transition: background-color 160ms ease, transform 160ms ease;
        }

        .ig-nav li a:hover {
            background: #121212;
            transform: scale(1.03);
        }

        .ig-nav li a.active {
            background: #141414;
        }

        .ig-icon {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            line-height: 1;
            transition: transform 160ms ease;
        }

        .ig-nav li a:hover .ig-icon {
            transform: scale(1.08);
        }

        .ig-nav li a span {
            position: absolute;
            left: 66px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            pointer-events: none;
            background: #1b1b1b;
            border: 1px solid #2e2e2e;
            color: #f0f0f0;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 8px;
            white-space: nowrap;
            transition: opacity 140ms ease;
            z-index: 5;
        }

        .ig-nav li a:hover span {
            opacity: 1;
        }

        .ig-spacer {
            flex: 1;
        }

        .ig-center {
            padding: 26px 20px;
            overflow-y: auto;
            background: #000;
        }

        .feed {
            width: min(640px, 100%);
            margin: 0 auto;
        }

        .post {
            background: #000;
            border: 0;
            border-radius: 0;
            overflow: hidden;
        }

        .post-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2px 12px;
        }

        .post-user {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
        }

        .dot-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(140deg, #ffd66b, #f58529, #dd2a7b, #8134af, #515bd4);
            border: 2px solid #111;
        }

        .post-user small {
            color: var(--sub);
            font-size: 12px;
            font-weight: 500;
            margin-left: 4px;
        }

        .post-actions-top {
            color: var(--link);
            font-weight: 700;
            font-size: 14px;
        }

        .post-media {
            aspect-ratio: 1 / 1;
            border-radius: 4px;
            background: linear-gradient(25deg, #9f8f68, #8f7f58 32%, #6f5d41 62%, #4b3a26);
            position: relative;
            overflow: hidden;
        }

        .pie-shape {
            position: absolute;
            bottom: -20px;
            left: 24%;
            width: 52%;
            aspect-ratio: 1 / 1;
            border-radius: 50%;
            background: radial-gradient(circle at 45% 42%, #f4c47d 0, #d28b3d 58%, #9a602f 100%);
            box-shadow: inset 0 10px 24px rgba(255, 223, 169, 0.24), 0 8px 22px rgba(0, 0, 0, 0.38);
        }

        .post-tools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0 6px;
            font-size: 27px;
        }

        .post-tools-left {
            display: flex;
            gap: 14px;
        }

        .post-tools i {
            cursor: pointer;
            transition: opacity 140ms ease;
        }

        .post-tools i:hover {
            opacity: 0.68;
        }

        .post-foot {
            padding: 0;
            font-size: 14px;
            line-height: 1.45;
        }

        .post-foot .likes {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .post-foot .sub {
            color: var(--sub);
            font-size: 13px;
        }

        .ig-right {
            padding: 34px 24px 18px;
            background: #000;
            overflow-y: auto;
        }

        .user-row,
        .suggest-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 5px 0;
        }

        .user-row {
            margin-bottom: 20px;
        }

        .user-info,
        .suggest-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .mini-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #bbb, #666);
            flex-shrink: 0;
        }

        .name {
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .muted {
            font-size: 13px;
            color: var(--sub);
        }

        .switch,
        .follow {
            font-size: 13px;
            color: var(--link);
            font-weight: 700;
            text-decoration: none;
        }

        .suggest-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            margin-top: 8px;
        }

        .suggest-head h3 {
            font-size: 24px;
            color: #efefef;
            font-weight: 700;
        }

        .suggest-head a {
            color: #f0f0f0;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .suggest-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .schedule-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        .schedule-head h3 {
            font-size: 16px;
            color: #efefef;
            font-weight: 700;
        }

        .schedule-view-all {
            color: var(--link);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .schedule-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .schedule-row {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 10px;
            background: #111;
            border-radius: 8px;
            border: 1px solid #333;
        }

        .schedule-title {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            margin: 0;
        }

        .schedule-time {
            font-size: 12px;
            color: var(--link);
            margin: 0;
        }

        .schedule-notes {
            font-size: 12px;
            color: var(--sub);
            margin: 0;
        }

        .ig-footer {
            margin-top: 18px;
            color: #7b7b7b;
            font-size: 12px;
            line-height: 1.65;
        }

        .ig-mobile-nav {
            display: none;
        }

        @media (max-width: 1180px) {
            .ig-shell {
                grid-template-columns: 86px 1fr;
            }

            .ig-right {
                display: none;
            }
        }

        @media (max-width: 760px) {
            body {
                overflow: auto;
            }

            .ig-shell {
                grid-template-columns: 1fr;
                height: auto;
                min-height: 100vh;
            }

            .ig-left {
                display: none;
            }

            .ig-center {
                padding: 16px 12px 72px;
            }

            .ig-mobile-nav {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                height: 56px;
                display: flex;
                align-items: center;
                justify-content: space-around;
                background: #000;
                border-top: 1px solid var(--line);
                z-index: 20;
            }

            .ig-mobile-nav a {
                text-decoration: none;
                color: #fff;
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <main class="ig-shell">
        <aside class="ig-left">
            <a class="ig-logo" href="#" aria-label="KITA">
                <i class="bi bi-globe2"></i>
            </a>
            <ul class="ig-nav">
                <li><a class="active" href="#"><i class="ig-icon bi bi-house-door-fill"></i><span>Home</span></a></li>
                <li><a href="#"><i class="ig-icon bi bi-compass"></i><span>Explore</span></a></li>
                <li><a href="#"><i class="ig-icon bi bi-chat-dots"></i><span>Messages</span></a></li>
                <li><a href="#"><i class="ig-icon bi bi-heart"></i><span>Notifications</span></a></li>
                <li><a href="#"><i class="ig-icon bi bi-plus-square"></i><span>Create</span></a></li>
            </ul>
            <div class="ig-spacer"></div>
            <ul class="ig-nav">
                <li><a href="#"><i class="ig-icon bi bi-person-circle"></i><span>Profile</span></a></li>
                <li><a href="logout.php"><i class="ig-icon bi bi-box-arrow-right"></i><span>Log out</span></a></li>
            </ul>
        </aside>

        <section class="ig-center">
            <div class="feed">
                <article class="post">
                    <header class="post-head">
                        <div class="post-user">
                            <span class="dot-avatar"></span>
                            <div>
                                tartinebake <small>- 1w</small>
                            </div>
                        </div>
                        <div class="post-actions-top">Follow&nbsp;&nbsp;...</div>
                    </header>
                    <div class="post-media">
                        <div class="pie-shape"></div>
                    </div>
                    <div class="post-tools">
                        <div class="post-tools-left">
                            <i class="bi bi-heart"></i>
                            <i class="bi bi-chat"></i>
                            <i class="bi bi-send"></i>
                        </div>
                        <i class="bi bi-bookmark"></i>
                    </div>
                    <div class="post-foot">
                        <p class="likes">37.1K likes</p>
                        <p><strong>tartinebake</strong> Fresh pie from the oven this morning.</p>
                        <p class="sub">View all 42 comments</p>
                    </div>
                </article>
            </div>
        </section>

        <aside class="ig-right">
            <div class="user-row">
                <div class="user-info">
                    <span class="mini-avatar"></span>
                    <div>
                        <p class="name"><?php echo htmlspecialchars($user['username'] ?? $user['name']); ?></p>
                        <p class="muted"><?php echo htmlspecialchars($user['strand'] ?? 'student'); ?></p>
                    </div>
                </div>
                <a class="switch" href="#">Switch</a>
            </div>

            <?php if (!empty($schedule)): ?>
            <div class="schedule-head">
                <h3>Upcoming Interviews</h3>
                <div class="schedule-actions">
                    <a href="schedule.php" class="schedule-view-all">Calendar</a>
                    <a href="schedule.php" class="schedule-view-all">View All</a>
                </div>
            </div>
            <div class="schedule-list">
                <?php foreach ($schedule as $item): ?>
                <div class="schedule-row">
                    <div class="schedule-info">
                        <p class="schedule-title"><?php echo htmlspecialchars($item['job_title']); ?> at <?php echo htmlspecialchars($item['company_name']); ?></p>
                        <p class="schedule-time"><?php echo date('M j, Y g:i A', strtotime($item['interview_datetime'])); ?> - <?php echo ucfirst($item['interview_type']); ?></p>
                        <?php if (!empty($item['interview_notes'])): ?>
                        <p class="schedule-notes"><?php echo htmlspecialchars($item['interview_notes']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="suggest-head">
                <h3>Suggested for you</h3>
                <a href="#">See All</a>
            </div>

            <div class="suggest-list">
                <div class="suggest-row">
                    <div class="suggest-info">
                        <span class="mini-avatar"></span>
                        <div><p class="name">alex.anyways18</p><p class="muted">Suggested for you</p></div>
                    </div>
                    <a class="follow" href="#">Follow</a>
                </div>
                <div class="suggest-row">
                    <div class="suggest-info">
                        <span class="mini-avatar"></span>
                        <div><p class="name">chantouflowergirl</p><p class="muted">Follows you</p></div>
                    </div>
                    <a class="follow" href="#">Follow</a>
                </div>
                <div class="suggest-row">
                    <div class="suggest-info">
                        <span class="mini-avatar"></span>
                        <div><p class="name">gwangurl77</p><p class="muted">Followed by chantouflowergirl</p></div>
                    </div>
                    <a class="follow" href="#">Follow</a>
                </div>
                <div class="suggest-row">
                    <div class="suggest-info">
                        <span class="mini-avatar"></span>
                        <div><p class="name">mishka_songs</p><p class="muted">Follows you</p></div>
                    </div>
                    <a class="follow" href="#">Follow</a>
                </div>
                <div class="suggest-row">
                    <div class="suggest-info">
                        <span class="mini-avatar"></span>
                        <div><p class="name">pierre_thecomet</p><p class="muted">Followed by mishka_songs + 6 more</p></div>
                    </div>
                    <a class="follow" href="#">Follow</a>
                </div>
            </div>

            <div class="ig-footer">
                <p>About . Help . Press . API . Jobs . Privacy . Terms</p>
                <p>Locations . Language . Meta Verified</p>
                <p>&copy; 2026 KITA FROM META</p>
            </div>
        </aside>
    </main>
    <nav class="ig-mobile-nav" aria-label="Mobile Navigation">
        <a href="#" aria-label="Home"><i class="bi bi-house-door-fill"></i></a>
        <a href="#" aria-label="Explore"><i class="bi bi-compass"></i></a>
        <a href="#" aria-label="Create"><i class="bi bi-plus-square"></i></a>
        <a href="#" aria-label="Messages"><i class="bi bi-chat-dots"></i></a>
        <a href="#" aria-label="Profile"><i class="bi bi-person-circle"></i></a>
    </nav>
</body>
</html>
