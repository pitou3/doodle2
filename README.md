# BC Swing – Sondages (doodle2)

Remplaçant de Framadate pour le BC Swing : sondages de **dates** et sondages **classiques**,
sans inscription, avec un espace de modération `/admin`.

PHP 8.1+ · MariaDB · aucune dépendance JavaScript · conçu pour l'hébergement web partagé Infomaniak.

📖 **Documentation complète : [docs/DOCUMENTATION.md](docs/DOCUMENTATION.md)** (guide d’utilisation, règles, architecture, configuration, déploiement, Graph, exploitation, dépannage).

## Fonctionnalités

- Accueil avec deux types de sondage : dates (avec horaires facultatifs) ou choix libres.
- Création ouverte à tous : **nom et titre obligatoires**, description et email facultatifs.
- Règle de modification des votes choisie par le créateur :
  - *Chacun son vote* : un participant ne modifie que son propre vote (jeton mémorisé dans le navigateur, plus un lien personnel pour changer d'appareil) ;
  - *Tout le monde* : n'importe qui peut modifier n'importe quel vote ;
  - *Personne* : un vote enregistré est figé.
  
  Dans tous les cas, le créateur et les administrateurs peuvent modifier ou supprimer les votes.
- Réponse « Peut-être » activable ou non.
- Date limite facultative : après cette date, plus aucun vote ni modification (sauf créateur et admins).
- **Lien d'administration** secret pour le créateur : il l'obtient à la création, et par email s'il en a indiqué un. Il permet de modifier les paramètres, les dates ou choix et les votes, de clôturer ou rouvrir le sondage, d'exporter en CSV et de supprimer le sondage.
- **Archivage** : un sondage clôturé passe en lecture seule, puis il est supprimé 6 mois plus tard (durée configurable).
- `/admin` : comptes administrateurs en base, liste et recherche de tous les sondages, modération, gestion des comptes.
- Emails via **SMTP** ou **Microsoft Graph** (Exchange Online).
- Interface **français / anglais** avec détection automatique de la langue du navigateur, et sélecteur FR/EN.
- Affichage responsive pour mobile et ordinateur.

### Cycle de vie d'un sondage

La « fin » d'un sondage est la plus proche des dates suivantes : sa clôture manuelle, sa date limite, ou, pour un sondage de dates sans date limite, la fin de la dernière date proposée. Après cette fin, le sondage est archivé en lecture seule et supprimé `retention_months` mois plus tard.

Un sondage classique sans date limite reste ouvert. Il est supprimé après `retention_months` mois sans activité.

Le nettoyage s'exécute automatiquement au plus une fois par jour, déclenché par le trafic du site. Il peut aussi être lancé par une tâche planifiée (voir plus bas) ou par le bouton dédié dans `/admin`.

## Développement et tests (Docker)

```bash
docker compose up -d --build                 # app : http://localhost:<APP_PORT>, emails : http://localhost:8025
docker compose exec app composer install
docker compose exec app php bin/migrate.php
docker compose exec app php bin/create-admin.php admin   # demande le mot de passe

# Tests unitaires et d'intégration (base doodle2_test)
docker compose exec app vendor/bin/phpunit --testsuite unit,integration
# Tests bout en bout via Apache (création, votes, droits, admin, emails Mailpit)
docker compose exec -e E2E_BASE_URL=http://localhost app vendor/bin/phpunit --testsuite e2e
```

Les ports locaux se règlent dans un fichier `.env` non versionné (`APP_PORT=8088`, `MAILPIT_PORT=8025`). Sans ce fichier, les ports par défaut sont 8080 et 8025.

En développement, tous les emails sont capturés par Mailpit, sur http://localhost:8025.

## Déploiement sur Infomaniak (hébergement web)

1. **Base de données** : dans le Manager Infomaniak, créez une base MariaDB et un utilisateur, puis notez l'hôte (`xxxx.myd.infomaniak.com`), le nom de la base, l'utilisateur et le mot de passe.
2. **Site** : créez le site `doodle2.bcswing.com`, activez le certificat **Let's Encrypt**, puis choisissez **PHP 8.1 ou plus récent**.
3. **Dossier racine** : dans les paramètres avancés du site, faites pointer le dossier racine vers le sous-dossier **`public/`** du projet.
   - Si ce n'est pas possible, le `.htaccess` à la racine redirige tout vers `public/` et bloque l'accès aux autres dossiers.
4. **Dépendances** : préparez-les localement avec `docker compose run --rm app composer install --no-dev --optimize-autoloader`.
   - Vous pouvez aussi lancer `composer install --no-dev` en SSH, Composer étant disponible sur Infomaniak.
5. **Envoi des fichiers** : transférez-les par SFTP, sans `tests/`, `docker/` ni `.git/`.
6. **Configuration** : copiez `config/config.example.php` en `config/config.php`, puis renseignez :
   - `base_url` ;
   - la base de données ;
   - l'envoi des emails ;
   - une `cron_key` ;
   - un `setup_token` temporaire.
7. **Installation** :
   - avec SSH : `php bin/migrate.php`, puis `php bin/create-admin.php <identifiant>` ;
   - sans SSH : ouvrez `https://doodle2.bcswing.com/admin/setup`, saisissez le `setup_token`, un identifiant et un mot de passe. Les tables et le premier compte sont créés. **Videz ensuite `setup_token`** dans la configuration.
8. **Dossier `var/`** : il doit être accessible en écriture, car il contient les journaux et le marqueur de nettoyage.
9. **Tâche planifiée (facultatif)** : dans le Manager Infomaniak, ajoutez une tâche quotidienne qui appelle `https://doodle2.bcswing.com/cron?key=<cron_key>`, ou bien la commande `php /chemin/vers/bin/cron.php`.

Les erreurs sont journalisées dans `var/log/app.log`.

## Emails

Un seul email est envoyé : les liens public et d'administration au créateur, s'il a indiqué son adresse. Si l'envoi échoue, le sondage est quand même créé et le lien d'administration reste affiché à l'écran.

### SMTP

- **Infomaniak** : `mail.infomaniak.com`, port 465 (`ssl`) ou 587 (`tls`), avec l'adresse et le mot de passe d'une boîte Infomaniak.
- **Exchange Online** : `smtp.office365.com`, port 587 (`tls`). SMTP AUTH doit être activé sur la boîte. Microsoft supprime progressivement l'authentification basique SMTP : **Graph est préférable**.

### Microsoft Graph (Exchange Online)

1. Dans Entra ID, ouvrez **Inscriptions d'applications** et créez une nouvelle inscription, par exemple « BC Swing Sondages », avec un seul locataire.
2. Dans **Autorisations d'API**, ajoutez Microsoft Graph → *Autorisations d'application* → `Mail.Send`, puis **accordez le consentement administrateur**.
3. Dans **Certificats et secrets**, créez un secret client et notez sa date d'expiration.
4. Dans `config.php`, renseignez :
   - `driver => 'graph'` ;
   - `tenant_id`, `client_id` et `client_secret` ;
   - `sender`, la boîte qui envoie les emails, par exemple une boîte partagée `sondages@bcswing.com`.
5. **Recommandé** : limitez l'application à cette seule boîte avec une *Application Access Policy* ou un rôle RBAC for Applications d'Exchange Online. Sans cette restriction, `Mail.Send` permet d'envoyer depuis n'importe quelle boîte du tenant.

## Sécurité

- Les jetons d'administration et de vote sont aléatoires et stockés uniquement sous forme de hash SHA-256. Le lien d'administration perdu ne peut donc pas être retrouvé : seul un admin du site peut alors gérer le sondage.
- Tous les formulaires sont protégés par CSRF. Les cookies sont `HttpOnly` et `SameSite=Lax`.
- Les en-têtes de sécurité sont stricts : CSP, `Referrer-Policy: no-referrer` pour ne pas divulguer les liens secrets, `X-Frame-Options`, HSTS en HTTPS.
- Les mots de passe administrateurs sont hachés avec bcrypt (`password_hash`). Après 10 échecs de connexion en 15 minutes, l'adresse IP est bloquée temporairement.
- Les pages sont marquées `noindex` : les sondages ne sont pas référencés par les moteurs de recherche.
- Un champ piège invisible (honeypot) filtre les robots sur le formulaire de création.

## Structure

```
public/        racine web (index.php, .htaccess, assets/)
src/           code PHP (App, contrôleurs, services, repositories, mail)
templates/     vues PHP
lang/          traductions fr / en
migrations/    schéma SQL
bin/           scripts CLI (migrate, create-admin, cron)
config/        config.example.php → config.php
tests/         PHPUnit : Unit, Integration (MariaDB), E2E (HTTP)
docker/        environnement de dev
```
