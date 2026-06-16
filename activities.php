<?php

declare(strict_types=1);

require __DIR__ . '/app.php';

bootstrap_app();
require_authentication();

$flash = consume_flash();
$errors = [];
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$activities = [];

try {
    $parsedFrom = parse_filter_date($from);
    $parsedTo = parse_filter_date($to);

    if ($parsedFrom !== null && $parsedTo !== null && $parsedFrom > $parsedTo) {
        throw new RuntimeException('La date de début doit être antérieure ou égale à la date de fin.');
    }

    $activities = fetch_activities($parsedFrom, $parsedTo);
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Liste des activités</title>
    <link rel="stylesheet" href="<?= h(url('style.css')) ?>">
</head>
<body>
<main class="layout">
    <section class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Suivi des activités</p>
                <h1>Historique des activités</h1>
            </div>
            <nav class="header-actions">
                <a class="button secondary" href="<?= h(url('index.php')) ?>">Nouvelle activité</a>
                <form method="post" action="<?= h(url('logout.php')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button class="button ghost" type="submit">Déconnexion</button>
                </form>
            </nav>
        </div>

        <?php if ($flash !== null): ?>
            <div class="notice notice-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endforeach; ?>

        <form method="get" class="stack">
            <div class="grid two-columns">
                <label class="field">
                    <span>Du</span>
                    <input type="date" name="from" value="<?= h($from) ?>">
                </label>
                <label class="field">
                    <span>Au</span>
                    <input type="date" name="to" value="<?= h($to) ?>">
                </label>
            </div>

            <div class="header-actions">
                <button class="button" type="submit">Filtrer</button>
                <a class="button secondary" href="<?= h(url('activities.php')) ?>">Réinitialiser</a>
            </div>
        </form>

        <div class="header-actions export-links">
            <a class="button secondary" href="<?= h(url('export.php')) ?>?from=<?= urlencode($from) ?>&amp;to=<?= urlencode($to) ?>">Exporter la période</a>
            <a class="button secondary" href="<?= h(url('export.php')) ?>?all=1">Exporter tout</a>
        </div>

        <p class="lead">
            <?= count($activities) ?> activité(s) · <?= h(format_minutes(total_duration_minutes($activities))) ?> au total
        </p>

        <?php if (!$activities): ?>
            <p class="empty-state">Aucune activité trouvée pour ce filtre.</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Durée</th>
                        <th>Descriptif</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($activities as $activity): ?>
                        <tr>
                            <td><?= h($activity['activity_date']) ?></td>
                            <td><?= h($activity['start_time']) ?></td>
                            <td><?= h($activity['end_time']) ?></td>
                            <td><?= h(format_minutes(activity_duration_minutes($activity))) ?></td>
                            <td><?= nl2br(h($activity['description'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
