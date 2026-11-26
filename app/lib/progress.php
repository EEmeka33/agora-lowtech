<?php
/*
 * Project progress and health, and progress of the founding checklist.
 */

/**
 * Project progress in percent.
 *   MANUAL  the value set by the project lead;
 *   AUTO    done tasks + done milestones over the total (a milestone weighs two tasks).
 * $tasks and $milestones are lists of rows with 'status' / 'done'.
 */
function project_progress(array $project, array $tasks, array $milestones): int
{
    if ($project['status'] === 'COMPLETED') {
        return 100;
    }
    if ($project['progress_mode'] === 'MANUAL') {
        return clamp((int) $project['manual_progress'], 0, 100);
    }
    $doneTasks = count(array_filter($tasks, fn ($t) => $t['status'] === 'DONE'));
    $doneMilestones = count(array_filter($milestones, fn ($m) => (int) $m['done'] === 1));
    $total = count($tasks) + count($milestones) * 2;
    return $total ? (int) round(($doneTasks + $doneMilestones * 2) / $total * 100) : 0;
}

/**
 * Health compares the elapsed share of the schedule with the progress made:
 *   past due and not completed       → OFF_TRACK
 *   more than 25 points behind plan  → AT_RISK
 */
function project_health(array $project, int $progress, ?string $now = null): ?string
{
    $now ??= now_wall();
    if (in_array($project['status'], ['COMPLETED', 'CANCELLED', 'IDEA'], true)) {
        return null;
    }
    $start = $project['start_date'] ? strtotime($project['start_date'] . ' UTC') : null;
    $due = $project['due_date'] ? strtotime($project['due_date'] . ' 23:59 UTC') : null;
    $nowTs = strtotime($now . ' UTC');
    if (!$due) {
        return $project['status'] === 'ON_HOLD' ? 'AT_RISK' : 'ON_TRACK';
    }
    if ($nowTs > $due) {
        return 'OFF_TRACK';
    }
    if ($project['status'] === 'ON_HOLD') {
        return 'AT_RISK';
    }
    if (!$start || $due <= $start || $nowTs < $start) {
        return 'ON_TRACK';
    }
    $elapsed = ($nowTs - $start) / ($due - $start) * 100;
    return $elapsed - $progress > 25 ? 'AT_RISK' : 'ON_TRACK';
}

/** Share of founding steps done (skipped steps don't count). */
function foundation_progress(array $steps): array
{
    $relevant = array_filter($steps, fn ($s) => $s['status'] !== 'SKIPPED');
    $done = count(array_filter($relevant, fn ($s) => $s['status'] === 'DONE'));
    return ['done' => $done, 'total' => count($relevant), 'percent' => pct($done, count($relevant))];
}

/** Loads progress and health of several projects with two queries (not one per project). */
function with_progress(array $projects): array
{
    if (!$projects) {
        return [];
    }
    $ids = array_column($projects, 'id');
    $tasks = [];
    foreach (rows('SELECT project_id, status FROM tasks WHERE project_id IN (' . placeholders($ids) . ')', $ids) as $t) {
        $tasks[$t['project_id']][] = $t;
    }
    $milestones = [];
    foreach (rows('SELECT project_id, done FROM milestones WHERE project_id IN (' . placeholders($ids) . ')', $ids) as $m) {
        $milestones[$m['project_id']][] = $m;
    }
    foreach ($projects as &$p) {
        $p['tasks_total'] = count($tasks[$p['id']] ?? []);
        $p['tasks_done'] = count(array_filter($tasks[$p['id']] ?? [], fn ($t) => $t['status'] === 'DONE'));
        $p['progress'] = project_progress($p, $tasks[$p['id']] ?? [], $milestones[$p['id']] ?? []);
        $p['health'] = project_health($p, $p['progress']);
    }
    return $projects;
}
