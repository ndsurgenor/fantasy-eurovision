<?php

declare(strict_types=1);

// Bootstrap
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/scoring.php';
require_once dirname(__DIR__) . '/includes/validation.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\ContestController;
use App\Controllers\AdminController;

$router = new Router();

// Auth
$router->get('/login',     [AuthController::class, 'loginForm']);
$router->post('/login',    [AuthController::class, 'login']);
$router->get('/register',  [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout',    [AuthController::class, 'logout']);

// Contest (public)
$router->get('/',            [ContestController::class, 'index']);
$router->get('/select',      [ContestController::class, 'selectForm']);
$router->post('/select',     [ContestController::class, 'submitSelect']);
$router->get('/my-team',     [ContestController::class, 'myTeam']);
$router->get('/leaderboard', [ContestController::class, 'leaderboard']);

// Admin auth
$router->get('/admin/login',  [AdminController::class, 'loginForm']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->get('/admin/logout', [AdminController::class, 'logout']);

// Admin — dashboard
$router->get('/admin', [AdminController::class, 'dashboard']);

// Admin — contests
$router->get('/admin/contests',             [AdminController::class, 'contestsList']);
$router->post('/admin/contests/new',        [AdminController::class, 'newContest']);
$router->post('/admin/contests/duplicate',  [AdminController::class, 'duplicateContest']);
$router->get('/admin/contests/{id}',        [AdminController::class, 'contestDetail']);
$router->post('/admin/contests/{id}',       [AdminController::class, 'contestSave']);

// Admin — master catalogues
$router->get('/admin/countries',  [AdminController::class, 'countriesCatalogue']);
$router->post('/admin/countries', [AdminController::class, 'countriesCatalogueSave']);
$router->get('/admin/groups',     [AdminController::class, 'groupsCatalogue']);
$router->post('/admin/groups',    [AdminController::class, 'groupsCatalogueSave']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
