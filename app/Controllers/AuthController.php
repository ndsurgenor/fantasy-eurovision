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
        if (strlen($name) > 16)                         $errors[] = 'Display name must be 16 characters or fewer.';
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

    public function profileForm(): void
    {
        requireLogin();
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $this->render('auth/profile.twig', ['user' => $stmt->fetch()]);
    }

    public function updateProfile(): void
    {
        requireLogin();

        $pdo    = getDB();
        $userId = (int) $_SESSION['user_id'];

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!empty($_POST['remove_avatar'])) {
            if ($user['avatar']) {
                $path = dirname(__DIR__, 2) . '/public/uploads/avatars/' . $user['avatar'];
                if (file_exists($path)) unlink($path);
                $pdo->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$userId]);
                $_SESSION['avatar'] = null;
            }
            $this->flash('success', 'Avatar removed.');
            $this->redirect('/profile');
            return;
        }

        $name           = trim($_POST['name']                 ?? '');
        $newPassword    =      $_POST['new_password']         ?? '';
        $confirmPassword =     $_POST['new_password_confirm'] ?? '';
        $errors         = [];

        if (!$name)          $errors[] = 'Display name cannot be empty.';
        if (strlen($name) > 16) $errors[] = 'Display name must be 16 characters or fewer.';

        if ($newPassword !== '' || $confirmPassword !== '') {
            if (strlen($newPassword) < 8)      $errors[] = 'New password must be at least 8 characters.';
            if ($newPassword !== $confirmPassword) $errors[] = 'New passwords do not match.';
        }

        $avatarFilename = $user['avatar'];

        if (!empty($_FILES['avatar']['name'])) {
            $file    = $_FILES['avatar'];
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed, true)) {
                $errors[] = 'Avatar must be a JPG, PNG, GIF, or WebP image.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Avatar must be under 2MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Avatar upload failed. Please try again.';
            } else {
                $filename = $userId . '_' . time() . '.' . $ext;
                $dest     = dirname(__DIR__, 2) . '/public/uploads/avatars/' . $filename;
                if ($this->saveResizedAvatar($file['tmp_name'], $dest, $ext)) {
                    if ($avatarFilename) {
                        $old = dirname(__DIR__, 2) . '/public/uploads/avatars/' . $avatarFilename;
                        if (file_exists($old)) unlink($old);
                    }
                    $avatarFilename = $filename;
                } else {
                    $errors[] = 'Could not save avatar. Please try again.';
                }
            }
        }

        if (!empty($errors)) {
            $this->render('auth/profile.twig', [
                'user'   => $user,
                'errors' => $errors,
                'name'   => $name,
            ]);
            return;
        }

        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, avatar = ? WHERE id = ?')
                ->execute([$name, $hash, $avatarFilename, $userId]);
        } else {
            $pdo->prepare('UPDATE users SET name = ?, avatar = ? WHERE id = ?')
                ->execute([$name, $avatarFilename, $userId]);
        }

        $_SESSION['name']   = $name;
        $_SESSION['avatar'] = $avatarFilename;

        $this->flash('success', 'Profile updated successfully.');
        $this->redirect('/profile');
    }

    public function logout(): void
    {
        logoutUser();
        $this->redirect('/login');
    }

    private function saveResizedAvatar(string $tmpPath, string $destPath, string $ext): bool
    {
        $info = @\getimagesize($tmpPath);
        if (!$info) return false;

        [$origW, $origH] = $info;
        $maxDim = 300;

        if ($origW <= $maxDim && $origH <= $maxDim) {
            $newW = $origW;
            $newH = $origH;
        } elseif ($origW >= $origH) {
            $newW = $maxDim;
            $newH = (int) round($origH * $maxDim / $origW);
        } else {
            $newH = $maxDim;
            $newW = (int) round($origW * $maxDim / $origH);
        }

        $src = match ($ext) {
            'jpg', 'jpeg' => @\imagecreatefromjpeg($tmpPath),
            'png'         => @\imagecreatefrompng($tmpPath),
            'gif'         => @\imagecreatefromgif($tmpPath),
            'webp'        => @\imagecreatefromwebp($tmpPath),
            default       => false,
        };
        if (!$src) return false;

        $dst = \imagecreatetruecolor($newW, $newH);

        if (in_array($ext, ['png', 'gif'], true)) {
            \imagecolortransparent($dst, \imagecolorallocatealpha($dst, 0, 0, 0, 127));
            \imagealphablending($dst, false);
            \imagesavealpha($dst, true);
        }

        \imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $result = match ($ext) {
            'jpg', 'jpeg' => \imagejpeg($dst, $destPath, 85),
            'png'         => \imagepng($dst, $destPath, 8),
            'gif'         => \imagegif($dst, $destPath),
            'webp'        => \imagewebp($dst, $destPath, 85),
            default       => false,
        };

        return (bool) $result;
    }
}
