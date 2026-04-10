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

// Contest
$router->get('/',            [ContestController::class, 'index']);
$router->get('/select',      [ContestController::class, 'selectForm']);
$router->post('/select',     [ContestController::class, 'submitSelect']);
$router->get('/my-team',     [ContestController::class, 'myTeam']);
$router->get('/leaderboard', [ContestController::class, 'leaderboard']);

// Admin auth
$router->get('/admin/login',       [AdminController::class, 'loginForm']);
$router->post('/admin/login',      [AdminController::class, 'login']);
$router->get('/admin/logout',      [AdminController::class, 'logout']);

// Admin
$router->get('/admin',             [AdminController::class, 'dashboard']);
$router->get('/admin/contest',     [AdminController::class, 'contestForm']);
$router->post('/admin/contest',    [AdminController::class, 'contest']);
$router->get('/admin/groups',      [AdminController::class, 'groupsForm']);
$router->post('/admin/groups',     [AdminController::class, 'groups']);
$router->get('/admin/countries',   [AdminController::class, 'countriesForm']);
$router->post('/admin/countries',  [AdminController::class, 'countries']);
$router->get('/admin/scores',      [AdminController::class, 'scoresForm']);
$router->post('/admin/scores',     [AdminController::class, 'scores']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
