<?php

declare(strict_types=1);

namespace App\Controllers;

class ContestController extends BaseController
{
    public function index(): void
    {
        $contest = resolvePublicContest();

        if (!$contest) {
            if (isAdmin()) {
                $this->redirect('/admin/contests');
            } else {
                $this->render('coming-soon.twig', [
                    'message' => 'No contest has been set up yet. Check back soon!',
                ]);
            }
            return;
        }

        if (!isLoggedIn()) {
            $this->redirect('/login');
            return;
        }

        $pdo    = getDB();
        $userId = (int) $_SESSION['user_id'];

        switch ($contest['status']) {
            case 'open':
                $stmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND contest_id = ?');
                $stmt->execute([$userId, (int) $contest['id']]);
                $this->redirect($stmt->fetch() ? '/my-team' : '/select');
                break;
            case 'closed':
                $stmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND contest_id = ?');
                $stmt->execute([$userId, (int) $contest['id']]);
                if ($stmt->fetch()) {
                    $this->redirect('/my-team');
                } else {
                    $this->render('coming-soon.twig', [
                        'message' => 'The contest is now closed. Results will be available soon!',
                    ]);
                }
                break;
            case 'finished':
                $this->redirect('/leaderboard');
                break;
            default:
                // setup status — should not normally be reached since resolvePublicContest
                // only returns active contests, but guard just in case
                $this->render('coming-soon.twig', [
                    'message' => 'The contest is being set up. Check back soon!',
                ]);
        }
    }

