<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\I18n;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class I18nTest extends TestCase
{
    public static function detectionCases(): array
    {
        return [
            'cookie prioritaire' => ['en', 'fr-CH,fr;q=0.9', 'en'],
            'cookie invalide ignoré' => ['de', 'en-US,en;q=0.9', 'en'],
            'navigateur anglais' => [null, 'en-GB,en;q=0.9,fr;q=0.8', 'en'],
            'navigateur français' => [null, 'fr-CH,fr;q=0.9,en;q=0.8', 'fr'],
            'allemand puis anglais' => [null, 'de-CH,de;q=0.9,en;q=0.7', 'en'],
            'q prioritaire sur l\'ordre' => [null, 'en;q=0.5,fr;q=0.9', 'fr'],
            'langue non gérée' => [null, 'it-CH,it;q=0.9', 'fr'],
            'aucun en-tête' => [null, null, 'fr'],
            'q=0 exclu' => [null, 'en;q=0,fr;q=0.1', 'fr'],
        ];
    }

    #[DataProvider('detectionCases')]
    public function testDetect(?string $cookie, ?string $header, string $expected): void
    {
        self::assertSame($expected, I18n::detect($cookie, $header, 'fr'));
    }

    public function testFormatting(): void
    {
        $i18n = new I18n(dirname(__DIR__, 2) . '/lang', 'fr');
        self::assertSame('ven. 20 nov. 2026', $i18n->formatDay('2026-11-20', true));
        self::assertSame('Bonjour Zoé,', $i18n->t('mail.hello', ['name' => 'Zoé']));
        $en = $i18n->forLang('en');
        self::assertSame('Fri 20 Nov 2026', $en->formatDay('2026-11-20', true));
        self::assertSame('fr', $i18n->lang(), 'forLang ne modifie pas l\'instance d\'origine');
    }

    /** Toutes les clés utilisées dans le code existent en français et en anglais. */
    public function testAllKeysTranslated(): void
    {
        $root = dirname(__DIR__, 2);
        $fr = require $root . '/lang/fr.php';
        $en = require $root . '/lang/en.php';
        self::assertSame([], array_values(array_diff(array_keys($fr), array_keys($en))), 'clés absentes de en.php');
        self::assertSame([], array_values(array_diff(array_keys($en), array_keys($fr))), 'clés absentes de fr.php');

        $used = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/src'));
        $paths = [];
        foreach ($files as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $paths[] = $f->getPathname();
            }
        }
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/templates'));
        foreach ($files as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $paths[] = $f->getPathname();
            }
        }
        foreach ($paths as $path) {
            $code = (string) file_get_contents($path);
            // Littéraux qui ressemblent à des clés : 'error.xxx', 'admin.yyy.zzz', …
            preg_match_all("/'((?:nav|common|home|create|form|policy|poll|results|summary|vote|manage|flash|csv|error|mail|admin|time)\.[a-z0-9_.]+)'/", $code, $m);
            foreach ($m[1] as $key) {
                $used[$key][] = basename($path);
            }
        }
        // Clés construites dynamiquement dans les templates.
        foreach (['own', 'all', 'none'] as $p) {
            $used['policy.' . $p] = $used['policy.' . $p . '.desc'] = $used['poll.policy_info.' . $p] = ['dynamic'];
        }
        foreach (['all', 'open', 'archived'] as $s) {
            $used['admin.filter.' . $s] = ['dynamic'];
        }

        $missing = array_diff(array_keys($used), array_keys($fr));
        // Les noms de routes et de champs ne sont pas des clés de traduction.
        $missing = array_filter($missing, static fn ($k) => !str_ends_with($k, '.csv') && !str_ends_with($k, '.php') && !str_ends_with($k, '.'));
        self::assertSame([], array_values($missing), 'clés de traduction manquantes');
    }
}
