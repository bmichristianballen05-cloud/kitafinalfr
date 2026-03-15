<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$user = $_SESSION['user'] ?? null;
$employer = $_SESSION['employer'] ?? null;

if (!$user && !$employer) {
    header('Location: login.php');
    exit;
}

$isEmployer = !!$employer;
$viewerId = $isEmployer ? (int)($employer['id'] ?? 0) : (int)($user['user_id'] ?? 0);
$viewerName = $isEmployer
    ? ($employer['company_name'] ?? 'Employer')
    : ($user['username'] ?? $user['full_name'] ?? 'User');

// Fetch scheduled interviews
$conn = db();
$schedule = [];
if ($isEmployer) {
    $stmt = $conn->prepare(
        "SELECT a.interview_datetime, a.interview_type, a.interview_notes, j.job_title, u.username AS student_name, u.email AS student_email, u.strand AS student_strand
         FROM applications a
         INNER JOIN jobs j ON j.job_id = a.job_id
         LEFT JOIN users u ON u.user_id = a.student_id
         WHERE j.employer_id = ? AND a.status IN ('interview_scheduled', 'call_scheduled')
         ORDER BY a.interview_datetime ASC"
    );
    if ($stmt) {
        $stmt->bind_param('i', $viewerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $schedule[] = $row;
        }
        $stmt->close();
    }
} else {
    $stmt = $conn->prepare(
        "SELECT a.interview_datetime, a.interview_type, a.interview_notes, j.job_title, e.company_name
         FROM applications a
         INNER JOIN jobs j ON j.job_id = a.job_id
         LEFT JOIN employers e ON e.id = j.employer_id
         WHERE a.student_id = ? AND a.status IN ('interview_scheduled', 'call_scheduled')
         ORDER BY a.interview_datetime ASC"
    );
    if ($stmt) {
        $stmt->bind_param('i', $viewerId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $schedule[] = $row;
        }
        $stmt->close();
    }
}

// Month calendar setup (Google Calendar-like)
$monthParam = trim((string)($_GET['month'] ?? ''));
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthStart = $monthParam . '-01';
} else {
    $monthStart = date('Y-m-01');
}

$monthTs = strtotime($monthStart);
if ($monthTs === false) {
    $monthTs = strtotime(date('Y-m-01'));
}

$year = (int) date('Y', $monthTs);
$month = (int) date('m', $monthTs);
$daysInMonth = (int) date('t', $monthTs);
$monthLabel = date('F Y', $monthTs);

$firstDayDow = (int) date('w', $monthTs); // 0=Sun
$prevMonthTs = strtotime('-1 month', $monthTs);
$nextMonthTs = strtotime('+1 month', $monthTs);
$prevMonthParam = date('Y-m', $prevMonthTs);
$nextMonthParam = date('Y-m', $nextMonthTs);

