# Documentation – BC Swing Sondages (doodle2)

Application de sondages « à la Doodle » qui remplace Framadate pour le club de danse **BC Swing**.
Elle est prévue pour fonctionner sur l'hébergement web partagé **Infomaniak** (PHP + MariaDB + Let's Encrypt).

---

## Sommaire

1. [Présentation](#1-présentation)
2. [Guide d'utilisation](#2-guide-dutilisation)
3. [Règles de fonctionnement](#3-règles-de-fonctionnement)
4. [Architecture technique](#4-architecture-technique)
5. [Configuration](#5-configuration)
6. [Développement local avec Docker](#6-développement-local-avec-docker)
7. [Tests](#7-tests)
8. [Déploiement sur Infomaniak](#8-déploiement-sur-infomaniak)
9. [Envoi des emails](#9-envoi-des-emails)
10. [Exploitation et maintenance](#10-exploitation-et-maintenance)
11. [Sécurité](#11-sécurité)
12. [Dépannage](#12-dépannage)
13. [Évolutions possibles](#13-évolutions-possibles)

---

## 1. Présentation

| | |
|---|---|
| **Objectif** | Permettre à n'importe qui de créer un sondage de dates ou un sondage classique, sans inscription |
| **Public** | Membres et organisateurs du BC Swing |
| **Modération** | Espace `/admin` protégé par identifiant et mot de passe |
| **Langues** | Français et anglais, avec détection automatique selon le navigateur |
| **Appareils** | Ordinateurs, tablettes et téléphones (affichage responsive) |
| **Stack** | PHP ≥ 8.1, MariaDB ≥ 10.3, Apache avec `.htaccess`, sans framework, sans build JavaScript |
| **Dépendance PHP** | `phpmailer/phpmailer` (envoi SMTP), installée via Composer |

### Fonctionnalités

- Deux types de sondage, proposés sur la page d'accueil :
  - **Sondage de dates** : une liste de dates, chacune avec des horaires facultatifs ;
  - **Sondage classique** : une liste de choix libres (lieu, thème, menu…).
- **Création ouverte à tous** :
  - nom du créateur et titre **obligatoires** ;
  - description et email **facultatifs**.
- **Droits de modification des votes**, choisis par le créateur : *chacun son vote*, *tout le monde* ou *personne*.
- Réponse **« Peut-être »** activable ou non.
- **Date limite** de participation, facultative.
- **Lien d'administration** secret pour le créateur, affiché à l'écran et envoyé par email si une adresse a été donnée.
- **Clôture manuelle**, réouverture, modification des dates ou choix, **export CSV**, suppression.
- **Archivage** : après sa clôture, un sondage reste consultable en lecture seule pendant 6 mois, puis il est supprimé automatiquement.
- **Administration** : liste et recherche de tous les sondages, modération, gestion des comptes administrateurs.
- **Emails** via SMTP ou **Microsoft Graph** (Exchange Online).

---

## 2. Guide d'utilisation

### 2.1 Créer un sondage

1. Sur la page d'accueil, choisir **Sondage de dates** ou **Sondage classique**.
2. Remplir les **informations** :
   - *Votre nom* (obligatoire) ;
   - *Votre email* (facultatif) : il sert à recevoir le lien d'administration ;
   - *Titre* (obligatoire) ;
   - *Description* (facultative).
3. Régler les **paramètres** :
   - *Qui peut modifier les votes ?* : voir le [§ 3.1](#31-droits-de-modification-des-votes) ;
   - *Autoriser « Peut-être »* : coché par défaut ;
   - *Date limite de participation* : facultative, elle doit être dans le futur.
4. Saisir les **choix** :
   - **Sondage de dates** : une ligne par date. Le champ *Horaires* accepte plusieurs valeurs séparées par des virgules (ex. `14h, 17h`), et chaque horaire devient une colonne distincte. Laissé vide, la date forme une seule colonne. Le bouton *+ Ajouter une date* propose le jour suivant avec les mêmes horaires.
   - **Sondage classique** : un choix par ligne, avec *+ Ajouter un choix* pour en ajouter.
5. Cliquer sur **Créer le sondage**. On arrive alors sur la **page d'administration** du sondage, qui affiche :
   - le **lien public**, à partager avec les participants ;
   - le **lien d'administration**, à conserver et à ne pas partager.

> ⚠️ Sans email, le lien d'administration **ne peut pas être retrouvé** s'il est perdu.
> Seul un administrateur du site peut alors gérer le sondage.

### 2.2 Participer

1. Ouvrir le lien public (`https://…/p/<identifiant>`).
2. Saisir son **nom**, puis répondre pour chaque choix : **Oui**, **Peut-être** (si autorisé) ou **Non**.
3. Cliquer sur **Enregistrer mon vote**.

Chaque nom ne peut être utilisé qu'une fois par sondage. La comparaison ignore les majuscules et les accents : `Chloé` et `chloe` sont considérés comme le même nom.

Si la règle est « chacun son vote », le site affiche un **lien personnel** après le vote. Il permet de modifier ce vote depuis un autre appareil. Sur l'appareil qui a servi à voter, le vote est mémorisé automatiquement.

### 2.3 Lire les résultats

- Le **tableau des participants** présente une ligne par personne et une colonne par choix :
  - ✓ (vert) : oui ;
  - (✓) (orange) : peut-être ;
  - ✗ (rouge pâle) : non.
- La ligne **Total** donne le nombre de « oui », avec les « peut-être » entre parenthèses.
- Le **meilleur choix** est souligné en orange : c'est celui qui a le plus de « oui », puis le plus de « peut-être » en cas d'égalité.
- Le **Résumé par choix**, sous le formulaire, donne la liste des noms pour chaque choix. Il est pratique sur mobile.

### 2.4 Gérer son sondage (créateur)

Depuis le lien d'administration (`https://…/a/<identifiant>/<jeton>`) :

| Action | Où |
|---|---|
| Copier le lien public ou le lien d'administration | Haut du panneau « Gestion de votre sondage » |
| Modifier le titre, la description, le nom, l'email, les droits, « Peut-être », la date limite | *Modifier les paramètres* |
| Clôturer ou rouvrir le sondage | *Modifier les paramètres* → *Clôturer le sondage maintenant* |
| Modifier, retirer ou ajouter des dates ou des choix | *Modifier les dates* / *Modifier les choix* |
| Ajouter, modifier ou supprimer **n'importe quel** vote | Icônes ✎ / 🗑 dans le tableau, même après la clôture |
| Exporter les résultats | *Exporter (CSV)* : fichier séparé par `;`, compatible Excel |
| Supprimer le sondage | *Supprimer le sondage* (action définitive) |

> Retirer un choix supprime aussi toutes les réponses données pour ce choix.

Pour **rouvrir** un sondage dont la date limite est passée, il faut décocher la clôture **et** repousser ou vider la date limite.

### 2.5 Administration du site (`/admin`)

1. Aller sur `https://…/admin` et se connecter.
2. **Tous les sondages** :
   - recherche par titre, auteur ou email, ou par identifiant exact ;
   - filtre *Tous / Ouverts / Clôturés* ;
   - pour chaque sondage : nombre de votes, statut et date de suppression prévue ;
   - actions *Voir* (page publique), *Gérer* (mêmes possibilités que le créateur) et *Supprimer*.
3. **Archivage** : le bouton *Lancer le nettoyage maintenant* supprime immédiatement les sondages arrivés en fin de conservation.
4. **Administrateurs** : ajouter un compte, supprimer un autre compte, changer son propre mot de passe (10 caractères minimum).

La session d'administration expire après **4 heures d'inactivité**.

### 2.6 Changer de langue

La langue est choisie automatiquement d'après le navigateur : anglais si l'anglais passe avant le français, français sinon.

Les liens **FR / EN** en haut à droite forcent une langue. Ce choix est mémorisé un an dans un cookie.

Les emails sont rédigés dans la langue utilisée au moment de la création du sondage.

---

## 3. Règles de fonctionnement

### 3.1 Droits de modification des votes

| Règle choisie | Le participant sur son vote | Un autre participant | Créateur / administrateur |
|---|---|---|---|
| **Chacun son vote** (`own`, par défaut) | ✅ modifier / supprimer | ❌ | ✅ toujours |
| **Tout le monde** (`all`) | ✅ | ✅ modifier / supprimer n'importe quel vote | ✅ toujours |
| **Personne** (`none`) | ❌ (vote figé) | ❌ | ✅ toujours |

- Une fois le sondage **clôturé**, plus aucun participant ne peut voter ni modifier, quelle que soit la règle. Le créateur et les administrateurs le peuvent encore.
- Avec « chacun son vote », le vote est reconnu grâce à un **jeton secret** créé lors du vote et conservé de deux façons :
  - dans un cookie du navigateur (`d2v_<identifiant>`, valable environ 13 mois, jusqu'à 20 votes mémorisés par sondage) ;
  - dans le **lien personnel** affiché après le vote (`/p/<id>/v/<n° vote>/<jeton>`).

### 3.2 Cycle de vie et archivage

La **fin** d'un sondage (colonne `ends_at`) est la plus proche des dates suivantes :

1. la **clôture manuelle** ;
2. la **date limite** ;
3. pour un **sondage de dates sans date limite** : la fin de la **dernière date proposée** (23 h 59).

```
 création ──► OUVERT ──(fin)──► CLÔTURÉ / ARCHIVÉ (lecture seule) ──(+ 6 mois)──► SUPPRIMÉ
```

| Cas | Supprimé le |
|---|---|
| Sondage avec une fin (date limite, dernière date, clôture) | fin + `retention_months` (6 par défaut) |
| Sondage **classique sans date limite** et non clôturé | dernière activité + `retention_months` |

Une « activité » est une création, un vote, une modification de vote, de paramètres ou de choix.

La date de suppression prévue est affichée sur la page d'un sondage clôturé, dans le panneau de gestion et dans `/admin`.

**Déclenchement du nettoyage** (n'importe laquelle de ces trois méthodes suffit) :

- **automatique** : au plus une fois par 24 h, lors d'une visite sur le site (fichier marqueur `var/last_purge`) ;
- **tâche planifiée** : URL `/cron?key=<cron_key>` ou commande `php bin/cron.php` ;
- **manuel** : bouton dans `/admin`.

### 3.3 Validation des saisies

| Champ | Règle |
|---|---|
| Nom du créateur | obligatoire, 100 caractères max. |
| Email du créateur | facultatif, adresse valide, 190 caractères max. |
| Titre | obligatoire, 200 caractères max. |
| Description | facultative, 5 000 caractères max. Les retours à la ligne sont conservés. |
| Date limite | facultative, dans le futur à la création |
| Choix | au moins 1, au plus 100. Classique : 255 caractères max., doublons ignorés. Horaire : 50 caractères max. |
| Nom du participant | obligatoire, 100 caractères max., unique dans le sondage |

---

## 4. Architecture technique

### 4.1 Arborescence

```
doodle2/
├── public/                 ← RACINE WEB (seul dossier exposé)
│   ├── index.php           point d'entrée unique (front controller)
│   ├── .htaccess           réécriture d'URL et passage forcé en HTTPS
│   └── assets/             style.css, app.js, favicon.svg
├── src/
│   ├── App.php             conteneur : config, services, routes, erreurs, en-têtes de sécurité
│   ├── Config.php          chargement de la configuration et valeurs par défaut
│   ├── Database.php        connexion PDO (utf8mb4, exceptions, requêtes préparées natives)
│   ├── Migrator.php        application des fichiers migrations/*.sql
│   ├── I18n.php            traductions, détection de la langue, formatage des dates
│   ├── Session.php         session PHP, messages flash, jeton CSRF
│   ├── Security.php        jetons aléatoires et hachage
│   ├── View.php            rendu des templates PHP
│   ├── helpers.php         e(), t(), url(), csrf_field(), field_error()
│   ├── Http/               Request, Response, Router, HttpException
│   ├── Controller/         Home, Poll (public), Manage (créateur/admin), Admin
│   ├── Service/            PollService (règles métier), AccessContext, ValidationException
│   ├── Repository/         PollRepository, AdminRepository (SQL)
│   └── Mail/               SmtpMailer, GraphMailer, LogMailer, NullMailer, MailerFactory, Notifier
├── templates/              vues (layout, accueil, création, sondage, partials/, admin/)
├── lang/                   fr.php, en.php
├── migrations/             001_init.sql
├── bin/                    migrate.php, create-admin.php, cron.php
├── config/                 config.example.php (→ config.php, non versionné)
├── var/                    journaux et marqueur de nettoyage (doit être accessible en écriture)
├── tests/                  Unit/, Integration/, E2E/
├── docker/                 Dockerfile, config.php de dev, script SQL de la base de test
├── docker-compose.yml
├── composer.json
└── phpunit.xml
```

Chaque dossier hors de `public/` contient un `.htaccess` `Require all denied`. Le `.htaccess` racine redirige vers `public/` si la racine du site a été mal configurée.

### 4.2 Traitement d'une requête

```
Apache ─► public/.htaccess ─► public/index.php
   └─► App::handle()
        1. démarrage de la session (cookie d2sess, HttpOnly, SameSite=Lax)
        2. détection de la langue (cookie « lang », puis Accept-Language, puis langue par défaut)
        3. vérification CSRF pour tout POST
        4. nettoyage automatique éventuel (une fois par 24 h)
        5. Router ─► Controller ─► PollService ─► PollRepository ─► MariaDB
        6. rendu du template + en-têtes de sécurité
   Les erreurs HttpException deviennent une page d'erreur traduite (404, 403…) ; les autres sont journalisées (500).
```

Après chaque formulaire, le site redirige vers une page de résultat (modèle **Post/Redirect/Get**). Les erreurs de saisie et les valeurs déjà tapées sont conservées en session le temps d'une requête (« flash »).

### 4.3 Routes

| Méthode | URL | Rôle |
|---|---|---|
| GET | `/` | Accueil |
| GET | `/lang/{fr\|en}?r=<chemin>` | Change la langue (cookie) et revient à la page |
| GET | `/cron?key=…` | Nettoyage (tâche planifiée) |
| GET / POST | `/create/{date\|classic}` | Formulaire / création d'un sondage |
| GET | `/p/{pid}` | Page publique (`?edit=<n°>` : éditer un vote) |
| POST | `/p/{pid}/vote` | Ajouter ou modifier un vote |
| POST | `/p/{pid}/vote/{vid}/delete` | Supprimer un vote |
| GET | `/p/{pid}/v/{vid}/{jeton}` | Lien personnel : mémorise le vote sur l'appareil |
| GET | `/a/{pid}/{jeton}` | Gestion par le créateur |
| GET | `/admin/poll/{pid}` | Gestion par un administrateur connecté |
| … | `{base}/vote`, `{base}/vote/{vid}/delete`, `{base}/settings`, `{base}/options`, `{base}/delete`, `{base}/export.csv` | Actions de gestion (`{base}` = l'une des deux URL précédentes) |
| GET | `/admin` | Liste des sondages (`?q=`, `?status=all\|open\|archived`, `?page=`) |
| GET / POST | `/admin/login` | Connexion |
| POST | `/admin/logout` | Déconnexion |
| POST | `/admin/purge` | Nettoyage immédiat |
| GET / POST | `/admin/users` | Liste / ajout d'administrateurs |
| POST | `/admin/users/{id}/delete` | Suppression d'un administrateur |
| POST | `/admin/password` | Changement de son mot de passe |
| GET / POST | `/admin/setup` | Installation web (seulement si `setup_token` est renseigné) |

### 4.4 Modèle de données

```
polls ─┬─< poll_options ─┐
       └─< votes ────────┴─< vote_choices
admins      login_attempts      schema_migrations
```

| Table | Contenu principal |
|---|---|
| `polls` | `public_id` (identifiant public de 16 caractères), `admin_token_hash` (SHA-256 du jeton créateur), `type` (`date`/`classic`), `title`, `description`, `author_name`, `author_email`, `edit_policy` (`own`/`all`/`none`), `allow_maybe`, `deadline`, `closed_at`, `ends_at` (fin calculée), `lang`, `created_at`, `updated_at`, `last_activity_at` |
| `poll_options` | `poll_id`, `option_date` (dates) ou `NULL`, `label` (horaire ou texte du choix), `position` |
| `votes` | `poll_id`, `name`, `edit_token_hash` (SHA-256 du jeton du votant), dates |
| `vote_choices` | `vote_id`, `option_id`, `value` (0 = non, 1 = peut-être, 2 = oui) |
| `admins` | `username`, `password_hash` (bcrypt), `last_login_at` |
| `login_attempts` | échecs de connexion par IP (limitation), purgés au-delà de 24 h |
| `schema_migrations` | migrations déjà appliquées |

Les suppressions sont en **cascade** : supprimer un sondage supprime ses choix, ses votes et les réponses.

Toutes les dates sont stockées en heure locale (`Europe/Zurich` par défaut) et calculées par PHP. Le code n'utilise pas `NOW()` en SQL.

### 4.5 Ajouter une migration

Créer `migrations/002_description.sql` (les fichiers sont appliqués dans l'ordre alphabétique), puis lancer `php bin/migrate.php`, ou valider le formulaire `/admin/setup` avec le jeton d'installation. Une migration n'est jamais rejouée.

### 4.6 Ajouter ou modifier un texte

Tous les textes sont dans `lang/fr.php` et `lang/en.php`. On les utilise dans le code avec `t('cle', ['param' => …])`, et `{param}` est remplacé dans le texte. Le test `I18nTest::testAllKeysTranslated` vérifie que les deux fichiers ont les mêmes clés et que toutes les clés utilisées dans le code existent.

---

## 5. Configuration

Fichier `config/config.php`, à créer à partir de `config/config.example.php`. Il ne doit **jamais** être versionné. On peut indiquer un autre emplacement avec la variable d'environnement `APP_CONFIG`.

| Clé | Défaut | Description |
|---|---|---|
| `app.name` | `BC Swing – Sondages` | Nom affiché (titre des pages, emails) |
| `app.base_url` | — | URL publique **sans slash final**, ex. `https://doodle2.bcswing.com`. Un sous-dossier est possible. |
| `app.timezone` | `Europe/Zurich` | Fuseau horaire |
| `app.default_lang` | `fr` | Langue si le navigateur ne propose ni `fr` ni `en` |
| `app.retention_months` | `6` | Durée de conservation après la clôture (ou sans activité) |
| `app.cron_key` | `''` | Clé de l'URL `/cron`. Vide : URL désactivée. |
| `app.setup_token` | `''` | Jeton de `/admin/setup`. **À vider après l'installation.** |
| `app.debug` | `false` | Affiche le détail des erreurs. **Jamais en production.** |
| `db.host` / `port` / `name` / `user` / `password` | — | Connexion MariaDB |
| `mail.driver` | `smtp` | `smtp`, `graph`, `log` (fichier `var/log/mail.log`) ou `none` |
| `mail.from_email` / `from_name` | — | Expéditeur (SMTP) |
| `mail.smtp.host` / `port` / `encryption` / `username` / `password` | — | `encryption` : `ssl`, `tls` ou `''` |
| `mail.graph.tenant_id` / `client_id` / `client_secret` / `sender` | — | Voir le [§ 9.2](#92-microsoft-graph-exchange-online) |

Pour générer une clé aléatoire (`cron_key`, `setup_token`) :

```bash
php -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
```

---

## 6. Développement local avec Docker

### 6.1 Services

| Service | Image | Accès |
|---|---|---|
| `app` | PHP 8.3 + Apache (racine web : `public/`) | http://localhost:8088 (port défini dans `.env`) |
| `db` | MariaDB 10.11 (bases `doodle2` et `doodle2_test`) | réseau interne |
| `mailpit` | capture des emails | http://localhost:8025 |

L'environnement Docker utilise `docker/config.php` : mode debug, emails envoyés vers Mailpit, `cron_key = dev-cron-key`, `setup_token = dev-setup-token`.

### 6.2 Ports (`.env`)

Le fichier `.env`, non versionné, définit les ports locaux :

```dotenv
APP_PORT=8088
MAILPIT_PORT=8025
```

Sans `.env`, les ports par défaut sont 8080 et 8025. Si l'un d'eux est déjà pris par un autre projet, le démarrage échoue avec l'erreur `port is already allocated` : il suffit alors de changer la valeur dans `.env`.

### 6.3 Premier démarrage

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php bin/migrate.php
docker compose exec app php bin/create-admin.php admin     # demande le mot de passe
```

Le code est monté dans le conteneur : une modification des fichiers est visible immédiatement, sans reconstruire l'image.

### 6.4 Commandes utiles

```bash
docker compose logs -f app                    # journaux Apache/PHP
docker compose exec app tail -f var/log/app.log
docker compose exec db mariadb -udoodle2 -pdoodle2 doodle2     # console SQL
docker compose down                           # arrêt (les données sont conservées)
docker compose down -v                        # arrêt + suppression de la base
```

---

## 7. Tests

| Suite | Dossier | Contenu | Prérequis |
|---|---|---|---|
| `unit` | `tests/Unit` | Détection de la langue, formats de date, traductions complètes, routeur, découpage des migrations, client Graph (HTTP simulé) | aucun |
| `integration` | `tests/Integration` | Règles métier sur une vraie base : validation, dates et horaires, droits `own`/`all`/`none`, date limite, clôture et réouverture, modification des choix, résultats, purge à 6 mois | base `doodle2_test` |
| `e2e` | `tests/E2E` | Parcours complets via Apache : création, email reçu dans Mailpit, votes, droits, lien personnel, CSRF, clôture, export CSV, suppression, connexion et modération admin, `/cron`, langues | stack Docker démarrée |

```bash
# Unitaires + intégration (la base de test est recréée à chaque exécution)
docker compose exec app vendor/bin/phpunit --testsuite unit,integration

# Bout en bout (crée l'administrateur « e2e-admin » dans la base de dev)
docker compose exec -e E2E_BASE_URL=http://localhost app vendor/bin/phpunit --testsuite e2e
```

Sans `E2E_BASE_URL`, la suite `e2e` est ignorée (« skipped »).

---

## 8. Déploiement sur Infomaniak

### 8.1 Prérequis

- Hébergement web Infomaniak avec PHP ≥ 8.1 et les extensions `pdo_mysql`, `mbstring`, `curl` (Graph), `openssl`.
- Une base MariaDB.
- Le domaine ou sous-domaine `doodle2.bcswing.com`.

### 8.2 Installation pas à pas

1. **Base de données** : dans le Manager, ouvrir *Hébergement web → Bases de données*, puis créer une base et un utilisateur avec tous les droits sur cette base. Noter l'hôte (`xxxx.myd.infomaniak.com`), le nom de la base, l'utilisateur et le mot de passe.
2. **Site** : ajouter le site `doodle2.bcswing.com`, choisir une **version de PHP ≥ 8.1**, puis activer le **certificat Let's Encrypt**.
3. **Dossier racine** : dans les paramètres avancés du site, faire pointer le dossier racine sur `…/doodle2/public`.
   *Si ce n'est pas possible*, le `.htaccess` à la racine du projet redirige automatiquement vers `public/`.
4. **Préparer les dépendances**, en local :
   ```bash
   docker compose run --rm app composer install --no-dev --optimize-autoloader
   ```
   On peut aussi lancer `composer install --no-dev` directement en SSH sur Infomaniak.
5. **Envoyer les fichiers** par SFTP : tout le projet **sauf** `tests/`, `docker/`, `docker-compose.yml`, `.env`, `.git/` et `phpunit.xml`.
6. **Configurer** : copier `config/config.example.php` en `config/config.php`, puis renseigner :
   - `base_url` ;
   - la base de données ;
   - l'envoi des emails ;
   - une `cron_key` ;
   - un `setup_token` provisoire.
7. **Initialiser la base et le premier administrateur** :
   - *avec SSH* :
     ```bash
     php bin/migrate.php
     php bin/create-admin.php <identifiant>
     ```
   - *sans SSH* : ouvrir `https://doodle2.bcswing.com/admin/setup`, puis saisir le `setup_token`, un identifiant et un mot de passe.
8. **Vider `setup_token`** dans `config/config.php`.
9. **Droits** : le dossier `var/` (et `var/log/`) doit être accessible en écriture par PHP.
10. **Tâche planifiée** (facultative, car le nettoyage automatique suffit) : dans *Hébergement → Tâches planifiées (cron)*, créer une tâche quotidienne qui appelle :
    ```
    https://doodle2.bcswing.com/cron?key=<cron_key>
    ```
11. **Vérifier** :
    - la page d'accueil s'affiche ;
    - un sondage de test peut être créé, et l'email arrive ;
    - la connexion à `/admin` fonctionne.

### 8.3 Mettre à jour l'application

1. Sauvegarder la base (*Manager → Bases de données → Sauvegarde*, ou `mysqldump`).
2. Envoyer les fichiers modifiés (`src/`, `templates/`, `lang/`, `public/`, `migrations/`, `vendor/` si les dépendances ont changé). **Ne pas écraser `config/config.php`.**
3. Appliquer les nouvelles migrations : `php bin/migrate.php`, ou passer par `/admin/setup` avec un `setup_token` provisoire.

---

## 9. Envoi des emails

L'application envoie **un seul email** : à la création d'un sondage, au créateur (s'il a indiqué son adresse), avec le lien public et le lien d'administration.

Si l'envoi échoue, le sondage est tout de même créé, un avertissement s'affiche et l'erreur est inscrite dans `var/log/app.log`.

### 9.1 SMTP

| Fournisseur | `host` | `port` | `encryption` | Identifiants |
|---|---|---|---|---|
| Infomaniak | `mail.infomaniak.com` | 465 / 587 | `ssl` / `tls` | adresse et mot de passe de la boîte |
| Exchange Online | `smtp.office365.com` | 587 | `tls` | boîte avec **SMTP AUTH activé** |

Microsoft supprime progressivement l'authentification SMTP « basique » sur Exchange Online : pour le club, **Graph est recommandé**.

### 9.2 Microsoft Graph (Exchange Online)

1. Dans le **portail Entra ID**, ouvrir *Inscriptions d'applications → Nouvelle inscription* (par exemple « BC Swing Sondages », un seul locataire).
2. Dans *Autorisations d'API*, ajouter *Microsoft Graph → Autorisations d'application → **Mail.Send***, puis cliquer sur **Accorder le consentement administrateur**.
3. Dans *Certificats et secrets*, créer un **secret client** et noter sa valeur et sa **date d'expiration**. Il faudra le renouveler avant cette date.
4. Créer ou choisir la **boîte expéditrice**, par exemple une boîte partagée `sondages@bcswing.com`.
5. **Restreindre l'application à cette boîte (fortement recommandé)**. Sans restriction, `Mail.Send` permet d'envoyer depuis n'importe quelle boîte du tenant. Exemple en PowerShell Exchange Online :
   ```powershell
   New-DistributionGroup -Name "App-Sondages" -Type Security -Members sondages@bcswing.com
   New-ApplicationAccessPolicy -AppId <client_id> -PolicyScopeGroupId App-Sondages@bcswing.com `
       -AccessRight RestrictAccess -Description "Sondages BC Swing : envoi limité"
   Test-ApplicationAccessPolicy -AppId <client_id> -Identity sondages@bcswing.com
   ```
   L'alternative plus récente est *RBAC for Applications* : `New-ManagementScope` puis `New-ManagementRoleAssignment -Role "Application Mail.Send"`.
6. Dans `config/config.php` :
   ```php
   'mail' => [
       'driver' => 'graph',
       'graph' => [
           'tenant_id' => '<ID du locataire>',
           'client_id' => '<ID d’application>',
           'client_secret' => '<secret>',
           'sender' => 'sondages@bcswing.com',
       ],
   ],
   ```

Fonctionnement : l'application obtient un jeton OAuth2 (*client credentials*) auprès de `login.microsoftonline.com`, puis appelle `POST https://graph.microsoft.com/v1.0/users/{sender}/sendMail`. Les messages ne sont pas conservés dans les éléments envoyés. Le nom d'expéditeur affiché est celui de la boîte (`from_name` ne s'applique pas avec Graph).

---

## 10. Exploitation et maintenance

| Tâche | Comment |
|---|---|
| Consulter les erreurs | `var/log/app.log` (connexion, emails, exceptions, nettoyages) |
| Forcer le nettoyage | `/admin` → *Lancer le nettoyage maintenant*, ou `php bin/cron.php` |
| Mot de passe administrateur oublié | En SSH : `php bin/create-admin.php <identifiant>` (réinitialise le mot de passe), ou demander à un autre administrateur de recréer le compte |
| Plus aucun administrateur | Recréer un compte en SSH (`bin/create-admin.php`) ou insérer une ligne dans `admins`, avec un hash `password_hash()` |
| Sauvegarde | Sauvegardes automatiques Infomaniak, ou export de la base. Seule la base de données contient des données ; aucun fichier n'est envoyé par les utilisateurs. |
| Changer la durée de conservation | `app.retention_months`, effet immédiat sur les calculs |
| Renouveler le secret Graph | Créer un nouveau secret dans Entra ID, mettre à jour `client_secret`, puis supprimer l'ancien |
| Débloquer une IP après trop d'échecs de connexion | Attendre 15 minutes, ou `DELETE FROM login_attempts WHERE ip = '…'` |

---

## 11. Sécurité

- **Jetons** :
  - identifiant public : 16 caractères aléatoires ;
  - jeton d'administration : 32 caractères ;
  - jeton de vote : 24 caractères, tous tirés avec `random_int`.
  
  Seul leur **hash SHA-256** est stocké, et la comparaison se fait en temps constant (`hash_equals`).
- **CSRF** : jeton de session obligatoire sur tous les formulaires `POST`.
- **Cookies** : `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS. L'identifiant de session est régénéré à la connexion.
- **Mots de passe** : `password_hash` (bcrypt), 10 caractères minimum, ré-hachage automatique si l'algorithme évolue.
- **Force brute** : 10 échecs en 15 minutes depuis une même IP bloquent la connexion. Un hash est calculé même pour un identifiant inexistant, pour garder un temps de réponse comparable.
- **En-têtes** :
  - `Content-Security-Policy` stricte : aucun script ni style externe ou en ligne ;
  - `Referrer-Policy: no-referrer`, pour que les liens secrets ne fuient pas vers d'autres sites ;
  - `X-Frame-Options: DENY` et `X-Content-Type-Options: nosniff` ;
  - `Cache-Control: no-store` et HSTS en HTTPS.
- **Injections** :
  - requêtes SQL préparées ;
  - sorties HTML échappées (`e()`) ;
  - formules neutralisées dans l'export CSV ;
  - redirection de langue limitée aux chemins internes.
- **Anti-spam** : champ piège invisible (honeypot) sur le formulaire de création.
- **Indexation** : `noindex, nofollow` sur toutes les pages.
- **Fichiers** : seul `public/` est exposé. Les autres dossiers sont protégés par `.htaccess`.

---

## 12. Dépannage

| Symptôme | Cause probable / solution |
|---|---|
| `Bind for 0.0.0.0:8080 failed: port is already allocated` (Docker) | Le port est utilisé par un autre projet : changer `APP_PORT` dans `.env` |
| Page « Dépendances manquantes » | `vendor/` absent : lancer `composer install --no-dev` |
| « Fichier de configuration introuvable » | Créer `config/config.php` à partir de l'exemple |
| Erreur 500 dès l'accueil | Consulter `var/log/app.log`. Le plus souvent, les identifiants de la base sont faux ou les tables n'existent pas : lancer `bin/migrate.php` ou `/admin/setup`. |
| Toutes les URL (sauf l'accueil) donnent 404 Apache | `mod_rewrite` inactif ou `.htaccess` non envoyé (fichier caché) |
| Boucle de redirection HTTPS | Certificat non activé, ou proxy qui n'envoie pas `X-Forwarded-Proto` : activer Let's Encrypt |
| « La session a expiré » à l'envoi d'un formulaire | Cookie de session bloqué, ou page restée ouverte trop longtemps : recharger la page |
| Email non reçu | Voir `var/log/app.log`. SMTP : identifiants ou port. Graph : `AADSTS7000215` (secret invalide ou expiré), `ErrorAccessDenied` (consentement manquant ou boîte hors de l'Application Access Policy). |
| Un participant ne peut plus modifier son vote | Règle « chacun son vote » et autre navigateur ou cookies effacés : utiliser le lien personnel, ou demander au créateur |
| Un sondage a disparu | Supprimé à la fin de la conservation, par le créateur ou par un administrateur |

---

## 13. Évolutions possibles

- Commentaires sous chaque sondage.
- Notification au créateur à chaque nouveau vote (option à la création).
- Rappel par email avant la date limite ou avant la suppression.
- Export iCalendar (`.ics`) de la date retenue.
- Import des sondages Framadate existants.
- Connexion administrateur via Microsoft Entra ID (SSO).
- Langue allemande : ajouter `lang/de.php` et `'de'` dans `I18n::SUPPORTED`.
