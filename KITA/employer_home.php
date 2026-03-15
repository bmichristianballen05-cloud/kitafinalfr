<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KITA | Employer Home</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Space+Grotesk:wght@500;700&display=swap');

        :root {
            --bg: #05150f;
            --bg2: #08261a;
            --card: rgba(14, 41, 31, 0.72);
            --line: rgba(101, 202, 145, 0.28);
            --text: #e8fff2;
            --muted: #9cc5b2;
            --accent: #35d98c;
            --accent2: #1dae67;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at 14% 8%, rgba(53, 217, 140, 0.26), transparent 34%),
                radial-gradient(circle at 84% 88%, rgba(22, 132, 84, 0.26), transparent 35%),
                linear-gradient(165deg, var(--bg) 0%, var(--bg2) 100%);
            font-family: "Manrope", sans-serif;
            padding: 22px;
        }

        .wrap {
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            gap: 16px;
        }

        .top {
            border: 1px solid var(--line);
            background: var(--card);
            border-radius: 16px;
            padding: 14px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .logo {
            font-family: "Space Grotesk", sans-serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #8af3b9;
        }

        .top a {
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .hero {
            border: 1px solid var(--line);
            background: var(--card);
            border-radius: 18px;
            padding: clamp(24px, 5vw, 56px);
            display: grid;
            gap: 20px;
        }

        .badge {
            width: fit-content;
            padding: 6px 11px;
            border-radius: 999px;
            border: 1px solid rgba(117, 218, 159, 0.45);
            background: rgba(53, 217, 140, 0.2);
            color: #b7f7d1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        h1 {
            font-family: "Space Grotesk", sans-serif;
            font-size: clamp(36px, 7vw, 68px);
            line-height: 0.95;
            max-width: 840px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 16px;
            max-width: 720px;
            line-height: 1.6;
        }

        .cta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
        }

        .btn {
            text-decoration: none;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 800;
        }

        .btn.primary {
            color: #052114;
            background: linear-gradient(90deg, var(--accent) 0%, var(--accent2) 100%);
        }

        .btn.ghost {
            color: var(--text);
            border: 1px solid var(--line);
            background: rgba(12, 28, 22, 0.42);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .tile {
            border: 1px solid var(--line);
            background: var(--card);
            border-radius: 14px;
            padding: 14px;
            display: grid;
            gap: 8px;
        }

        .tile h2 {
            font-size: 16px;
        }

        .tile p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <header class="top">
            <div class="logo">KITA Employer</div>
            <a href="index.php">Back to Home</a>
        </header>

        <section class="hero">
            <span class="badge">FOR HIRING TEAMS</span>
            <h1>Employer Homepage</h1>
            <p class="subtitle">
                Create job posts, review applicants, and connect with student talent from one employer-focused workspace.
            </p>
            <div class="cta">
                <a class="btn primary" href="employer_register.php">Create Employer Account</a>
                <a class="btn ghost" href="employer_login.php">Login as Employer</a>
                <a class="btn ghost" href="employer.php">Open Employer Dashboard</a>
            </div>
        </section>

        <section class="grid">
            <article class="tile">
                <h2>Post Openings</h2>
                <p>Publish internships, part-time, and full-time roles with clear strand and location filters.</p>
            </article>
            <article class="tile">
                <h2>Track Applicants</h2>
                <p>View applications in one place and quickly monitor hiring status per job listing.</p>
            </article>
            <article class="tile">
                <h2>Reach Students</h2>
                <p>Connect to qualified students based on strand fit and practical skills.</p>
            </article>
        </section>
    </main>
</body>
</html>
