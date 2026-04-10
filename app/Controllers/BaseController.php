<?php

declare(strict_types=1);

namespace App\Controllers;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

abstract class BaseController
{
    protected Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader(dirname(__DIR__) . '/templates');

        $this->twig = new Environment($loader, [
            'cache'      => false,
            'autoescape' => 'html',
        ]);

        $this->twig->addGlobal('site_name',    SITE_NAME);
        $this->twig->addGlobal('current_path', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $this->twig->addGlobal('current_year', (int) date('Y'));
        $this->twig->addGlobal('is_logged_in', isLoggedIn());
        $this->twig->addGlobal('is_admin',     isAdmin());
        $this->twig->addGlobal('user_name',    $_SESSION['name'] ?? null);

        // Active contest — available to every template
        $contest = getDB()->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        $this->twig->addGlobal('contest', $contest ?: null);

        // Flash message — consume from session so it only shows once
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        $this->twig->addGlobal('flash', $flash);
    }

    protected function render(string $template, array $data = []): void
    {
        echo $this->twig->render($template, $data);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}
