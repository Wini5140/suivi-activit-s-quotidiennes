<?php

declare(strict_types=1);

require __DIR__ . '/app.php';

bootstrap_app();

$flash = consume_flash();
$errors = [];
$form = default_activity_values();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        if (($_POST['action'] ?? '') === 'login') {
            $password = (string) ($_POST['password'] ?? '');
            if (!authenticate($password)) {
                $errors[] = 'Mot de passe invalide.';
            } else {
                flash('success', 'Connexion réussie.');
                header('Location: ' . url('index.php'), true, 302);
                exit;
            }
        }

        if (($_POST['action'] ?? '') === 'save' && is_authenticated()) {
            [$form, $errors] = validate_activity($_POST);
            if (!$errors) {
                save_activity($form);
                flash('success', 'Activité enregistrée.');
                header('Location: ' . url('index.php'), true, 302);
                exit;
            }
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
        if (($_POST['action'] ?? '') === 'save') {
            $form = array_merge($form, [
                'activity_date' => (string) ($_POST['activity_date'] ?? $form['activity_date']),
                'start_time' => (string) ($_POST['start_time'] ?? $form['start_time']),
                'end_time' => (string) ($_POST['end_time'] ?? ''),
                'description' => (string) ($_POST['description'] ?? ''),
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Suivi des activités</title>
    <link rel="stylesheet" href="<?= h(url('style.css')) ?>">
</head>
<body>
<main class="layout">
    <section class="card">
        <div class="card-header">
            <div>
                <p class="eyebrow">Suivi des activités</p>
                <h1>Encoder une activité</h1>
            </div>
            <?php if (is_authenticated()): ?>
                <nav class="header-actions">
                    <a class="button secondary" href="<?= h(url('activities.php')) ?>">Voir la liste</a>
                    <form method="post" action="<?= h(url('logout.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <button class="button ghost" type="submit">Déconnexion</button>
                    </form>
                </nav>
            <?php endif; ?>
        </div>

        <?php if ($flash !== null): ?>
            <div class="notice notice-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice-error"><?= h($error) ?></div>
        <?php endforeach; ?>

        <?php if (!is_authenticated()): ?>
            <p class="lead">L’accès est protégé par mot de passe avant l’encodage ou la consultation des activités.</p>
            <form method="post" class="stack">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <label class="field">
                    <span>Mot de passe</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="button" type="submit">Se connecter</button>
            </form>
            <p class="hint">Configurez le mot de passe via <code>config.local.php</code> ou la variable <code>APP_PASSWORD_HASH</code>.</p>
        <?php else: ?>
            <p class="lead">La date est préremplie au jour courant et l’heure de début prend automatiquement l’heure d’accès au formulaire.</p>
            <form method="post" class="stack" novalidate>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

                <div class="grid two-columns">
                    <label class="field">
                        <span>Date</span>
                        <input id="activity_date" type="date" name="activity_date" value="<?= h($form['activity_date']) ?>" required>
                    </label>

                    <label class="field">
                        <span>Début</span>
                        <input id="start_time" type="time" name="start_time" value="<?= h($form['start_time']) ?>" required>
                    </label>
                </div>

                <label class="field">
                    <span>Fin</span>
                    <input type="time" name="end_time" value="<?= h($form['end_time']) ?>" required>
                </label>

                <label class="field">
                    <span>Descriptif</span>
                    <textarea name="description" rows="6" placeholder="Décrivez l’activité réalisée..." required><?= h($form['description']) ?></textarea>
                </label>

                <button class="button" type="submit">Enregistrer l’activité</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<script>
    (function () {
        var dateField = document.getElementById('activity_date');
        var startField = document.getElementById('start_time');
        var now = new Date();
        var localDate = new Date(now.getTime() - (now.getTimezoneOffset() * 60000));

        if (dateField && !dateField.value) {
            dateField.value = localDate.toISOString().slice(0, 10);
        }

        if (startField && !startField.value) {
            startField.value = localDate.toISOString().slice(11, 16);
        }
    }());
</script>
</body>
</html>
