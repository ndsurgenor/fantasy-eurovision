<?php

declare(strict_types=1);

namespace App\Controllers;

class AdminController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
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

        $pdo  = getDB();
        $stats = [
            'players'  => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 0')->fetchColumn(),
            'contests' => (int) $pdo->query('SELECT COUNT(*) FROM contests')->fetchColumn(),
            'active'   => $pdo->query("SELECT * FROM contests WHERE is_active = 1 LIMIT 1")->fetch() ?: null,
        ];

        $this->render('admin/dashboard.twig', ['stats' => $stats]);
    }

    // -------------------------------------------------------------------------
    // Contests list
    // -------------------------------------------------------------------------

    public function contestsList(): void
    {
        requireAdmin();

        $pdo = getDB();
        $contests = $pdo->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM contest_groups cg  WHERE cg.contest_id  = c.id) AS group_count,
                    (SELECT COUNT(*) FROM countries co        WHERE co.contest_id  = c.id) AS country_count,
                    (SELECT COUNT(*) FROM entries e           WHERE e.contest_id   = c.id) AS entry_count
               FROM contests c
              ORDER BY c.id DESC'
        )->fetchAll();

        $this->render('admin/contests.twig', ['contests' => $contests]);
    }

    public function newContest(): void
    {
        requireAdmin();

        $pdo  = getDB();
        $year = (int) date('Y');
        $name = 'Fantasy Eurovision Song Contest ' . $year;

        $pdo->prepare('INSERT INTO contests (year, name, budget_limit, status, is_active) VALUES (?, ?, 50.00, \'setup\', 0)')
            ->execute([$year, $name]);

        $newId = (int) $pdo->lastInsertId();
        $this->flash('success', 'New contest created. Update the settings below.');
        $this->redirect('/admin/contests/' . $newId);
    }

    public function duplicateContest(): void
    {
        requireAdmin();

        $sourceId = (int) ($_POST['source_contest_id'] ?? 0);
        if (!$sourceId) {
            $this->flash('error', 'Invalid contest selected for duplication.');
            $this->redirect('/admin/contests');
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT * FROM contests WHERE id = ?');
        $stmt->execute([$sourceId]);
        $source = $stmt->fetch();
        if (!$source) {
            $this->flash('error', 'Source contest not found.');
            $this->redirect('/admin/contests');
            return;
        }

        $newYear = (int) $source['year'] + 1;
        $newName = 'Fantasy Eurovision Song Contest ' . $newYear;

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO contests (year, name, budget_limit, status, is_active) VALUES (?, ?, ?, \'setup\', 0)'
            )->execute([$newYear, $newName, $source['budget_limit']]);
            $newContestId = (int) $pdo->lastInsertId();

            // Copy groups
            $groups = $pdo->prepare(
                'SELECT * FROM contest_groups WHERE contest_id = ? ORDER BY sort_order, id'
            );
            $groups->execute([$sourceId]);

            $groupIdMap    = [];
            $insertGroup   = $pdo->prepare(
                'INSERT INTO contest_groups (contest_id, group_id, name, colour, is_wildcard, sort_order) VALUES (?,?,?,?,?,?)'
            );
            foreach ($groups->fetchAll() as $g) {
                $insertGroup->execute([
                    $newContestId, $g['group_id'], $g['name'], $g['colour'], $g['is_wildcard'], $g['sort_order'],
                ]);
                $groupIdMap[(int) $g['id']] = (int) $pdo->lastInsertId();
            }

            // Copy countries (reset scores)
            $countries = $pdo->prepare('SELECT * FROM countries WHERE contest_id = ?');
            $countries->execute([$sourceId]);

            $insertCountry = $pdo->prepare(
                'INSERT INTO countries (contest_id, catalogue_id, group_id, name, flag_image, price, running_order)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($countries->fetchAll() as $c) {
                $newGroupId = $groupIdMap[(int) $c['group_id']] ?? null;
                if (!$newGroupId) continue;
                $insertCountry->execute([
                    $newContestId, $c['catalogue_id'], $newGroupId,
                    $c['name'], $c['flag_image'], $c['price'], $c['running_order'],
                ]);
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            $this->flash('error', 'Duplication failed. Please try again.');
            $this->redirect('/admin/contests');
            return;
        }

        $this->flash('success', 'Contest duplicated. Review and update the settings below.');
        $this->redirect('/admin/contests/' . $newContestId);
    }

    // -------------------------------------------------------------------------
    // Contest detail page
    // -------------------------------------------------------------------------

    public function contestDetail(array $params): void
    {
        requireAdmin();

        $contestId = (int) ($params['id'] ?? 0);
        $pdo       = getDB();

        $stmt = $pdo->prepare('SELECT * FROM contests WHERE id = ?');
        $stmt->execute([$contestId]);
        $contest = $stmt->fetch();
        if (!$contest) { $this->redirect('/admin/contests'); return; }

        $groups = $this->fetchContestGroups($pdo, $contestId);

        $countriesByGroup = $this->fetchCountriesByGroup($pdo, $contestId);

        $allGroupCatalogue   = $pdo->query('SELECT * FROM group_catalogue ORDER BY name')->fetchAll();
        $allCountryCatalogue = $pdo->query('SELECT * FROM country_catalogue ORDER BY name')->fetchAll();

        $this->render('admin/contest-detail.twig', [
            'contest'               => $contest,
            'groups'                => $groups,
            'countries_by_group'    => $countriesByGroup,
            'all_group_catalogue'   => $allGroupCatalogue,
            'all_country_catalogue' => $allCountryCatalogue,
            'valid_statuses'        => ['setup', 'open', 'closed', 'finished'],
        ]);
    }

    public function contestSave(array $params): void
    {
        requireAdmin();

        $contestId = (int) ($params['id'] ?? 0);
        $pdo       = getDB();

        $stmt = $pdo->prepare('SELECT * FROM contests WHERE id = ?');
        $stmt->execute([$contestId]);
        $contest = $stmt->fetch();
        if (!$contest) { $this->redirect('/admin/contests'); return; }

        $action = $_POST['_action'] ?? '';

        switch ($action) {
            case 'save_settings':
                $this->doSaveSettings($pdo, $contest);
                if (!empty($_POST['_redirect'])) {
                    $this->redirect('/admin/contests');
                }
                break;
            case 'delete_contest': $this->doDeleteContest($pdo, $contestId); return;
            case 'add_group':
                $newGroupId = $this->doAddGroup($pdo, $contestId);
                $suffix = $newGroupId ? '?new_group_id=' . $newGroupId : '';
                $this->redirect('/admin/contests/' . $contestId . $suffix);
                return;
            case 'edit_group':     $this->doEditGroup($pdo, $contestId);   break;
            case 'delete_group':   $this->doDeleteGroup($pdo, $contestId); break;
            case 'add_country':    $this->doAddCountry($pdo, $contestId);  break;
            case 'edit_country':   $this->doEditCountry($pdo, $contestId); break;
            case 'delete_country': $this->doDeleteCountry($pdo, $contestId); break;
            case 'save_scores':    $this->doSaveScores($pdo, $contest);    break;
        }

        $this->redirect('/admin/contests/' . $contestId);
    }

    // -------------------------------------------------------------------------
    // Contest detail — sub-actions
    // -------------------------------------------------------------------------

    private function doSaveSettings(\PDO $pdo, array $contest): void
    {
        $validStatuses = ['setup', 'open', 'closed', 'finished'];

        $year       = (int)   ($_POST['year']         ?? 0);
        $name       = trim(    $_POST['name']          ?? '');
        $budget     = (float)  ($_POST['budget_limit'] ?? 0);
        $status     = trim(    $_POST['status']        ?? '');
        $isActive   = isset($_POST['is_active']) ? 1 : 0;
        $launchDate = trim($_POST['launch_date'] ?? '') ?: null;
        $launchTime = trim($_POST['launch_time'] ?? '') ?: null;

        $errors = [];
        if ($year < 2000 || $year > 2100)             $errors[] = 'Please enter a valid year (2000–2100).';
        if (!$name)                                    $errors[] = 'Contest name is required.';
        if ($budget <= 0)                              $errors[] = 'Budget must be greater than zero.';
        if (!in_array($status, $validStatuses, true))  $errors[] = 'Invalid status.';
        if (!$launchDate)                              $errors[] = 'Launch date is required.';
        if (!$launchTime)                              $errors[] = 'Launch time is required.';

        if (!empty($errors)) {
            $this->flash('error', implode(' ', $errors));
            return;
        }

        // If activating this contest, deactivate all others first
        if ($isActive) {
            $pdo->prepare('UPDATE contests SET is_active = 0 WHERE id != ?')->execute([(int) $contest['id']]);
        }

        $prevStatus = $contest['status'];

        $pdo->prepare(
            'UPDATE contests SET year=?, name=?, budget_limit=?, status=?, is_active=?, launch_date=?, launch_time=? WHERE id=?'
        )->execute([$year, $name, $budget, $status, $isActive, $launchDate, $launchTime, (int) $contest['id']]);

        // Update inline country fields (catalogue, price, running order)
        if (!empty($_POST['country_price']) && is_array($_POST['country_price'])) {
            foreach ($_POST['country_price'] as $rawId => $rawPrice) {
                $countryId   = (int) $rawId;
                $price       = round((float) $rawPrice, 1);
                $order       = ($_POST['running_order'][$rawId] ?? '') !== '' ? (int) $_POST['running_order'][$rawId] : null;
                $catalogueId = (int) ($_POST['country_catalogue_id'][$rawId] ?? 0) ?: null;

                if ($price <= 0) continue;

                if ($catalogueId) {
                    $catStmt = $pdo->prepare('SELECT name, flag_image FROM country_catalogue WHERE id = ?');
                    $catStmt->execute([$catalogueId]);
                    if ($cat = $catStmt->fetch()) {
                        $pdo->prepare(
                            'UPDATE countries SET catalogue_id=?, name=?, flag_image=?, price=?, running_order=? WHERE id=? AND contest_id=?'
                        )->execute([$catalogueId, $cat['name'], $cat['flag_image'], $price, $order, $countryId, (int) $contest['id']]);
                        continue;
                    }
                }

                $pdo->prepare('UPDATE countries SET price=?, running_order=? WHERE id=? AND contest_id=?')
                    ->execute([$price, $order, $countryId, (int) $contest['id']]);
            }
        }

        // Insert new countries from inline add rows (new_catalogue_id[groupId][] arrays)
        if (!empty($_POST['new_catalogue_id']) && is_array($_POST['new_catalogue_id'])) {
            $insertCountry = $pdo->prepare(
                'INSERT INTO countries (contest_id, catalogue_id, group_id, name, flag_image, price, running_order) VALUES (?,?,?,?,?,?,?)'
            );
            $catStmt = $pdo->prepare('SELECT name, flag_image FROM country_catalogue WHERE id = ?');

            foreach ($_POST['new_catalogue_id'] as $groupId => $entries) {
                if (!is_array($entries)) continue;
                foreach ($entries as $idx => $catalogueId) {
                    $catalogueId = (int) $catalogueId ?: null;
                    if (!$catalogueId) continue;

                    $price = round((float) ($_POST['new_price'][$groupId][$idx] ?? 0), 1);
                    if ($price <= 0) continue;

                    $order = ($_POST['new_order'][$groupId][$idx] ?? '') !== '' ? (int) $_POST['new_order'][$groupId][$idx] : null;

                    $catStmt->execute([$catalogueId]);
                    if ($cat = $catStmt->fetch()) {
                        $insertCountry->execute([(int) $contest['id'], $catalogueId, (int) $groupId, $cat['name'], $cat['flag_image'], $price, $order]);
                    }
                }
            }
        }

        // Update scores (admin can always edit scores unless contest is finished)
        $scoresUpdated = false;
        if ($status !== 'finished' && !empty($_POST['scores']) && is_array($_POST['scores'])) {
            $updateScore = $pdo->prepare('UPDATE countries SET final_score_raw=? WHERE id=? AND contest_id=?');
            foreach ($_POST['scores'] as $rawId => $rawScore) {
                $score = ($rawScore === '' || $rawScore === null) ? null : (int) $rawScore;
                $updateScore->execute([$score, (int) $rawId, (int) $contest['id']]);
            }
            $scoresUpdated = true;
        }

        // Trigger score recalculation when moving to finished, or when scores were updated
        if ($status === 'finished' && $prevStatus !== 'finished') {
            recalculateAllEntries((int) $contest['id']);
        } elseif ($scoresUpdated) {
            recalculateAllEntries((int) $contest['id']);
        }

        $this->flash('success', 'Contest saved.');
    }

    private function doAddGroup(\PDO $pdo, int $contestId): int
    {
        $catalogueId = (int) ($_POST['group_catalogue_id'] ?? 0) ?: null;
        $name        = trim($_POST['group_name']  ?? '');
        $colour      = trim($_POST['group_colour'] ?? '#6366f1');
        $isWildcard  = isset($_POST['is_wildcard']) ? 1 : 0;
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);

        // If a catalogue entry was chosen, pull name/colour from it
        if ($catalogueId) {
            $row = $pdo->prepare('SELECT * FROM group_catalogue WHERE id = ?');
            $row->execute([$catalogueId]);
            if ($cat = $row->fetch()) {
                if (!$name)   $name   = $cat['name'];
                if ($colour === '#6366f1') $colour = $cat['colour'];
            }
        }

        if (!$name) {
            $this->flash('error', 'Group name is required.');
            return 0;
        }

        if ($isWildcard) {
            $wc = $pdo->prepare('SELECT id FROM contest_groups WHERE contest_id=? AND is_wildcard=1');
            $wc->execute([$contestId]);
            if ($wc->fetch()) {
                $this->flash('error', 'A wildcard group already exists for this contest.');
                return 0;
            }
        }

        $pdo->prepare(
            'INSERT INTO contest_groups (contest_id, group_id, name, colour, is_wildcard, sort_order) VALUES (?,?,?,?,?,?)'
        )->execute([$contestId, $catalogueId, $name, $colour, $isWildcard, $sortOrder]);

        $this->flash('success', 'Group added.');
        return (int) $pdo->lastInsertId();
    }

    private function doEditGroup(\PDO $pdo, int $contestId): void
    {
        $groupId    = (int) ($_POST['group_id']    ?? 0);
        $name       = trim($_POST['group_name']    ?? '');
        $colour     = trim($_POST['group_colour']  ?? '#6366f1');
        $isWildcard = isset($_POST['is_wildcard'])  ? 1 : 0;
        $sortOrder  = (int) ($_POST['sort_order']  ?? 0);

        if (!$name) {
            $this->flash('error', 'Group name is required.');
            return;
        }

        if ($isWildcard) {
            $wc = $pdo->prepare('SELECT id FROM contest_groups WHERE contest_id=? AND is_wildcard=1 AND id!=?');
            $wc->execute([$contestId, $groupId]);
            if ($wc->fetch()) {
                $this->flash('error', 'A wildcard group already exists for this contest.');
                return;
            }
        }

        $pdo->prepare(
            'UPDATE contest_groups SET name=?, colour=?, is_wildcard=?, sort_order=? WHERE id=? AND contest_id=?'
        )->execute([$name, $colour, $isWildcard, $sortOrder, $groupId, $contestId]);

        $this->flash('success', 'Group updated.');
    }

    private function doDeleteGroup(\PDO $pdo, int $contestId): void
    {
        $groupId = (int) ($_POST['group_id'] ?? 0);

        $check = $pdo->prepare('SELECT COUNT(*) FROM countries WHERE group_id = ?');
        $check->execute([$groupId]);
        if ((int) $check->fetchColumn() > 0) {
            $this->flash('error', 'Cannot delete a group that has countries assigned to it.');
            return;
        }

        $pdo->prepare('DELETE FROM contest_groups WHERE id=? AND contest_id=?')->execute([$groupId, $contestId]);
        $this->flash('success', 'Group deleted.');
    }

    private function doAddCountry(\PDO $pdo, int $contestId): void
    {
        $catalogueId = (int) ($_POST['country_catalogue_id'] ?? 0) ?: null;
        $groupId     = (int) ($_POST['country_group_id']     ?? 0);
        $price       = (float) ($_POST['country_price']      ?? 0);
        $runOrder    = ($_POST['running_order'] ?? '') !== '' ? (int) $_POST['running_order'] : null;

        if (!$groupId || $price <= 0) {
            $this->flash('error', 'Group and price are required.');
            return;
        }

        // Verify the group belongs to this contest
        $gc = $pdo->prepare('SELECT id FROM contest_groups WHERE id=? AND contest_id=?');
        $gc->execute([$groupId, $contestId]);
        if (!$gc->fetch()) {
            $this->flash('error', 'Invalid group selected.');
            return;
        }

        // Get name and flag image from catalogue if provided
        $name      = trim($_POST['country_name'] ?? '');
        $flagImage = null;

        if ($catalogueId) {
            $row = $pdo->prepare('SELECT * FROM country_catalogue WHERE id = ?');
            $row->execute([$catalogueId]);
            if ($cat = $row->fetch()) {
                if (!$name) $name = $cat['name'];
                $flagImage = $cat['flag_image'] ?? null;
            }
        }

        if (!$name) {
            $this->flash('error', 'Country name is required.');
            return;
        }

        $pdo->prepare(
            'INSERT INTO countries (contest_id, catalogue_id, group_id, name, flag_image, price, running_order)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$contestId, $catalogueId, $groupId, $name, $flagImage, $price, $runOrder]);

        $this->flash('success', 'Country added.');
    }

    private function doEditCountry(\PDO $pdo, int $contestId): void
    {
        $countryId = (int) ($_POST['country_id']    ?? 0);
        $price     = (float) ($_POST['country_price'] ?? 0);
        $runOrder  = ($_POST['running_order'] ?? '') !== '' ? (int) $_POST['running_order'] : null;

        if ($price <= 0) {
            $this->flash('error', 'Price must be greater than zero.');
            return;
        }

        $pdo->prepare(
            'UPDATE countries SET price=?, running_order=? WHERE id=? AND contest_id=?'
        )->execute([$price, $runOrder, $countryId, $contestId]);

        $this->flash('success', 'Country updated.');
    }

    private function doDeleteCountry(\PDO $pdo, int $contestId): void
    {
        $countryId = (int) ($_POST['country_id'] ?? 0);

        $check = $pdo->prepare('SELECT COUNT(*) FROM entry_countries WHERE country_id=?');
        $check->execute([$countryId]);
        if ((int) $check->fetchColumn() > 0) {
            $this->flash('error', 'Cannot delete a country that is part of a submitted entry.');
            return;
        }

        $pdo->prepare('DELETE FROM countries WHERE id=? AND contest_id=?')->execute([$countryId, $contestId]);
        $this->flash('success', 'Country deleted.');
    }

    private function doDeleteContest(\PDO $pdo, int $contestId): void
    {
        $pdo->prepare('DELETE FROM contests WHERE id = ?')->execute([$contestId]);
        $this->flash('success', 'Contest deleted.');
        $this->redirect('/admin/contests');
    }

    private function doSaveScores(\PDO $pdo, array $contest): void
    {
        if ($contest['status'] === 'finished') {
            $this->flash('error', 'Scores cannot be edited once the contest is finished.');
            return;
        }

        $stmt = $pdo->prepare('UPDATE countries SET final_score_raw=? WHERE id=? AND contest_id=?');
        foreach ($_POST['scores'] ?? [] as $rawId => $rawScore) {
            $score = ($rawScore === '' || $rawScore === null) ? null : (int) $rawScore;
            $stmt->execute([$score, (int) $rawId, (int) $contest['id']]);
        }

        recalculateAllEntries((int) $contest['id']);
        $this->flash('success', 'Scores saved and entries recalculated.');
    }

    // -------------------------------------------------------------------------
    // Master country catalogue
    // -------------------------------------------------------------------------

    public function countriesCatalogue(): void
    {
        requireAdmin();

        $pdo       = getDB();
        $countries = $pdo->query('SELECT * FROM country_catalogue ORDER BY name')->fetchAll();
        $editEntry = null;

        if ($editId = (int) ($_GET['edit'] ?? 0)) {
            foreach ($countries as $c) {
                if ((int) $c['id'] === $editId) { $editEntry = $c; break; }
            }
        }

        $this->render('admin/countries.twig', [
            'countries'  => $countries,
            'edit_entry' => $editEntry,
        ]);
    }

    public function countriesCatalogueSave(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $action  = $_POST['action']   ?? '';
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $name    = trim($_POST['name'] ?? '');

        if (in_array($action, ['create', 'edit'], true) && !$name) {
            $this->flash('error', 'Country name is required.');
            $this->redirect('/admin/countries');
            return;
        }

        $flagImage = $this->handleFlagUpload($name);
        if ($flagImage === false) {
            $this->redirect('/admin/countries');
            return;
        }

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO country_catalogue (name, flag_image) VALUES (?,?)')
                ->execute([$name, $flagImage]);
            $this->flash('success', 'Country added to catalogue.');
        } elseif ($action === 'edit') {
            if ($flagImage === null) {
                // No new file uploaded — keep existing image
                $flagImage = $pdo->prepare('SELECT flag_image FROM country_catalogue WHERE id = ?');
                $flagImage->execute([$entryId]);
                $flagImage = $flagImage->fetchColumn() ?: null;
            }
            $pdo->prepare('UPDATE country_catalogue SET name=?, flag_image=? WHERE id=?')
                ->execute([$name, $flagImage, $entryId]);
            $this->flash('success', 'Country updated.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM country_catalogue WHERE id=?')->execute([$entryId]);
            $this->flash('success', 'Country removed from catalogue.');
        }

        $this->redirect('/admin/countries');
    }

    private function handleFlagUpload(string $countryName): string|null|false
    {
        if (empty($_FILES['flag_image']['name'])) {
            return null;
        }

        $file = $_FILES['flag_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'File upload failed (error code ' . $file['error'] . ').');
            return false;
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        $typeMap   = [IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

        if (!$imageInfo || !isset($typeMap[$imageInfo[2]])) {
            $this->flash('error', 'Invalid file type. Please upload a PNG, GIF, JPEG, or WebP image.');
            return false;
        }

        $ext      = $typeMap[$imageInfo[2]];
        $slug     = preg_replace('/[^a-z0-9]+/', '-', strtolower($countryName));
        $slug     = trim($slug, '-');
        $filename = $slug . '.' . $ext;
        $dest     = dirname(__DIR__, 2) . '/public/uploads/flags/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->flash('error', 'Failed to save uploaded file.');
            return false;
        }

        return $filename;
    }

    // -------------------------------------------------------------------------
    // Master group catalogue
    // -------------------------------------------------------------------------

    public function groupsCatalogue(): void
    {
        requireAdmin();

        $pdo    = getDB();
        $groups = $pdo->query('SELECT * FROM group_catalogue ORDER BY name')->fetchAll();
        $editEntry = null;

        if ($editId = (int) ($_GET['edit'] ?? 0)) {
            foreach ($groups as $g) {
                if ((int) $g['id'] === $editId) { $editEntry = $g; break; }
            }
        }

        $this->render('admin/groups.twig', [
            'groups'     => $groups,
            'edit_entry' => $editEntry,
        ]);
    }

    public function groupsCatalogueSave(): void
    {
        requireAdmin();

        $pdo     = getDB();
        $action  = $_POST['action']   ?? '';
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        $name    = trim($_POST['name']        ?? '');
        $colour  = trim($_POST['colour']      ?? '#6366f1');

        if (in_array($action, ['create', 'edit'], true) && !$name) {
            $this->flash('error', 'Group name is required.');
            $this->redirect('/admin/groups');
            return;
        }

        if ($action === 'create') {
            $pdo->prepare('INSERT INTO group_catalogue (name, colour) VALUES (?,?)')
                ->execute([$name, $colour]);
            $this->flash('success', 'Group added to catalogue.');
        } elseif ($action === 'edit') {
            $pdo->prepare('UPDATE group_catalogue SET name=?, colour=? WHERE id=?')
                ->execute([$name, $colour, $entryId]);
            $this->flash('success', 'Group updated.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM group_catalogue WHERE id=?')->execute([$entryId]);
            $this->flash('success', 'Group removed from catalogue.');
        }

        $this->redirect('/admin/groups');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fetchContestGroups(\PDO $pdo, int $contestId): array
    {
        $stmt = $pdo->prepare(
            'SELECT cg.* FROM contest_groups cg
              WHERE cg.contest_id = ?
              ORDER BY cg.sort_order, cg.id'
        );
        $stmt->execute([$contestId]);
        return $stmt->fetchAll();
    }

    private function fetchCountriesByGroup(\PDO $pdo, int $contestId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM countries WHERE contest_id = ? ORDER BY price DESC, name'
        );
        $stmt->execute([$contestId]);
        $byGroup = [];
        foreach ($stmt->fetchAll() as $c) {
            $byGroup[$c['group_id']][] = $c;
        }
        return $byGroup;
    }
}
