<?php

declare(strict_types=1);

require __DIR__ . '/app.php';

bootstrap_app();
require_authentication();

try {
    $from = null;
    $to = null;

    if (($_GET['all'] ?? '') !== '1') {
        $from = parse_filter_date($_GET['from'] ?? null);
        $to = parse_filter_date($_GET['to'] ?? null);

        if ($from !== null && $to !== null && $from > $to) {
            throw new RuntimeException('La période demandée est invalide.');
        }
    }

    $activities = fetch_activities($from, $to);
} catch (Throwable $exception) {
    flash('error', $exception->getMessage());
    header('Location: ' . url('activities.php'), true, 302);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="activites.csv"');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Date', 'Début', 'Fin', 'Durée', 'Descriptif'], ';');

foreach ($activities as $activity) {
    fputcsv($output, [
        $activity['activity_date'],
        $activity['start_time'],
        $activity['end_time'],
        format_minutes(activity_duration_minutes($activity)),
        $activity['description'],
    ], ';');
}

fclose($output);
