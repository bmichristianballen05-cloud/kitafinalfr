<?php
require_once __DIR__ . '/config.php';

$user = $_SESSION['user'] ?? null;
if (!$user) {
    header('Location: login.php?error=Please%20log%20in%20first');
    exit;
}

$jobs = [
    [
        'title' => 'Junior Web Developer',
        'company' => 'Greenleaf Studio',
        'location' => 'Cebu City',
        'setup' => 'On-site',
        'salary' => 'PHP 18,000 - 25,000',
        'tags' => ['HTML', 'CSS', 'JavaScript'],
        'match' => 94,
    ],
    [
        'title' => 'Data Analyst Intern',
        'company' => 'Sprout Insights',
        'location' => 'Mandaue',
        'setup' => 'Hybrid',
        'salary' => 'PHP 12,000 - 18,000',
        'tags' => ['Excel', 'SQL', 'Python'],
        'match' => 89,
    ],
    [
        'title' => 'UI/UX Design Assistant',
        'company' => 'Cebu Creative Co.',
        'location' => 'Remote',
        'setup' => 'Remote',
        'salary' => 'PHP 20,000 - 28,000',
        'tags' => ['Figma', 'Wireframing', 'Prototyping'],
        'match' => 91,
    ],
    [
        'title' => 'Content Writer',
        'company' => 'Brightline Media',
        'location' => 'Lapu-Lapu',
        'setup' => 'Hybrid',
        'salary' => 'PHP 16,000 - 23,000',
        'tags' => ['Writing', 'Research', 'SEO'],
        'match' => 85,
    ],
    [
        'title' => 'Marketing Assistant',
        'company' => 'MarketFlow PH',
        'location' => 'Cebu City',
        'setup' => 'On-site',
        'salary' => 'PHP 15,000 - 21,000',
        'tags' => ['Social Media', 'Canva', 'Copywriting'],
        'match' => 88,
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Jobs</title>
    <link rel="stylesheet" href="style.css" />
</head>
<body class="dashboard-page">
    <header class="site-header">
        <div class="logo">KITA</div>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="jobs.php" class="active">Jobs</a>
            <a href="dashboard.php">Dashboard</a>
            <span class="welcome"><?php echo htmlspecialchars($user['name']); ?></span>
            <a href="logout.php">Log out</a>
        </nav>
    </header>

    <main class="page">
        <section class="matches-header">
            <h2>All Job Matches</h2>
            <p><?php echo count($jobs); ?> openings based on your profile</p>
        </section>

        <section class="card-grid">
            <?php foreach ($jobs as $job): ?>
                <article class="job-card">
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <p class="company"><?php echo htmlspecialchars($job['company']); ?></p>
                    <p class="meta">
                        <?php echo htmlspecialchars($job['location']); ?> ·
                        <?php echo htmlspecialchars($job['setup']); ?> ·
                        <?php echo htmlspecialchars($job['salary']); ?>
                    </p>
                    <div class="job-tags">
                        <?php foreach ($job['tags'] as $tag): ?>
                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="job-score"><?php echo (int)$job['match']; ?>% Match</div>
                    <button class="btn small" type="button">Apply Now</button>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>

