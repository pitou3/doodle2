<?php
// Copiez ce fichier en config/config.php et adaptez les valeurs.
// Ne versionnez JAMAIS config/config.php (il contient des secrets).

return [
    'app' => [
        'name' => 'BC Swing – Sondages',
        // URL publique, sans slash final. Un sous-dossier est possible (ex. https://bcswing.com/sondages).
        'base_url' => 'https://doodle2.bcswing.com',
        'timezone' => 'Europe/Zurich',
        'default_lang' => 'fr',
        // Durée de conservation (en mois) d'un sondage après sa clôture.
        'retention_months' => 6,
        // Clé secrète pour déclencher le nettoyage via https://…/cron?key=… (laisser vide pour désactiver l'URL).
        // Générer par ex. avec : php -r "echo bin2hex(random_bytes(24));"
        'cron_key' => '',
        // Jeton temporaire permettant d'initialiser la base et de créer le premier administrateur via /admin/setup.
        // À vider une fois l'installation terminée.
        'setup_token' => '',
        // Afficher le détail des erreurs (jamais en production).
        'debug' => false,
    ],

    'db' => [
        'host' => 'xxxx.myd.infomaniak.com',
        'port' => 3306,
        'name' => 'xxxx_doodle2',
        'user' => 'xxxx_doodle2',
        'password' => '',
    ],

    'mail' => [
        // smtp | graph | log (écrit dans var/log/mail.log) | none
        'driver' => 'smtp',
        'from_email' => 'sondages@bcswing.com',
        'from_name' => 'BC Swing – Sondages',

        'smtp' => [
            // Infomaniak : mail.infomaniak.com, port 465 (ssl) ou 587 (tls)
            // Exchange Online : smtp.office365.com, port 587 (tls) – nécessite SMTP AUTH activé sur la boîte
            'host' => 'mail.infomaniak.com',
            'port' => 465,
            'encryption' => 'ssl', // ssl | tls | '' (aucun)
            'username' => '',
            'password' => '',
        ],

        // Microsoft Graph (Exchange Online) – application Entra ID avec la permission
        // d'application « Mail.Send » (voir README).
        'graph' => [
            'tenant_id' => '',
            'client_id' => '',
            'client_secret' => '',
            // Boîte aux lettres expéditrice (doit exister dans le tenant)
            'sender' => 'sondages@bcswing.com',
        ],
    ],
];
