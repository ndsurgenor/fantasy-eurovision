<?php

declare(strict_types=1);

namespace App\Controllers;

class AdminController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        // requireAdmin() is called per-method so login/logout are accessible unauthenticated
    }

    // -------------------------------------------------------------------------
    // Admin auth
    // -------------------------------------------------------------------------

    public function loginForm(): void
    {
        if (isAdmin()) {
            $this->redirect('/admin');
        }
        $this->render('admin/login.twig');
    }

    public function login(): void
    {
        if (isAdmin()) {
            $this->redirect('/admin');
        }

        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password =                  $_POST['password'] ?? '';
        $errors   = [];

        if (!$email || !$password) {
            $errors[] = 'Please enter your email and password.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_admin = 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                loginUser($user);
                $this->redirect('/admin');
            }
            $errors[] = 'Invalid credentials or account is not an admin.';
        }

        $this->render('admin/login.twig', ['errors' => $errors, 'email' => $email]);
    }

    public function logout(): void
    {
        logoutUser();
        $this->redirect('/admin/login');
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function dashboard(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $contest = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();

        $stats = ['players' => 0, 'entries' => 0, 'countries' => 0, 'groups' => 0];
        $stats['players'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn();

        if ($contest) {
            foreach ([
                'entries'   => 'SELECT COUNT(*) FROM entries WHERE contest_id = ?',
                'countries' => 'SELECT COUNT(*) FROM countries WHERE contest_id = ?',
                'groups'    => 'SELECT COUNT(*) FROM contest_groups WHERE contest_id = ?',
            ] as $key => $sql) {
                $s = $pdo->prepare($sql);
                $s->execute([(int) $contest['id']]);
                $stats[$key] = (int) $s->fetchColumn();
            }
        }

        $this->render('admin/dashboard.twig', ['stats' => $stats]);
    }

    public function contestForm(): void
    {
        requireAdmin();
        $this->render('admin/contest.twig', [
            'valid_statuses' => ['setup', 'open', 'locked', 'scored'],
        ]);
    }

    public function contest(): void
    {
        requireAdmin();

        $pdo        = getDB();
        $contest    = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        $validStatuses = ['setup', 'open', 'locked', 'scored'];

        $year   = (int)   ($_POST['year']         ?? 0);
        $name   = trim(    $_POST['name']          ?? '');
        $budget = (float)  ($_POST['budget_limit'] ?? 0);
        $status = trim(    $_POST['status']        ?? '');
        $errors = [];

        if ($year < 2000 || $year > 2100)               $errors[] = 'Please enter a valid year.';
        if (!$name)                                      $errors[] = 'Contest name is required.';
        if ($budget <= 0)                                $errors[] = 'Budget must be greater than zero.';
        if (!in_array($status, $validStatuses, true))    $errors[] = 'Invalid status.';

        if (!empty($errors)) {
            $this->render('admin/contest.twig', [
                'errors'         => $errors,
                'valid_statuses' => $validStatuses,
            ]);
            return;
        }

        $prevStatus = $contest['status'] ?? null;
        $contestId  = null;

        if (!$contest) {
            $stmt = $pdo->prepare('INSERT INTO contests (year, name, budget_limit, status) VALUES (?, ?, ?, ?)');
            $stmt->execute([$year, $name, $budget, $status]);
            $contestId = (int) $pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare('UPDATE contests SET year=?, name=?, budget_limit=?, status=? WHERE id=?');
            $stmt->execute([$year, $name, $budget, $status, (int) $contest['id']]);
            $contestId = (int) $contest['id'];
        }

        if ($status === 'scored' && $prevStatus !== 'scored') {
            recalculateAllEntries($contestId);
        }

        $this->flash('success', $contest ? 'Contest updated.' : 'Contest created.');
        $this->redirect('/admin/contest');
    }

    public function groupsForm(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $contest = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }

        $groups    = $this->fetchGroups($pdo, (int) $contest['id']);
        $editGroup = $this->findById($groups, (int) ($_GET['edit'] ?? 0));

        $this->render('admin/groups.twig', [
            'groups'     => $groups,
            'edit_group' => $editGroup,
        ]);
    }

    public function groups(): void
    {
        requireAdmin();

        $pdo       = getDB();
        $contest   = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }
        $contestId = (int) $contest['id'];

        $action     = $_POST['action']    ?? '';
        $groupId    = (int) ($_POST['group_id']   ?? 0);
        $name       = trim($_POST['name']          ?? '');
        $isWildcard = isset($_POST['is_wildcard']) ? 1 : 0;
        $sortOrder  = (int) ($_POST['sort_order']  ?? 0);
        $errors     = [];

        if (in_array($action, ['create', 'edit'], true) && !$name) {
            $errors[] = 'Group name is required.';
        }
        if ($isWildcard && empty($errors)) {
            $excludeId = $action === 'edit' ? $groupId : 0;
            $wc = $pdo->prepare('SELECT id FROM contest_groups WHERE contest_id=? AND is_wildcard=1 AND id!=?');
            $wc->execute([$contestId, $excludeId]);
            if ($wc->fetch()) $errors[] = 'A wildcard group already exists for this contest.';
        }

        if (!empty($errors)) {
            $this->render('admin/groups.twig', [
                'groups'     => $this->fetchGroups($pdo, $contestId),
                'edit_group' => null,
                'errors'     => $errors,
            ]);
            return;
        }

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO contest_groups (contest_id, name, is_wildcard, sort_order) VALUES (?,?,?,?)')
                ->execute([$contestId, $name, $isWildcard, $sortOrder]);
            $this->flash('success', 'Group created.');

        } elseif ($action === 'edit') {
            $pdo->prepare('UPDATE contest_groups SET name=?, is_wildcard=?, sort_order=? WHERE id=? AND contest_id=?')
                ->execute([$name, $isWildcard, $sortOrder, $groupId, $contestId]);
            $this->flash('success', 'Group updated.');

        } elseif ($action === 'delete') {
            $check = $pdo->prepare('SELECT COUNT(*) FROM countries WHERE group_id = ?');
            $check->execute([$groupId]);
            if ((int) $check->fetchColumn() > 0) {
                $this->render('admin/groups.twig', [
                    'groups'     => $this->fetchGroups($pdo, $contestId),
                    'edit_group' => null,
                    'errors'     => ['Cannot delete a group that has countries assigned to it.'],
                ]);
                return;
            }
            $pdo->prepare('DELETE FROM contest_groups WHERE id=? AND contest_id=?')->execute([$groupId, $contestId]);
            $this->flash('success', 'Group deleted.');
        }

        $this->redirect('/admin/groups');
    }

    public function countriesForm(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $contest = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }
        $contestId = (int) $contest['id'];

        $gc = $pdo->prepare('SELECT COUNT(*) FROM contest_groups WHERE contest_id=?');
        $gc->execute([$contestId]);
        if ((int) $gc->fetchColumn() === 0) { $this->redirect('/admin/groups'); return; }

        [$groups, $countries] = $this->loadGroupsAndCountriesForAdmin($pdo, $contestId);
        $editCountry = $this->findById($countries, (int) ($_GET['edit'] ?? 0));

        $this->render('admin/countries.twig', [
            'groups'       => $groups,
            'countries'    => $countries,
            'edit_country' => $editCountry,
        ]);
    }

    public function countries(): void
    {
        requireAdmin();

        $pdo       = getDB();
        $contest   = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }
        $contestId = (int) $contest['id'];

        $action    = $_POST['action']      ?? '';
        $countryId = (int) ($_POST['country_id']   ?? 0);
        $name      = trim($_POST['name']            ?? '');
        $flagEmoji = trim($_POST['flag_emoji']      ?? '');
        $price     = (float) ($_POST['price']       ?? 0);
        $groupId   = (int) ($_POST['group_id']      ?? 0);
        $rawOrder  = $_POST['running_order']        ?? '';
        $runOrder  = $rawOrder !== '' ? (int) $rawOrder : null;
        $errors    = [];

        if (in_array($action, ['create', 'edit'], true)) {
            if (!$name)      $errors[] = 'Country name is required.';
            if ($price <= 0) $errors[] = 'Price must be greater than zero.';
            if (!$groupId) {
                $errors[] = 'Please select a group.';
            } else {
                $gc = $pdo->prepare('SELECT id FROM contest_groups WHERE id=? AND contest_id=?');
                $gc->execute([$groupId, $contestId]);
                if (!$gc->fetch()) $errors[] = 'Invalid group selected.';
            }
        }

        if (!empty($errors)) {
            [$groups, $countries] = $this->loadGroupsAndCountriesForAdmin($pdo, $contestId);
            $this->render('admin/countries.twig', [
                'groups'       => $groups,
                'countries'    => $countries,
                'edit_country' => null,
                'errors'       => $errors,
            ]);
            return;
        }

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO countries (contest_id, group_id, name, flag_emoji, price, running_order) VALUES (?,?,?,?,?,?)')
                ->execute([$contestId, $groupId, $name, $flagEmoji, $price, $runOrder]);
            $this->flash('success', 'Country added.');

        } elseif ($action === 'edit') {
            $pdo->prepare('UPDATE countries SET group_id=?, name=?, flag_emoji=?, price=?, running_order=? WHERE id=? AND contest_id=?')
                ->execute([$groupId, $name, $flagEmoji, $price, $runOrder, $countryId, $contestId]);
            $this->flash('success', 'Country updated.');

        } elseif ($action === 'delete') {
            $check = $pdo->prepare('SELECT COUNT(*) FROM entry_countries WHERE country_id=?');
            $check->execute([$countryId]);
            if ((int) $check->fetchColumn() > 0) {
                [$groups, $countries] = $this->loadGroupsAndCountriesForAdmin($pdo, $contestId);
                $this->render('admin/countries.twig', [
                    'groups'       => $groups,
                    'countries'    => $countries,
                    'edit_country' => null,
                    'errors'       => ['Cannot delete a country that is part of a submitted entry.'],
                ]);
                return;
            }
            $pdo->prepare('DELETE FROM countries WHERE id=? AND contest_id=?')->execute([$countryId, $contestId]);
            $this->flash('success', 'Country deleted.');
        }

        $this->redirect('/admin/countries');
    }

    public function scoresForm(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $contest = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }

        $stmt = $pdo->prepare(
            'SELECT c.*, cg.name AS group_name
               FROM countries c
               JOIN contest_groups cg ON cg.id = c.group_id
              WHERE c.contest_id = ?
              ORDER BY c.running_order, c.name'
        );
        $stmt->execute([(int) $contest['id']]);

        $this->render('admin/scores.twig', ['countries' => $stmt->fetchAll()]);
    }

    public function scores(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $contest = $pdo->query('SELECT * FROM contests ORDER BY id DESC LIMIT 1')->fetch();
        if (!$contest) { $this->redirect('/admin/contest'); return; }
        $contestId = (int) $contest['id'];

        $stmt = $pdo->prepare('UPDATE countries SET final_score_raw=? WHERE id=? AND contest_id=?');
        foreach ($_POST['scores'] ?? [] as $rawId => $rawScore) {
            $score = ($rawScore === '' || $rawScore === null) ? null : (int) $rawScore;
            $stmt->execute([$score, (int) $rawId, $contestId]);
        }

        recalculateAllEntries($contestId);
        $this->flash('success', 'Scores saved and entries recalculated.');
        $this->redirect('/admin/scores');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fetchGroups(\PDO $pdo, int $contestId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM contest_groups WHERE contest_id=? ORDER BY sort_order, id');
        $stmt->execute([$contestId]);
        return $stmt->fetchAll();
    }

    private function loadGroupsAndCountriesForAdmin(\PDO $pdo, int $contestId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM contest_groups WHERE contest_id=? ORDER BY sort_order');
        $stmt->execute([$contestId]);
        $groups = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT c.*, cg.name AS group_name
               FROM countries c
               JOIN contest_groups cg ON cg.id = c.group_id
              WHERE c.contest_id = ?
              ORDER BY cg.sort_order, c.running_order, c.name'
        );
        $stmt->execute([$contestId]);
        return [$groups, $stmt->fetchAll()];
    }

    private function findById(array $items, int $id): ?array
    {
        if (!$id) return null;
        foreach ($items as $item) {
            if ((int) $item['id'] === $id) return $item;
        }
        return null;
    }
}
