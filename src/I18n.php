<?php
declare(strict_types=1);

namespace App;

use DateTimeImmutable;

final class I18n
{
    public const SUPPORTED = ['fr', 'en'];

    private string $lang;
    private array $messages = [];
    private array $fallback = [];

    public function __construct(private readonly string $dir, private readonly string $default = 'fr')
    {
        $this->fallback = $this->load($default);
        $this->setLang($default);
    }

    /**
     * Choisit la langue : cookie explicite, sinon en-tête Accept-Language du navigateur, sinon défaut.
     */
    public static function detect(?string $cookie, ?string $acceptLanguage, string $default): string
    {
        if ($cookie !== null && in_array($cookie, self::SUPPORTED, true)) {
            return $cookie;
        }
        if ($acceptLanguage !== null && $acceptLanguage !== '') {
            $candidates = [];
            foreach (explode(',', $acceptLanguage) as $i => $part) {
                $pieces = explode(';', trim($part));
                $tag = strtolower(trim($pieces[0]));
                $q = 1.0;
                foreach (array_slice($pieces, 1) as $param) {
                    if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                        $q = (float) $m[1];
                    }
                }
                if ($tag === '' || $q <= 0) {
                    continue;
                }
                // L'index sert à départager les égalités en gardant l'ordre du navigateur.
                $candidates[] = [$q, -$i, substr($tag, 0, 2)];
            }
            rsort($candidates);
            foreach ($candidates as [, , $primary]) {
                if (in_array($primary, self::SUPPORTED, true)) {
                    return $primary;
                }
            }
        }
        return in_array($default, self::SUPPORTED, true) ? $default : self::SUPPORTED[0];
    }

    public function setLang(string $lang): void
    {
        $this->lang = in_array($lang, self::SUPPORTED, true) ? $lang : $this->default;
        $this->messages = $this->lang === $this->default ? $this->fallback : $this->load($this->lang);
    }

    public function lang(): string
    {
        return $this->lang;
    }

    /** Nouvelle instance dans une autre langue (ex. pour un email). */
    public function forLang(string $lang): self
    {
        $clone = clone $this;
        $clone->setLang($lang);
        return $clone;
    }

    public function t(string $key, array $params = []): string
    {
        $text = $this->messages[$key] ?? $this->fallback[$key] ?? $key;
        if (!is_string($text)) {
            return $key;
        }
        foreach ($params as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }

    public function has(string $key): bool
    {
        return isset($this->messages[$key]);
    }

    /** Ex. « lun. 14 oct. » / « Mon 14 Oct » ; l'année est ajoutée si elle diffère de l'année courante. */
    public function formatDay(string $date, bool $forceYear = false): string
    {
        $d = new DateTimeImmutable($date);
        $days = $this->messages['_days_short'];
        $months = $this->messages['_months_short'];
        $text = strtr($this->messages['_day_format'], [
            '{wd}' => $days[(int) $d->format('N') - 1],
            '{d}' => $d->format('j'),
            '{mon}' => $months[(int) $d->format('n') - 1],
        ]);
        if ($forceYear || $d->format('Y') !== date('Y')) {
            $text .= ' ' . $d->format('Y');
        }
        return $text;
    }

    /** Éléments séparés pour l'en-tête des colonnes de dates. */
    public function dayParts(string $date): array
    {
        $d = new DateTimeImmutable($date);
        return [
            'wd' => $this->messages['_days_short'][(int) $d->format('N') - 1],
            'd' => $d->format('j'),
            'mon' => $this->messages['_months_short'][(int) $d->format('n') - 1],
            'y' => $d->format('Y') !== date('Y') ? $d->format('Y') : '',
        ];
    }

    public function formatDateTime(string $datetime): string
    {
        $d = new DateTimeImmutable($datetime);
        return $this->formatDay($d->format('Y-m-d'), true) . ' ' . $this->t('time.at') . ' ' . $d->format('H:i');
    }

    public function formatDate(string $datetime): string
    {
        return $this->formatDay((new DateTimeImmutable($datetime))->format('Y-m-d'), true);
    }

    private function load(string $lang): array
    {
        $file = $this->dir . '/' . $lang . '.php';
        return is_file($file) ? require $file : [];
    }
}
