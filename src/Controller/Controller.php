<?php
declare(strict_types=1);

namespace App\Controller;

use App\App;
use App\Http\HttpException;
use App\Http\Response;

abstract class Controller
{
    public function __construct(protected readonly App $app)
    {
    }

    protected function render(string $template, array $params = [], int $status = 200): Response
    {
        return Response::html($this->app->view->render($template, $params), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($this->app->url($path));
    }

    protected function message(string $type, string $key, array $params = []): void
    {
        $this->app->session->addMessage($type, $key, $params);
    }

    protected function loadPoll(string $publicId): array
    {
        return $this->app->pollRepo()->findByPublicId($publicId) ?? throw new HttpException(404, 'error.poll_not_found');
    }

    protected function requireAdmin(): array
    {
        return $this->app->currentAdmin() ?? throw HttpException::redirect($this->app->url('/admin/login'));
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
