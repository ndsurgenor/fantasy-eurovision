<?php

/**
 * Count how many picks belong to a given group.
 *
 * @param array $picks      Array of country rows, each with a 'group_id' key.
 * @param int   $groupId
 */
function countPicksInGroup(array $picks, int $groupId): int {
    return count(array_filter($picks, fn($p) => (int) $p['group_id'] === $groupId));
}

/**
 * Validate a player's team selection.
 *
 * @param array $picks   Array of selected country rows (each with 'group_id' and 'price').
 * @param array $groups  Array of group rows (each with 'id', 'name', 'is_wildcard').
 * @param float $budget  Maximum allowed spend (e.g. 50.00).
 *
 * @return string[]  List of validation error messages (empty = valid).
 */
function validateEntry(array $picks, array $groups, float $budget): array {
    $errors = [];

    if (count($picks) !== 8) {
        $errors[] = 'You must select exactly 8 countries.';
    }

    foreach ($groups as $group) {
        $groupPicks = countPicksInGroup($picks, (int) $group['id']);
        $max = $group['is_wildcard'] ? 1 : 2;
        $min = $group['is_wildcard'] ? 0 : 1;

        if ($groupPicks < $min) {
            $errors[] = 'Select at least 1 from ' . $group['name'] . '.';
        }
        if ($groupPicks > $max) {
            $errors[] = 'Max ' . $max . ' from ' . $group['name'] . '.';
        }
    }

    $totalCost = (float) array_sum(array_column($picks, 'price'));
    if ($totalCost > $budget) {
        $errors[] = 'Team cost exceeds budget of €' . number_format($budget, 2) . 'm.';
    }

    return $errors;
}
