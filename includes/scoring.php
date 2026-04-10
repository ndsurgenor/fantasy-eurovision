<?php

require_once __DIR__ . '/db.php';

function calculateScore(?int $finalPoints): int {
    if ($finalPoints === null) return 0;   // not yet scored
    if ($finalPoints === 0)   return -5;   // nul points penalty
    return (int) ceil($finalPoints / 10);  // divide by 10, round up
}

function recalculateAllEntries(int $contestId): void {
    $pdo = getDB();

    // Fetch all entries for this contest
    $entries = $pdo->prepare('SELECT id FROM entries WHERE contest_id = ?');
    $entries->execute([$contestId]);
    $entries = $entries->fetchAll();

    $fetchCountries = $pdo->prepare(
        'SELECT c.final_score_raw
           FROM entry_countries ec
           JOIN countries c ON c.id = ec.country_id
          WHERE ec.entry_id = ?'
    );

    $updateEntry = $pdo->prepare(
        'UPDATE entries SET total_score = ? WHERE id = ?'
    );

    foreach ($entries as $entry) {
        $fetchCountries->execute([$entry['id']]);
        $countries = $fetchCountries->fetchAll();

        $totalScore = 0;
        foreach ($countries as $country) {
            $totalScore += calculateScore(
                $country['final_score_raw'] !== null
                    ? (int) $country['final_score_raw']
                    : null
            );
        }

        $updateEntry->execute([$totalScore, $entry['id']]);
    }
}
