<?php
declare(strict_types=1);

namespace App;

final class View
{
    public function __construct(private readonly string $dir)
    {
    }

    public function render(string $template, array $params = [], ?string $layout = 'layout'): string
    {
        $content = $this->partial($template, $params);
        if ($layout === null) {
            return $content;
        }
        return $this->partial($layout, ['content' => $content] + $params);
    }

    public function partial(string $__template, array $__params = []): string
    {
        extract($__params, EXTR_SKIP);
        ob_start();
        try {
            include $this->dir . '/' . $__template . '.php';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
