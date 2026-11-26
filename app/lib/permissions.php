<?php
/*
 * Who can do what. Pages use these functions to show or hide buttons, and the
 * form handlers check them again with ensure(): hiding a button is never the
 * protection.
 *
 *   Administrator  everything, including settings, roles, members and backups
 *   Manager        content: poles, structure, founding steps, projects, events,
 *                  finance, announcements, join codes for members
 *   Member         requests, tasks, votes, volunteering, RSVP, documents, and
 *                  what they created or are assigned to
 */

function is_admin(?array $u): bool
{
    return ($u['role'] ?? '') === 'ADMIN';
}

function is_manager(?array $u): bool
{
    return in_array($u['role'] ?? '', ['ADMIN', 'MANAGER'], true);
}

/** Stops with "403 Forbidden" when the check is false. */
function ensure(bool $allowed): void
{
    if (!$allowed) {
        abort(403);
    }
}

function can(string $what, array $u, array $x = []): bool
{
    $id = (int) ($u['id'] ?? 0);
    $is = fn (?string $field) => $field !== null && isset($x[$field]) && (int) $x[$field] === $id;
    return match ($what) {
        'manageAssociation', 'manageMembers', 'backup' => is_admin($u),
        'managePoles', 'manageStructure', 'manageFoundation', 'manageFinance', 'manageEvents',
        'manageAnnouncements', 'manageCodes', 'createProject', 'exportMembers' => is_manager($u),
        // Everyone can open a vote; its creator and managers can close or delete it.
        'editPoll' => is_manager($u) || $is('created_by_id'),
        // Managers and the requester can cancel a request; managers and pole leads can give it to someone.
        'cancelRequest' => is_manager($u) || $is('requester_id'),
        'assignRequest' => is_manager($u) || in_array($id, $x['pole_lead_ids'] ?? [], true),
        'deleteTimeEntry' => is_manager($u) || $is('user_id'),
        // Managers, the project lead, or a lead of the project's pole.
        'editProject' => is_manager($u) || $is('lead_id') || in_array($id, $x['pole_lead_ids'] ?? [], true),
        // Managers, the creator, the assignee or the project lead.
        'editTask' => is_manager($u) || $is('creator_id') || $is('assignee_id') || $is('project_lead_id'),
        'deleteTask' => is_manager($u) || $is('creator_id') || $is('project_lead_id'),
        'editDocument' => is_manager($u) || $is('uploaded_by_id'),
        'editPole' => is_manager($u) || in_array($id, $x['pole_lead_ids'] ?? [], true),
        // Minutes: managers, secretaries and the president.
        'writeMinutes' => is_manager($u) || in_array($u['board_position'] ?? '', ['SECRETARY', 'DEPUTY_SECRETARY', 'PRESIDENT'], true),
        'editStep' => is_manager($u) || $is('assignee_id'),
        default => throw new LogicException("Unknown permission $what"),
    };
}
