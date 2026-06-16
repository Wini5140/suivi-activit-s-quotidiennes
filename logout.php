<?php

declare(strict_types=1);

require __DIR__ . '/app.php';

bootstrap_app();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'), true, 302);
    exit;
}

try {
    verify_csrf();
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    header('Location: ' . url('index.php'), true, 302);
    exit;
}

logout_user();
header('Location: ' . url('index.php'), true, 302);
exit;
