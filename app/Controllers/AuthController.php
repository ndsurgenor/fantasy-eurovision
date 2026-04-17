<?php

declare(strict_types=1);

namespace App\Controllers;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        $this->render('auth/login.twig');
    }

    public function login(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }

        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password =                  $_POST['password'] ?? '';
        $errors   = [];

        if (!$email || !$password) {
            $errors[] = 'Please enter your email and password.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                $this->redirect('/');
            }
            $errors[] = 'Invalid email or password.';
        }

        $this->render('auth/login.twig', ['errors' => $errors, 'email' => $email]);
    }

    public function registerForm(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        $this->render('auth/register.twig');
    }

    public function register(): void
    {
        if (isLoggedIn()) {
            $this->redirect('/');
        }

        $contest = resolvePublicContest();

        if (!$contest || $contest['status'] !== 'open') {
            $this->redirect('/');
        }

        $pdo      = getDB();
        $name     = trim($_POST['name']             ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password =            $_POST['password']   ?? '';
        $confirm  =            $_POST['password_confirm'] ?? '';
        $errors   = [];

        if (!$name)                                     $errors[] = 'Please enter your display name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8)                      $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)                     $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'An account with that email already exists.';
            }
        }

        if (!empty($errors)) {
            $this->render('auth/register.twig', [
                'errors' => $errors,
                'name'   => $name,
                'email'  => $email,
            ]);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, 0)');
        $stmt->execute([$name, $email, $hash]);

        $newUser = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $newUser->execute([(int) $pdo->lastInsertId()]);
        loginUser($newUser->fetch());

        $this->redirect('/select');
    }

    public function logout(): void
    {
        logoutUser();
        $this->redirect('/login');
    }
}
