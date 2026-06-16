<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $config = [
        'app_name' => getenv('APP_NAME') ?: 'Suivi des activités quotidiennes',
        'db_host' => getenv('DB_HOST') ?: '',
        'db_port' => getenv('DB_PORT') ?: '3306',
        'db_name' => getenv('DB_NAME') ?: '',
        'db_user' => getenv('DB_USER') ?: '',
        'db_pass' => getenv('DB_PASS') ?: '',
        'db_charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        'password_hash' => getenv('APP_PASSWORD_HASH') ?: '',
        'session_name' => getenv('APP_SESSION_NAME') ?: 'daily_activity_tracker',
        'force_https' => filter_var(getenv('APP_FORCE_HTTPS') ?: 'false', FILTER_VALIDATE_BOOL),
    ];

    $localConfigPath = __DIR__ . '/config.local.php';
    if (is_file($localConfigPath)) {
        $localConfig = require $localConfigPath;
        if (is_array($localConfig)) {
            $config = array_replace($config, $localConfig);
        }
    }

    return $config;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function bootstrap_app(): void
{
    $config = app_config();

    if ($config['force_https'] && !is_https()) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 302);
        exit;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($config['session_name']);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
            'secure' => is_https(),
        ]);
        session_start();
    }
}

function db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $requiredKeys = ['db_host', 'db_name', 'db_user'];
    foreach ($requiredKeys as $key) {
        if ($config[$key] === '') {
            throw new RuntimeException('Configuration manquante. Complétez config.local.php ou les variables d’environnement.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_port'],
        $config['db_name'],
        $config['db_charset']
    );

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    ensure_schema($pdo);

    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS activday_activities (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            activity_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_date (activity_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('La vérification de sécurité a échoué.');
    }
}

function url(string $path): string
{
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($base === '/' || $base === '.') {
        $base = '';
    }

    return $base . '/' . ltrim($path, '/');
}

function is_authenticated(): bool
{
    return !empty($_SESSION['authenticated']);
}

function require_authentication(): void
{
    if (!is_authenticated()) {
        header('Location: ' . url('index.php'), true, 302);
        exit;
    }
}

function login_error_state(): ?string
{
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);

    if ($lockedUntil > time()) {
        $remaining = max(1, (int) ceil(($lockedUntil - time()) / 60));
        return 'Accès temporairement bloqué. Réessayez dans ' . $remaining . ' minute(s).';
    }

    if ($lockedUntil !== 0) {
        unset($_SESSION['login_locked_until']);
    }

    return null;
}

function authenticate(string $password): bool
{
    $lockMessage = login_error_state();
    if ($lockMessage !== null) {
        throw new RuntimeException($lockMessage);
    }

    $hash = (string) app_config()['password_hash'];
    if ($hash === '') {
        throw new RuntimeException('Le mot de passe n’est pas configuré.');
    }

    if (!password_verify($password, $hash)) {
        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_locked_until'] = time() + 300;
        }
        return false;
    }

    $_SESSION['authenticated'] = true;
    $_SESSION['login_attempts'] = 0;
    unset($_SESSION['login_locked_until']);
    session_regenerate_id(true);

    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function default_activity_values(): array
{
    return [
        'activity_date' => date('Y-m-d'),
        'start_time' => date('H:i'),
        'end_time' => '',
        'description' => '',
    ];
}

function validate_activity(array $input): array
{
    $data = [
        'activity_date' => trim((string) ($input['activity_date'] ?? '')),
        'start_time' => trim((string) ($input['start_time'] ?? '')),
        'end_time' => trim((string) ($input['end_time'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
    ];

    $errors = [];

    if (!valid_date($data['activity_date'])) {
        $errors[] = 'La date est invalide.';
    }

    if (!valid_time($data['start_time'])) {
        $errors[] = 'L’heure de début est invalide.';
    }

    if (!valid_time($data['end_time'])) {
        $errors[] = 'L’heure de fin est invalide.';
    }

    if ($data['description'] === '') {
        $errors[] = 'Le descriptif est obligatoire.';
    }

    if (mb_strlen($data['description']) > 5000) {
        $errors[] = 'Le descriptif est trop long.';
    }

    if (!$errors && strcmp($data['end_time'], $data['start_time']) <= 0) {
        $errors[] = 'L’heure de fin doit être postérieure à l’heure de début.';
    }

    return [$data, $errors];
}

function valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    $parsed = DateTimeImmutable::createFromFormat('H:i', $time);

    return $parsed instanceof DateTimeImmutable && $parsed->format('H:i') === $time;
}

function save_activity(array $data): void
{
    $statement = db()->prepare(
        'INSERT INTO activday_activities (activity_date, start_time, end_time, description)
         VALUES (:activity_date, :start_time, :end_time, :description)'
    );

    $statement->execute([
        'activity_date' => $data['activity_date'],
        'start_time' => $data['start_time'] . ':00',
        'end_time' => $data['end_time'] . ':00',
        'description' => $data['description'],
    ]);
}

function parse_filter_date(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!valid_date($value)) {
        throw new RuntimeException('Le filtre de date est invalide.');
    }

    return $value;
}

function fetch_activities(?string $from, ?string $to): array
{
    $sql = 'SELECT id, activity_date, TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, description, created_at
            FROM activday_activities';
    $params = [];
    $clauses = [];

    if ($from !== null) {
        $clauses[] = 'activity_date >= :from_date';
        $params['from_date'] = $from;
    }

    if ($to !== null) {
        $clauses[] = 'activity_date <= :to_date';
        $params['to_date'] = $to;
    }

    if ($clauses) {
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    $sql .= ' ORDER BY activity_date DESC, start_time DESC, id DESC';

    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function activity_duration_minutes(array $activity): int
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i', $activity['activity_date'] . ' ' . $activity['start_time']);
    $end = DateTimeImmutable::createFromFormat('Y-m-d H:i', $activity['activity_date'] . ' ' . $activity['end_time']);

    if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
        return 0;
    }

    return (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
}

function format_minutes(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;

    return sprintf('%dh%02d', $hours, $remaining);
}

function total_duration_minutes(array $activities): int
{
    $total = 0;
    foreach ($activities as $activity) {
        $total += activity_duration_minutes($activity);
    }

    return $total;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
