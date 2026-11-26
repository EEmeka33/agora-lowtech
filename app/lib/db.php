<?php
/*
 * Database: one SQLite file, opened with PDO (built into PHP).
 *
 * Queries are written in plain SQL with "?" placeholders, never by gluing
 * user input into strings. Everything that belongs to an association is read
 * with `association_id = ?` (see find() and aid()), so one association never
 * sees another's data.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $file = (string) config('database');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    $isNew = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');   // readers don't wait for writers
    $pdo->exec('PRAGMA synchronous = NORMAL');
    if ($isNew) {
        @chmod($file, 0600); // personal data: readable by the web server's user only
    }
    migrate($pdo);
    return $pdo;
}

/**
 * Brings the database schema up to date. PRAGMA user_version stores the number
 * of the last migration applied: 1 = app/schema.sql, then app/migrations/002_*.sql…
 */
function migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    $files = [1 => APP . '/schema.sql'];
    foreach (glob(APP . '/migrations/*.sql') ?: [] as $file) {
        $files[(int) basename($file)] = $file;
    }
    ksort($files);
    foreach ($files as $number => $file) {
        if ($number <= $version) {
            continue;
        }
        $pdo->beginTransaction();
        $pdo->exec(file_get_contents($file));
        $pdo->exec('PRAGMA user_version = ' . (int) $number);
        $pdo->commit();
    }
}

/** Runs a query and returns the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    return $stmt;
}

/** All rows. */
function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** First row, or null. */
function row(string $sql, array $params = []): ?array
{
    return q($sql, $params)->fetch() ?: null;
}

/** First column of the first row, or null. */
function val(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** First column of every row. */
function col(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Inserts a row and returns its id. Table and column names always come from the
 * code, never from a form.
 */
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $cols),
        implode(', ', array_fill(0, count($cols), '?'))
    );
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

/** Updates rows: update('tasks', ['status' => 'DONE'], 'id = ? AND association_id = ?', [$id, aid()]). */
function update(string $table, array $data, string $where, array $params): int
{
    $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($data)));
    return q("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$params])->rowCount();
}

/** Runs $fn inside a transaction (all or nothing). */
function transaction(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** "?, ?, ?" for an IN (…) list. */
function placeholders(array $values): string
{
    return implode(', ', array_fill(0, max(1, count($values)), '?'));
}

// ─── Inside the current association ────────────────────────────────────────

/** Id of the current association (see context.php). */
function aid(): int
{
    $assoc = current_association();
    if (!$assoc) {
        throw new LogicException('No current association');
    }
    return (int) $assoc['id'];
}

/** Tables that have an association_id column. */
const TENANT_TABLES = [
    'members', 'join_codes', 'poles', 'responsibilities', 'foundation_steps', 'projects', 'tasks',
    'requests', 'events', 'announcements', 'polls', 'time_entries', 'transactions', 'dues',
    'documents', 'activities',
];

/** A row of the current association, or null (also null for another association's row). */
function find(string $table, int|string|null $id): ?array
{
    if (!in_array($table, TENANT_TABLES, true)) {
        throw new LogicException("$table has no association_id");
    }
    if (!$id || !ctype_digit((string) $id)) {
        return null;
    }
    return row("SELECT * FROM $table WHERE id = ? AND association_id = ?", [(int) $id, aid()]);
}

/** Same as find(), but answers "404 Not found" when the row doesn't exist. */
function find_or_404(string $table, int|string|null $id): array
{
    return find($table, $id) ?? abort(404);
}

/**
 * Checks that ids coming from a form belong to the current association
 * (e.g. the project of a new task). Answers "403 Forbidden" otherwise.
 */
function assert_refs(array $refs): void
{
    foreach (['pole_id' => 'poles', 'project_id' => 'projects', 'task_id' => 'tasks', 'event_id' => 'events'] as $key => $table) {
        if (!empty($refs[$key]) && !find($table, $refs[$key])) {
            abort(403);
        }
    }
    $userIds = array_unique(array_filter($refs['user_ids'] ?? []));
    if ($userIds) {
        $n = (int) val(
            'SELECT COUNT(*) FROM members WHERE association_id = ? AND user_id IN (' . placeholders($userIds) . ')',
            [aid(), ...$userIds]
        );
        if ($n !== count($userIds)) {
            abort(403);
        }
    }
}

// ─── Lists used in forms ───────────────────────────────────────────────────

/** Active members of the association: [id => name]. */
function member_options(): array
{
    $list = [];
    foreach (rows("SELECT u.id, u.name FROM members m JOIN users u ON u.id = m.user_id
                   WHERE m.association_id = ? AND m.status = 'ACTIVE' ORDER BY u.name", [aid()]) as $r) {
        $list[$r['id']] = $r['name'];
    }
    return $list;
}

/** Poles: [id => name]. */
function pole_options(): array
{
    $list = [];
    foreach (rows('SELECT id, name FROM poles WHERE association_id = ? ORDER BY position, name', [aid()]) as $r) {
        $list[$r['id']] = $r['name'];
    }
    return $list;
}

/** Projects (only open ones unless $all): [id => title]. */
function project_options(bool $all = false): array
{
    $sql = 'SELECT id, title FROM projects WHERE association_id = ?'
        . ($all ? '' : " AND status NOT IN ('COMPLETED', 'CANCELLED')") . ' ORDER BY title';
    $list = [];
    foreach (rows($sql, [aid()]) as $r) {
        $list[$r['id']] = $r['title'];
    }
    return $list;
}

/** Ids of the leads of a pole. */
function pole_lead_ids(?int $poleId): array
{
    if (!$poleId) {
        return [];
    }
    return array_map('intval', col("SELECT user_id FROM pole_members WHERE pole_id = ? AND role = 'LEAD'", [$poleId]));
}

/** Ids of the poles a person belongs to (in the current association). */
function my_pole_ids(int $userId): array
{
    return array_map('intval', col(
        'SELECT pm.pole_id FROM pole_members pm JOIN poles p ON p.id = pm.pole_id
         WHERE pm.user_id = ? AND p.association_id = ?',
        [$userId, aid()]
    ));
}

/** Ids of the active members of a pole. */
function pole_member_ids(int $poleId): array
{
    return array_map('intval', col(
        "SELECT pm.user_id FROM pole_members pm
         JOIN poles p ON p.id = pm.pole_id
         JOIN members m ON m.user_id = pm.user_id AND m.association_id = p.association_id
         WHERE pm.pole_id = ? AND m.status = 'ACTIVE'",
        [$poleId]
    ));
}

/** Ids of the active members of the association (optionally only some roles). */
function association_member_ids(int $associationId, array $roles = []): array
{
    $sql = "SELECT user_id FROM members WHERE association_id = ? AND status = 'ACTIVE'";
    $params = [$associationId];
    if ($roles) {
        $sql .= ' AND role IN (' . placeholders($roles) . ')';
        array_push($params, ...$roles);
    }
    return array_map('intval', col($sql, $params));
}

/** Records something visible in the activity feed. Never makes an action fail. */
function log_activity(string $action, string $entityType, ?int $entityId = null, ?string $label = null, ?int $actorId = null, ?int $associationId = null): void
{
    try {
        insert('activities', [
            'association_id' => $associationId ?? aid(),
            'actor_id' => $actorId ?? (current_user()['id'] ?? null),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $label === null ? null : str_cut($label, 200),
            'created_at' => now_utc(),
        ]);
    } catch (Throwable $e) {
        error_log('Activity not logged: ' . $e->getMessage());
    }
}
