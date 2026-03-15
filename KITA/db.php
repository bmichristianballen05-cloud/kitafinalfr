<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function users_columns(): array
{
    static $columns = null;
    if (is_array($columns)) {
        return $columns;
    }

    $columns = [];
    $conn = db();
    $res = $conn->query("SHOW COLUMNS FROM users");
    if (!$res) {
        return $columns;
    }

    while ($row = $res->fetch_assoc()) {
        $field = strtolower((string) ($row['Field'] ?? ''));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}

function users_has_column(string $column): bool
{
    $cols = users_columns();
    return isset($cols[strtolower($column)]);
}

function users_name_column(): string
{
    foreach (['username', 'full_name', 'name'] as $candidate) {
        if (users_has_column($candidate)) {
            return $candidate;
        }
    }

    return 'username';
}

function users_id_column(): string
{
    foreach (['user_id', 'id'] as $candidate) {
        if (users_has_column($candidate)) {
            return $candidate;
        }
    }

    return 'user_id';
}

function users_select_sql(): string
{
    $idColumn = users_id_column();
    $nameColumn = users_name_column();

    $parts = [];
    $parts[] = users_has_column($idColumn) ? "`{$idColumn}` AS user_id" : "NULL AS user_id";
    $parts[] = users_has_column($nameColumn) ? "`{$nameColumn}` AS username" : "NULL AS username";

    foreach (['email', 'password', 'role', 'strand', 'location', 'bio', 'profile_picture', 'email_verified', 'email_verified_at', 'created_at'] as $column) {
        $parts[] = users_has_column($column) ? "`{$column}`" : "NULL AS `{$column}`";
    }

    return implode(', ', $parts);
}

function find_user_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    if (!users_has_column('email')) {
        return null;
    }

    $conn = db();
    $sql = "SELECT " . users_select_sql() . "
            FROM users
            WHERE email = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function find_user_by_username(string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }
    $nameColumn = users_name_column();
    if (!users_has_column($nameColumn)) {
        return null;
    }

    $conn = db();
    $sql = "SELECT " . users_select_sql() . "
            FROM users
            WHERE `{$nameColumn}` = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function find_user_by_id(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    $idColumn = users_id_column();
    if (!users_has_column($idColumn)) {
        return null;
    }

    $conn = db();
    $sql = "SELECT " . users_select_sql() . "
            FROM users
            WHERE `{$idColumn}` = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function create_user(array $payload): array
{
    $conn = db();

    $username = trim((string) ($payload['username'] ?? ($payload['full_name'] ?? '')));
    $email = trim((string) ($payload['email'] ?? ''));
    $passwordHash = (string) ($payload['password_hash'] ?? '');
    $location = trim((string) ($payload['location'] ?? ''));
    $strand = trim((string) ($payload['strand'] ?? ''));
    $role = trim((string) ($payload['role'] ?? 'student'));

    $columns = [];
    $values = [];
    $types = '';

    $nameColumn = users_name_column();
    if (users_has_column($nameColumn)) {
        $columns[] = "`{$nameColumn}`";
        $values[] = $username;
        $types .= 's';
    }

    if (!users_has_column('email') || !users_has_column('password')) {
        return ['ok' => false, 'error' => 'users table is missing required email/password columns'];
    }

    $columns[] = "`email`";
    $values[] = $email;
    $types .= 's';

    $columns[] = "`password`";
    $values[] = $passwordHash;
    $types .= 's';

    if (users_has_column('role')) {
        $columns[] = "`role`";
        $values[] = $role;
        $types .= 's';
    }
    if (users_has_column('strand')) {
        $columns[] = "`strand`";
        $values[] = $strand;
        $types .= 's';
    }
    if (users_has_column('location')) {
        $columns[] = "`location`";
        $values[] = $location;
        $types .= 's';
    }
    if (users_has_column('created_at')) {
        $columns[] = "`created_at`";
    }

    $placeholders = [];
    $bindValues = [];
    foreach ($columns as $column) {
        if ($column === "`created_at`") {
            $placeholders[] = "NOW()";
            continue;
        }
        $placeholders[] = '?';
        $bindValues[] = array_shift($values);
    }

    $sql = "INSERT INTO users (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => $conn->error];
    }

    if ($types !== '') {
        $bind = [$types];
        foreach ($bindValues as $index => $value) {
            $bind[] = &$bindValues[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }
    $ok = $stmt->execute();
    $error = $stmt->error;
    $newId = (int) $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        return ['ok' => false, 'error' => $error];
    }

    return ['ok' => true, 'user_id' => $newId];
}

function save_user_skills(int $userId, array $skills): void
{
    if ($userId <= 0 || empty($skills)) {
        return;
    }

    $conn = db();

    // If optional tables do not exist in this DB, just skip gracefully.
    $hasSkills = $conn->query("SHOW TABLES LIKE 'skills'");
    $hasUserSkills = $conn->query("SHOW TABLES LIKE 'user_skills'");
    if (!$hasSkills || !$hasUserSkills || $hasSkills->num_rows === 0 || $hasUserSkills->num_rows === 0) {
        return;
    }

    $findSkill = $conn->prepare("SELECT skill_id FROM skills WHERE skill_name = ? LIMIT 1");
    $insertSkill = $conn->prepare("INSERT INTO skills (skill_name) VALUES (?)");
    $insertUserSkill = $conn->prepare("INSERT IGNORE INTO user_skills (user_id, skill_id) VALUES (?, ?)");

    if (!$findSkill || !$insertSkill || !$insertUserSkill) {
        return;
    }

    foreach ($skills as $raw) {
        $skill = trim((string) $raw);
        if ($skill === '') {
            continue;
        }

        $findSkill->bind_param('s', $skill);
        $findSkill->execute();
        $res = $findSkill->get_result();
        $row = $res ? $res->fetch_assoc() : null;

        if ($row && isset($row['skill_id'])) {
            $skillId = (int) $row['skill_id'];
        } else {
            $insertSkill->bind_param('s', $skill);
            $insertSkill->execute();
            $skillId = (int) $insertSkill->insert_id;
        }

        if ($skillId > 0) {
            $insertUserSkill->bind_param('ii', $userId, $skillId);
            $insertUserSkill->execute();
        }
    }

    $findSkill->close();
    $insertSkill->close();
    $insertUserSkill->close();
}

function update_user_password_by_email(string $email, string $passwordHash): bool
{
    $email = trim($email);
    if ($email === '' || $passwordHash === '') {
        return false;
    }
    if (!users_has_column('email') || !users_has_column('password')) {
        return false;
    }

    $conn = db();
    $sql = "UPDATE users SET `password` = ? WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $passwordHash, $email);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $ok && $affected >= 0;
}

function db_table_exists(string $table): bool
{
    $conn = db();
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function ensure_social_tables(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $conn = db();

    $queries = [
        "CREATE TABLE IF NOT EXISTS friend_requests (
            request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            from_user_id INT UNSIGNED NOT NULL,
            to_user_id INT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (request_id),
            UNIQUE KEY uq_friend_req_pair (from_user_id, to_user_id),
            KEY idx_friend_req_to_status (to_user_id, status),
            KEY idx_friend_req_from_status (from_user_id, status),
            CONSTRAINT fk_friend_req_from FOREIGN KEY (from_user_id) REFERENCES users (user_id) ON DELETE CASCADE,
            CONSTRAINT fk_friend_req_to FOREIGN KEY (to_user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS friendships (
            friendship_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_one_id INT UNSIGNED NOT NULL,
            user_two_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (friendship_id),
            UNIQUE KEY uq_friendship_pair (user_one_id, user_two_id),
            KEY idx_friendship_user_two (user_two_id),
            CONSTRAINT fk_friendships_one FOREIGN KEY (user_one_id) REFERENCES users (user_id) ON DELETE CASCADE,
            CONSTRAINT fk_friendships_two FOREIGN KEY (user_two_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS chat_threads (
            thread_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (thread_id),
            KEY idx_chat_threads_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS chat_thread_members (
            thread_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            last_read_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (thread_id, user_id),
            KEY idx_ctm_user (user_id),
            CONSTRAINT fk_ctm_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (thread_id) ON DELETE CASCADE,
            CONSTRAINT fk_ctm_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS chat_messages (
            message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id),
            KEY idx_chat_messages_thread (thread_id, created_at),
            KEY idx_chat_messages_sender (sender_id),
            CONSTRAINT fk_chat_msg_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (thread_id) ON DELETE CASCADE,
            CONSTRAINT fk_chat_msg_sender FOREIGN KEY (sender_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS notifications (
            notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(40) NOT NULL,
            title VARCHAR(160) NOT NULL,
            body VARCHAR(255) NOT NULL,
            data_json TEXT NULL,
            external_key VARCHAR(120) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (notification_id),
            UNIQUE KEY uq_notifications_external (user_id, external_key),
            KEY idx_notifications_user_created (user_id, created_at),
            KEY idx_notifications_user_read (user_id, is_read),
            CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    foreach ($queries as $sql) {
        $conn->query($sql);
    }

    // Allow employer/user combined IDs (negative for employers) in chat tables.
    // Drop strict user foreign keys and use signed ints for member/sender IDs.
    $conn->query("ALTER TABLE chat_thread_members DROP FOREIGN KEY fk_ctm_user");
    $conn->query("ALTER TABLE chat_messages DROP FOREIGN KEY fk_chat_msg_sender");
    $conn->query("ALTER TABLE chat_thread_members MODIFY user_id INT NOT NULL");
    $conn->query("ALTER TABLE chat_messages MODIFY sender_id INT NOT NULL");

    $ready = true;
}

function ensure_email_verification_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $conn = db();

    if (users_has_column('email_verified') === false) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
    }
    if (users_has_column('email_verified_at') === false) {
        $conn->query("ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS email_verification_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email_verify_user (user_id),
            KEY idx_email_verify_email (email),
            KEY idx_email_verify_expires (expires_at),
            CONSTRAINT fk_email_verify_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ready = true;
}

function ensure_employer_profile_schema(): void
{
    static $ready = false;
    if ($ready) return;
    $conn = db();
    // Add profile_picture and bio columns to employers if missing
    foreach (['profile_picture VARCHAR(255) DEFAULT NULL', 'bio TEXT DEFAULT NULL'] as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $res = $conn->query("SHOW COLUMNS FROM employers LIKE '{$colName}'");
        if ($res instanceof mysqli_result && $res->num_rows === 0) {
            $conn->query("ALTER TABLE employers ADD COLUMN {$colDef}");
        }
    }
    $ready = true;
}

function ensure_career_plan_schema(): void
{
    static $ready = false;
    if ($ready) return;
    $conn = db();
    $res = $conn->query("SHOW COLUMNS FROM users LIKE 'career_plan'");
    if ($res instanceof mysqli_result && $res->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN career_plan TEXT DEFAULT NULL");
    }
    $ready = true;
}

function ensure_post_meta_schema(): void
{
    static $ready = false;
    if ($ready) return;
    $conn = db();
    
    // Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS posts (
        post_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'image VARCHAR(255) DEFAULT NULL', 
        'strand VARCHAR(100) DEFAULT NULL', 
        'location VARCHAR(120) DEFAULT NULL'
    ];
    foreach ($columns as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $res = $conn->query("SHOW COLUMNS FROM posts LIKE '{$colName}'");
        if ($res instanceof mysqli_result && $res->num_rows === 0) {
            $conn->query("ALTER TABLE posts ADD COLUMN {$colDef}");
        }
    }
    $ready = true;
}

function ensure_application_scheduling_schema(): void
{
    static $ready = false;
    if ($ready) return;
    $conn = db();
    $columns = ['interview_type VARCHAR(20) DEFAULT NULL', 'interview_datetime DATETIME DEFAULT NULL', 'interview_notes TEXT DEFAULT NULL'];
    foreach ($columns as $colDef) {
        $colName = explode(' ', $colDef)[0];
        $res = $conn->query("SHOW COLUMNS FROM applications LIKE '{$colName}'");
        if ($res instanceof mysqli_result && $res->num_rows === 0) {
            $conn->query("ALTER TABLE applications ADD COLUMN {$colDef}");
        }
    }
    $ready = true;
}

function user_email_is_verified(array $user): bool
{
    // Email verification is disabled — all accounts are treated as verified.
    return true;
}

function set_user_email_verified(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    ensure_email_verification_schema();
    $conn = db();
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1, email_verified_at = NOW() WHERE user_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function create_email_verification_code(int $userId, string $email): ?string
{
    if ($userId <= 0 || trim($email) === '') {
        return null;
    }
    ensure_email_verification_schema();
    $conn = db();
    $email = trim($email);
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 15 * 60);

    $expireOld = $conn->prepare("UPDATE email_verification_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
    if ($expireOld) {
        $expireOld->bind_param('i', $userId);
        $expireOld->execute();
        $expireOld->close();
    }

    $stmt = $conn->prepare("INSERT INTO email_verification_codes (user_id, email, code_hash, expires_at) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('isss', $userId, $email, $hash, $expiresAt);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok ? $code : null;
}

function verify_email_code(int $userId, string $code): bool
{
    $code = trim($code);
    if ($userId <= 0 || $code === '') {
        return false;
    }
    ensure_email_verification_schema();
    $conn = db();
    $stmt = $conn->prepare(
        "SELECT id, code_hash, expires_at, attempts
         FROM email_verification_codes
         WHERE user_id = ? AND used_at IS NULL
         ORDER BY id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!is_array($row)) {
        return false;
    }
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return false;
    }
    $expiresAt = strtotime((string) ($row['expires_at'] ?? ''));
    if ($expiresAt !== false && $expiresAt < time()) {
        $mark = $conn->prepare("UPDATE email_verification_codes SET used_at = NOW() WHERE id = ? LIMIT 1");
        if ($mark) {
            $mark->bind_param('i', $id);
            $mark->execute();
            $mark->close();
        }
        return false;
    }

    $isMatch = password_verify($code, (string) ($row['code_hash'] ?? ''));
    if (!$isMatch) {
        $inc = $conn->prepare("UPDATE email_verification_codes SET attempts = attempts + 1 WHERE id = ? LIMIT 1");
        if ($inc) {
            $inc->bind_param('i', $id);
            $inc->execute();
            $inc->close();
        }
        return false;
    }

    $use = $conn->prepare("UPDATE email_verification_codes SET used_at = NOW() WHERE id = ? LIMIT 1");
    if ($use) {
        $use->bind_param('i', $id);
        $use->execute();
        $use->close();
    }
    set_user_email_verified($userId);
    return true;
}

function send_verification_email(string $toEmail, string $username, string $code): bool
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || $code === '') {
        return false;
    }

    $safeName = trim($username) !== '' ? trim($username) : 'there';
    $subject  = 'KITA Email Verification Code';
    $body     = "Hi {$safeName},\n\nYour KITA verification code is: {$code}\n\nThis code expires in 15 minutes.\nIf you did not create this account, you can ignore this email.\n\n- KITA";

    // Try PHPMailer (SMTP) first
    $phpmailerPath = __DIR__ . '/phpmailer/PHPMailer.php';
    $smtpPath      = __DIR__ . '/phpmailer/SMTP.php';
    $exceptionPath = __DIR__ . '/phpmailer/Exception.php';

    if (
        defined('SMTP_USER') && SMTP_USER !== 'your_gmail@gmail.com' &&
        file_exists($phpmailerPath) && file_exists($smtpPath) && file_exists($exceptionPath)
    ) {
        require_once $exceptionPath;
        require_once $phpmailerPath;
        require_once $smtpPath;

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $safeName);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (\Exception $e) {
            // Fall through to php mail()
        }
    }

    // Fallback: built-in mail()
    $headers  = 'From: KITA <no-reply@kita.local>' . "\r\n";
    $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    return @mail($toEmail, $subject, $body, $headers);
}