    public function selectForm(): void
    {
        requireLogin();

        $contest = resolvePublicContest();

        if (!$contest || $contest['status'] !== 'open') {
            $this->redirect('/');
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND contest_id = ?');
        $stmt->execute([(int) $_SESSION['user_id'], (int) $contest['id']]);
        $entry = $stmt->fetch();

        $checkedIds = [];
        if ($entry) {
            $stmt = $pdo->prepare('SELECT country_id FROM entry_countries WHERE entry_id = ?');
            $stmt->execute([(int) $entry['id']]);
            $checkedIds = array_column($stmt->fetchAll(), 'country_id');
        }

        [$groups, $countriesByGroup] = $this->loadGroupsAndCountries($pdo, (int) $contest['id']);

        $this->render('contest/select.twig', [
            'groups'             => $groups,
            'countries_by_group' => $countriesByGroup,
            'checked_ids'        => $checkedIds,
            'errors'             => [],
        ]);
    }

    public function submitSelect(): void
    {
        requireLogin();

        $contest = resolvePublicContest();

        if (!$contest || $contest['status'] !== 'open') {
            $this->redirect('/');
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id FROM entries WHERE user_id = ? AND contest_id = ?');
        $stmt->execute([(int) $_SESSION['user_id'], (int) $contest['id']]);
        $existingEntry = $stmt->fetch();

        [$groups, $countriesByGroup] = $this->loadGroupsAndCountries($pdo, (int) $contest['id']);

        $checkedIds = array_map('intval', (array) ($_POST['countries'] ?? []));

        $picks = [];
        if (!empty($checkedIds)) {
            $placeholders = implode(',', array_fill(0, count($checkedIds), '?'));
            $stmt = $pdo->prepare("SELECT * FROM countries WHERE id IN ($placeholders) AND contest_id = ?");
            $stmt->execute([...$checkedIds, (int) $contest['id']]);
            $picks = $stmt->fetchAll();
        }

        $errors = validateEntry($picks, $groups, (float) $contest['budget_limit']);

        if (!empty($errors)) {
            $this->render('contest/select.twig', [
                'groups'             => $groups,
                'countries_by_group' => $countriesByGroup,
                'checked_ids'        => $checkedIds,
                'errors'             => $errors,
            ]);
            return;
        }

        $totalCost = (float) array_sum(array_column($picks, 'price'));

        $pdo->beginTransaction();
        try {
            if ($existingEntry) {
                $entryId = (int) $existingEntry['id'];
                $pdo->prepare('DELETE FROM entry_countries WHERE entry_id = ?')->execute([$entryId]);
                $pdo->prepare('UPDATE entries SET total_cost = ?, submitted_at = NOW() WHERE id = ?')
                    ->execute([$totalCost, $entryId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO entries (user_id, contest_id, submitted_at, total_cost, total_score) VALUES (?, ?, NOW(), ?, 0)'
                )->execute([(int) $_SESSION['user_id'], (int) $contest['id'], $totalCost]);
                $entryId = (int) $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare('INSERT INTO entry_countries (entry_id, country_id) VALUES (?, ?)');
            foreach ($picks as $pick) {
                $stmt->execute([$entryId, (int) $pick['id']]);
            }
            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            $this->render('contest/select.twig', [
                'groups'             => $groups,
                'countries_by_group' => $countriesByGroup,
                'checked_ids'        => $checkedIds,
                'errors'             => ['Something went wrong saving your entry. Please try again.'],
            ]);
            return;
        }

        $this->flash('success', $existingEntry ? 'Your team has been updated!' : 'Your team has been submitted!');
        $this->redirect('/my-team');
    }

    public function myTeam(): void
    {
        requireLogin();

        $contest = resolvePublicContest();

        if (!$contest) {
            $this->redirect('/');
            return;
        }

        $pdo   = getDB();
        $stmt  = $pdo->prepare('SELECT * FROM entries WHERE user_id = ? AND contest_id = ?');
        $stmt->execute([(int) $_SESSION['user_id'], (int) $contest['id']]);
        $entry = $stmt->fetch();

        if (!$entry) {
            $this->redirect($contest['status'] === 'open' ? '/select' : '/');
            return;
        }

        $stmt = $pdo->prepare(
            'SELECT c.name, c.flag_image, c.price, c.final_score_raw, c.running_order,
                    cg.name AS group_name
               FROM entry_countries ec
               JOIN countries c       ON c.id  = ec.country_id
               JOIN contest_groups cg ON cg.id = c.group_id
              WHERE ec.entry_id = ?
              ORDER BY c.running_order, c.name'
        );
        $stmt->execute([(int) $entry['id']]);
        $rawPicks = $stmt->fetchAll();

        $picks = array_map(function (array $pick): array {
            $pick['score'] = calculateScore(
                $pick['final_score_raw'] !== null ? (int) $pick['final_score_raw'] : null
            );
            return $pick;
        }, $rawPicks);

        $this->render('contest/my-team.twig', [
            'entry'       => $entry,
            'picks'       => $picks,
            'total_score' => array_sum(array_column($picks, 'score')),
            'is_open'     => $contest['status'] === 'open',
            'is_finished' => $contest['status'] === 'finished',
            'is_closed'   => $contest['status'] === 'closed',
            'launch_date' => $contest['launch_date'] ?? null,
            'launch_time' => $contest['launch_time'] ?? null,
        ]);
    }

    public function leaderboard(): void
    {
        requireLogin();

        $contest = resolvePublicContest();

        if (!$contest || !in_array($contest['status'], ['open', 'closed', 'finished'], true)) {
            $this->redirect('/');
            return;
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT u.name, u.id AS user_id, e.total_score, e.total_cost
               FROM entries e
               JOIN users u ON u.id = e.user_id
              WHERE e.contest_id = ?
              ORDER BY e.total_score DESC, e.total_cost ASC'
        );
        $stmt->execute([(int) $contest['id']]);

        $this->render('contest/leaderboard.twig', [
            'entries'         => $stmt->fetchAll(),
            'is_finished'     => $contest['status'] === 'finished',
            'current_user_id' => (int) $_SESSION['user_id'],
        ]);
    }

    private function loadGroupsAndCountries(\PDO $pdo, int $contestId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM contest_groups WHERE contest_id = ? ORDER BY sort_order');
        $stmt->execute([$contestId]);
        $groups = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT * FROM countries WHERE contest_id = ? ORDER BY price DESC, name ASC');
        $stmt->execute([$contestId]);
        $countriesByGroup = [];
        foreach ($stmt->fetchAll() as $c) {
            $countriesByGroup[$c['group_id']][] = $c;
        }

        return [$groups, $countriesByGroup];
    }
}
