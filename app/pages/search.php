<?php
/*
 * Search: one SQL query per kind of thing (LIKE), at most a few results each.
 * A plain GET form: the results page can be bookmarked and works everywhere.
 */

function search_show(): void
{
    require_member();
    $q = get_str('q');
    $groups = [];
    if (preg_match_all('/./us', $q) >= 2) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $a = aid();
        $find = fn (string $sql, array $extra = []) => rows($sql, [$a, ...array_fill(0, substr_count($sql, 'LIKE ?'), $like), ...$extra]);
        $groups = [
            'projects' => $find("SELECT id, title, NULL AS sub FROM projects WHERE association_id = ? AND title LIKE ? ESCAPE '\\' LIMIT 8"),
            'tasks' => $find("SELECT t.id, t.title, p.title AS sub FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE t.association_id = ? AND (t.title LIKE ? ESCAPE '\\' OR t.description LIKE ? ESCAPE '\\') ORDER BY t.status = 'DONE' LIMIT 10"),
            'requests' => $find("SELECT r.id, r.title, p.name AS sub FROM requests r JOIN poles p ON p.id = r.pole_id WHERE r.association_id = ? AND r.title LIKE ? ESCAPE '\\' LIMIT 8"),
            'members' => $find("SELECT u.id, u.name AS title, m.title AS sub FROM members m JOIN users u ON u.id = m.user_id WHERE m.association_id = ? AND (u.name LIKE ? ESCAPE '\\' OR u.email LIKE ? ESCAPE '\\' OR m.title LIKE ? ESCAPE '\\' OR u.skills LIKE ? ESCAPE '\\') LIMIT 8"),
            'poles' => $find("SELECT id, name AS title, NULL AS sub FROM poles WHERE association_id = ? AND name LIKE ? ESCAPE '\\' LIMIT 5"),
            'events' => $find("SELECT id, title, start_at AS sub FROM events WHERE association_id = ? AND title LIKE ? ESCAPE '\\' ORDER BY start_at DESC LIMIT 8"),
            'documents' => $find("SELECT id, title, NULL AS sub FROM documents WHERE association_id = ? AND (title LIKE ? ESCAPE '\\' OR description LIKE ? ESCAPE '\\') LIMIT 8"),
        ];
    }
    render('search/show', ['q' => $q, 'groups' => array_filter($groups)], t('common.search'));
}
