# suivi-activit-s-quotidiennes
une webapp pour encoder mes activités quotidiennes et pouvoir sortir des rapports.

## Mise en place

1. Copiez `config.local.php.example` en `config.local.php`.
2. Générez un hash de mot de passe :

   ```bash
   php -r "echo password_hash('votre-mot-de-passe', PASSWORD_DEFAULT), PHP_EOL;"
   ```

3. Renseignez les accès MySQL OVH dans `config.local.php`.
4. Lancez l’application en local :

   ```bash
   php -S 127.0.0.1:8000
   ```

## Fonctionnalités

- accès protégé par mot de passe, session PHP durcie et protection CSRF ;
- formulaire responsive pour encoder la date, l’heure de début, l’heure de fin et le descriptif ;
- date du jour et heure d’accès préremplies automatiquement ;
- stockage MySQL avec création automatique de la table `activities` au premier accès ;
- page d’historique avec filtre par période, total des durées et export CSV de la période filtrée ou de toutes les activités.
