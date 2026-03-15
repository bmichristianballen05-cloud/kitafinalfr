<?php
require_once __DIR__ . '/config.php';

$userSession = $_SESSION['user'] ?? null;
$employerSession = $_SESSION['employer'] ?? null;
if (!is_array($userSession) && !is_array($employerSession)) {
    header('Location: login.php');
    exit;
}

$viewerName = is_array($userSession)
    ? trim((string) (($userSession['username'] ?? '') ?: ($userSession['full_name'] ?? 'KITA User')))
    : trim((string) (($employerSession['company_name'] ?? '') ?: ($employerSession['contact_name'] ?? 'Employer')));
if ($viewerName === '') $viewerName = 'KITA User';

$room = trim((string) ($_GET['room'] ?? ''));
$mode = strtolower(trim((string) ($_GET['mode'] ?? 'video')));

if ($room === '') {
    http_response_code(400);
    echo 'Missing room.';
    exit;
}

if (!preg_match('/^[a-z0-9-]{3,120}$/', $room)) {
    http_response_code(400);
    echo 'Invalid room.';
    exit;
}

if ($mode !== 'audio' && $mode !== 'video') {
    $mode = 'video';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>KITA Call</title>
    <style>
        :root {
            --bg: #08110d;
            --surface: #0f1b15;
            --line: #1f3d2f;
            --accent: #00c96b;
            --text: #d9fbe9;
            --muted: #9fb8ac;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at 50% 8%, rgba(0, 201, 107, 0.18), transparent 45%), var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr;
        }
        .call-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
            background: rgba(15, 27, 21, 0.92);
        }
        .brand {
            font-weight: 800;
            color: var(--accent);
            letter-spacing: 0.04em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .brand-logo {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(0, 201, 107, 0.45);
        }
        .meta { font-size: 12px; color: var(--muted); }
        .leave {
            border: 1px solid var(--line);
            background: #13241c;
            color: var(--text);
            border-radius: 8px;
            padding: 7px 10px;
            cursor: pointer;
        }
        .leave:hover { border-color: var(--accent); color: var(--accent); }
        #jitsiContainer {
            width: 100%;
            min-height: 0;
        }
    </style>
</head>
<body>
    <header class="call-head">
        <div>
            <div class="brand">
                <img class="brand-logo" src="uploads/kita_logo.png" alt="KITA" />
                <span>KITA</span>
            </div>
            <div class="meta"><?php echo htmlspecialchars(strtoupper($mode), ENT_QUOTES, 'UTF-8'); ?> room: <?php echo htmlspecialchars($room, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <button class="leave" type="button" onclick="window.close()">Leave Call</button>
    </header>
    <main id="jitsiContainer"></main>

    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        (function () {
            const roomName = <?php echo json_encode($room, JSON_UNESCAPED_SLASHES); ?>;
            const mode = <?php echo json_encode($mode, JSON_UNESCAPED_SLASHES); ?>;
            const displayName = <?php echo json_encode($viewerName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const isAudio = mode === "audio";

            const api = new JitsiMeetExternalAPI("meet.jit.si", {
                roomName,
                parentNode: document.getElementById("jitsiContainer"),
                userInfo: { displayName },
                interfaceConfigOverwrite: {
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_BRAND_WATERMARK: false,
                    BRAND_WATERMARK_LINK: ""
                },
                configOverwrite: {
                    startAudioOnly: isAudio,
                    startWithVideoMuted: isAudio,
                    prejoinConfig: { enabled: false }
                }
            });

            window.addEventListener("beforeunload", () => {
                try { api.dispose(); } catch {}
            });
        })();
    </script>
</body>
</html>