$eventsByDate = [];
foreach ($schedule as $item) {
    $dt = strtotime($item['interview_datetime']);
    if ($dt === false) {
        continue;
    }
    $key = date('Y-m-d', $dt);
    $eventsByDate[$key][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        :root {
            --bg: #f4f6f8;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --text: #1f2937;
            --muted: #6b7280;
            --line: #d8dee5;
            --accent: #0ea765;
            --accent-2: #0b8752;
            --chip: #eef2f7;
            --shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1280px;
            margin: 22px auto 36px;
            background: var(--surface);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 18px 18px 22px;
            border: 1px solid var(--line);
        }
        .cal-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 6px 14px;
            border-bottom: 1px solid var(--line);
        }
        .cal-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }
        .cal-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cal-btn {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--text);
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .cal-btn:hover {
            background: var(--chip);
        }
        .cal-btn.primary {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }
        .calendar {
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: var(--surface);
        }
        .calendar-head {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            background: var(--surface-2);
            border-bottom: 1px solid var(--line);
        }
        .calendar-head .day {
            padding: 10px 0;
            text-align: center;
            font-weight: 700;
            color: var(--muted);
            font-size: 11px;
            letter-spacing: 0.08em;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            grid-auto-rows: minmax(110px, auto);
        }
        .day-cell {
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 8px 8px 6px;
            position: relative;
            background: var(--surface);
        }
        .day-cell:nth-child(7n) {
            border-right: none;
        }
        .date-badge {
            font-size: 12px;
            font-weight: 700;
            color: var(--text);
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
        }
        .date-badge.muted {
            color: #9ca3af;
        }
        .date-badge.today {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 6px 14px rgba(14, 167, 101, 0.25);
        }
        .event {
            margin-top: 6px;
            background: color-mix(in oklab, var(--accent) 16%, var(--surface));
            border: 1px solid color-mix(in oklab, var(--accent) 35%, var(--line));
            color: var(--text);
            border-radius: 10px;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.2;
        }
        .event .title {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .event .meta {
            font-size: 11px;
            color: var(--muted);
        }
        .no-schedule {
            text-align: center;
            color: var(--muted);
            padding: 22px 0 8px;
            font-size: 15px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            margin-top: 12px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 900px) {
            .container {
                margin: 14px;
                padding: 14px;
            }
            .cal-top {
                flex-direction: column;
                align-items: flex-start;
            }
            .calendar-grid {
                grid-auto-rows: 96px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="cal-top">
            <div class="cal-title"><?php echo htmlspecialchars($monthLabel); ?></div>
            <div class="cal-actions">
                <a class="cal-btn" href="?month=<?php echo $prevMonthParam; ?>">&#8249;</a>
                <a class="cal-btn" href="?month=<?php echo date('Y-m'); ?>">Today</a>
                <a class="cal-btn" href="?month=<?php echo $nextMonthParam; ?>">&#8250;</a>
                <a class="cal-btn primary" href="<?php echo $isEmployer ? 'employer.php' : 'dashboard.php'; ?>">Back</a>
            </div>
        </div>
        <div class="calendar">
            <div class="calendar-head">
                <div class="day">SUN</div>
                <div class="day">MON</div>
                <div class="day">TUE</div>
                <div class="day">WED</div>
                <div class="day">THU</div>
                <div class="day">FRI</div>
                <div class="day">SAT</div>
            </div>
            <div class="calendar-grid">
                <?php
                    $todayKey = date('Y-m-d');
                    $cellCount = 42;
                    $dayNum = 1;
                    $prevMonthDays = (int) date('t', strtotime('first day of -1 month', $monthTs));
                    $startPrevDay = $prevMonthDays - $firstDayDow + 1;

                    for ($cell = 0; $cell < $cellCount; $cell++) {
                        $cellDay = 0;
                        $cellMonth = $month;
                        $cellYear = $year;
                        $isMuted = false;

                        if ($cell < $firstDayDow) {
                            $cellDay = $startPrevDay + $cell;
                            $prevTs = strtotime('-1 month', $monthTs);
                            $cellMonth = (int) date('m', $prevTs);
                            $cellYear = (int) date('Y', $prevTs);
                            $isMuted = true;
                        } elseif ($dayNum <= $daysInMonth) {
                            $cellDay = $dayNum;
                            $dayNum++;
                        } else {
                            $cellDay = $dayNum - $daysInMonth;
                            $dayNum++;
                            $nextTs = strtotime('+1 month', $monthTs);
                            $cellMonth = (int) date('m', $nextTs);
                            $cellYear = (int) date('Y', $nextTs);
                            $isMuted = true;
                        }

                        $cellDateKey = sprintf('%04d-%02d-%02d', $cellYear, $cellMonth, $cellDay);
                        $isToday = $cellDateKey === $todayKey;
                ?>
                    <div class="day-cell">
                        <div class="date-badge<?php echo $isMuted ? ' muted' : ''; ?><?php echo $isToday ? ' today' : ''; ?>">
                            <?php echo $cellDay; ?>
                        </div>
                        <?php if (!empty($eventsByDate[$cellDateKey])): ?>
                            <?php foreach ($eventsByDate[$cellDateKey] as $item): ?>
                                <div class="event">
                                    <div class="title">
                                        <?php if ($isEmployer): ?>
                                            <?php echo htmlspecialchars($item['job_title']); ?> - <?php echo htmlspecialchars($item['student_name'] ?? 'Unknown'); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($item['job_title']); ?> at <?php echo htmlspecialchars($item['company_name'] ?? 'Unknown Company'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta">
                                        <?php echo date('g:i A', strtotime($item['interview_datetime'])); ?> - <?php echo ucfirst(htmlspecialchars($item['interview_type'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php if (empty($schedule)): ?>
            <div class="no-schedule">No upcoming interviews scheduled.</div>
        <?php endif; ?>
        <a href="<?php echo $isEmployer ? 'employer.php' : 'dashboard.php'; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>
