<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$employer = $_SESSION['employer'] ?? null;
if (!$employer) {
    header('Location: login.php');
    exit;
}
$viewerId = (int)($employer['id'] ?? 0);
$viewerName = $employer['company_name'] ?? 'Employer';

$conn = db();
$schedule = [];
$stmt = $conn->prepare(
    "SELECT a.interview_datetime, a.interview_type, a.interview_notes, j.job_title, u.username AS student_name, u.email AS student_email, u.strand AS student_strand
     FROM applications a
     INNER JOIN jobs j ON j.job_id = a.job_id
     LEFT JOIN users u ON u.user_id = a.student_id
     WHERE j.employer_id = ? AND a.status IN ('interview_scheduled', 'call_scheduled')"
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

// Weekly grid setup
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$slots = [
    ['07:30', '08:30'],
    ['08:30', '09:30'],
    ['09:30', '10:30'],
    ['10:30', '11:30'],
    ['11:30', '12:30'],
    ['12:30', '13:30'],
    ['13:30', '14:30'],
    ['14:30', '15:30'],
    ['15:30', '16:30'],
];

// Fill grid with interviews
$grid = [];
foreach ($schedule as $item) {
    $dt = strtotime($item['interview_datetime']);
    $day = date('l', $dt);
    $time = date('H:i', $dt);
    foreach ($slots as $i => $slot) {
        if ($time >= $slot[0] && $time < $slot[1]) {
            $grid[$day][$i][] = $item;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Employer Weekly Schedule</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 32px 24px;
        }
        .schedule-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: 0.04em;
        }
        table.schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.schedule-table th {
            background: #ffe5b4;
            color: #1f2937;
            font-size: 18px;
            font-weight: 700;
            padding: 12px 0;
            border: 1px solid #d8dee5;
        }
        table.schedule-table th:nth-child(3) {
            background: #ffb6b9;
        }
        table.schedule-table th:nth-child(4) {
            background: #ffb6ff;
        }
        table.schedule-table th:nth-child(5),
        table.schedule-table th:nth-child(6) {
            background: #d6b6ff;
        }
        table.schedule-table td {
            border: 1px solid #d8dee5;
            height: 48px;
            text-align: center;
            font-size: 16px;
            background: #fff;
        }
        table.schedule-table td.time {
            background: #f8fafc;
            font-weight: 600;
            color: #6b7280;
        }
        .interview-info {
            font-size: 13px;
            color: #0ea765;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .interview-details {
            font-size: 12px;
            color: #6b7280;
        }
        @media (max-width: 700px) {
            .container {
                padding: 12px 4px;
            }
            .schedule-title {
                font-size: 22px;
            }
            table.schedule-table th, table.schedule-table td {
                font-size: 13px;
                padding: 6px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="schedule-title">Employer Weekly Schedule</div>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th class="time"></th>
                    <?php foreach ($days as $day): ?>
                        <th><?php echo strtoupper($day); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $i => $slot): ?>
                <tr>
                    <td class="time"><?php echo $slot[0]; ?> - <?php echo $slot[1]; ?></td>
                    <?php foreach ($days as $day): ?>
                        <td>
                            <?php if (!empty($grid[$day][$i])): ?>
                                <?php foreach ($grid[$day][$i] as $item): ?>
                                    <div class="interview-info">
                                        <?php echo htmlspecialchars($item['job_title']); ?> - <?php echo htmlspecialchars($item['student_name'] ?? 'Unknown'); ?>
                                        <span class="interview-details">
                                            <br><?php echo date('g:i A', strtotime($item['interview_datetime'])); ?> - <?php echo ucfirst(htmlspecialchars($item['interview_type'])); ?>
                                            <br>Strand: <?php echo htmlspecialchars($item['student_strand'] ?? 'N/A'); ?>
                                            <br>Email: <?php echo htmlspecialchars($item['student_email'] ?? 'N/A'); ?>
                                            <?php if (!empty($item['interview_notes'])): ?>
                                                <br>Notes: <?php echo htmlspecialchars($item['interview_notes']); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="employer.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</body>
</html>
