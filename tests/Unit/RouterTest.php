<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Migrator;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testParamsAndLiteralDots(): void
    {
        $router = new Router();
        $router->get('/a/{pid}/{token}/export.csv', static fn (Request $r, array $p) => Response::text($p['pid'] . '|' . $p['token']));
        $request = new Request('GET', '/a/abc/def/export.csv');

        self::assertSame('abc|def', $router->dispatch($request, '/a/abc/def/export.csv')?->body);
        self::assertNull($router->dispatch($request, '/a/abc/def/exportXcsv'));
        self::assertNull($router->dispatch($request, '/a/abc/def/ghi/export.csv'));
    }

    public function testMethodNotAllowed(): void
    {
        $router = new Router();
        $router->post('/p/{pid}/vote', static fn () => Response::text('ok'));
        $this->expectException(HttpException::class);
        $router->dispatch(new Request('GET', '/p/x/vote'), '/p/x/vote');
    }

    public function testMigrationSplitting(): void
    {
        $sql = "-- commentaire\nCREATE TABLE a (x INT);\n\nCREATE TABLE b (\n  y INT\n);\n";
        self::assertSame(['CREATE TABLE a (x INT)', "CREATE TABLE b (\n  y INT\n)"], Migrator::statements($sql));
    }
}
