<?php
declare(strict_types=1);

use App\App;

/** Échappe une valeur pour l'insérer dans du HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Traduit une clé dans la langue courante. */
function t(string $key, array $params = []): string
{
    return App::get()->i18n->t($key, $params);
}

/** Chemin interne (préfixé par le sous-dossier éventuel de l'application). */
function url(string $path = '/'): string
{
    return App::get()->url($path);
}

/** Message d'erreur sous un champ de formulaire. */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="err-' . e($field) . '">' . e(t($errors[$field])) . '</p>';
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App::get()->session->csrfToken()) . '">';
}
