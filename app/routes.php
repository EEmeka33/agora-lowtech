<?php
/*
 * Every address of Agora, in one place.
 *
 *   'METHOD /path' => 'file_function'
 *
 * The handler `tasks_show` is the function tasks_show() in app/pages/tasks.php.
 * {id} matches a number, {token} a random token. GET shows a page; POST
 * receives a form, then redirects. Nothing else: no API, no JavaScript.
 */
return [
    // Accounts
    'GET /login' => 'auth_login',
    'POST /login' => 'auth_login_submit',
    'POST /logout' => 'auth_logout',
    'GET /signup' => 'auth_signup',
    'POST /signup' => 'auth_signup_submit',
    'GET /forgot' => 'auth_forgot',
    'POST /forgot' => 'auth_forgot_submit',
    'GET /invite/{token}' => 'auth_invite',
    'POST /invite/{token}' => 'auth_invite_submit',
    'GET /join' => 'auth_join',
    'POST /locale' => 'auth_locale',

    // Create or join an association
    'GET /welcome' => 'welcome_show',
    'POST /welcome/new' => 'welcome_create',
    'POST /welcome/join' => 'welcome_join',
    'POST /switch' => 'welcome_switch',

    // Overview
    'GET /' => 'dashboard_show',
    'POST /announcements' => 'dashboard_announce',
    'POST /announcements/{id}/pin' => 'dashboard_pin',
    'POST /announcements/{id}/delete' => 'dashboard_unannounce',
    'GET /activity' => 'activity_list',
    'GET /search' => 'search_show',
    'GET /about' => 'about_show',
    'GET /health' => 'about_health',

    // Founding checklist
    'GET /foundation' => 'foundation_list',
    'POST /foundation' => 'foundation_create',
    'POST /foundation/template' => 'foundation_template',
    'GET /foundation/{id}' => 'foundation_edit',
    'POST /foundation/{id}' => 'foundation_update',
    'POST /foundation/{id}/status' => 'foundation_status',
    'POST /foundation/{id}/delete' => 'foundation_delete',

    // Structure and poles
    'GET /structure' => 'structure_show',
    'POST /responsibilities' => 'structure_create',
    'GET /responsibilities/{id}' => 'structure_edit',
    'POST /responsibilities/{id}' => 'structure_update',
    'POST /responsibilities/{id}/delete' => 'structure_delete',
    'GET /poles' => 'poles_list',
    'POST /poles' => 'poles_create',
    'GET /poles/{id}' => 'poles_show',
    'POST /poles/{id}' => 'poles_update',
    'POST /poles/{id}/delete' => 'poles_delete',
    'POST /poles/{id}/members' => 'poles_add_member',
    'POST /poles/{id}/members/{id2}/role' => 'poles_member_role',
    'POST /poles/{id}/members/{id2}/remove' => 'poles_remove_member',

    // Requests between poles
    'GET /requests' => 'requests_list',
    'POST /requests' => 'requests_create',
    'GET /requests/{id}' => 'requests_show',
    'POST /requests/{id}/take' => 'requests_take',
    'POST /requests/{id}/assign' => 'requests_assign',
    'POST /requests/{id}/release' => 'requests_release',
    'POST /requests/{id}/cancel' => 'requests_cancel',

    // Projects
    'GET /projects' => 'projects_list',
    'POST /projects' => 'projects_create',
    'GET /projects/{id}' => 'projects_show',
    'POST /projects/{id}' => 'projects_update',
    'POST /projects/{id}/delete' => 'projects_delete',
    'POST /projects/{id}/milestones' => 'projects_add_milestone',
    'POST /projects/{id}/milestones/{id2}/toggle' => 'projects_toggle_milestone',
    'POST /projects/{id}/milestones/{id2}/delete' => 'projects_delete_milestone',
    'POST /projects/{id}/updates' => 'projects_post_update',
    'POST /projects/{id}/team' => 'projects_add_member',
    'POST /projects/{id}/team/{id2}/remove' => 'projects_remove_member',

    // Tasks
    'GET /tasks' => 'tasks_list',
    'POST /tasks' => 'tasks_create',
    'GET /tasks/{id}' => 'tasks_show',
    'POST /tasks/{id}' => 'tasks_update',
    'POST /tasks/{id}/status' => 'tasks_status',
    'POST /tasks/{id}/delete' => 'tasks_delete',
    'POST /tasks/{id}/comments' => 'tasks_comment',
    'POST /tasks/{id}/comments/{id2}/delete' => 'tasks_uncomment',

    // Events and meetings
    'GET /events' => 'events_list',
    'POST /events' => 'events_create',
    'GET /events/{id}' => 'events_show',
    'POST /events/{id}' => 'events_update',
    'POST /events/{id}/delete' => 'events_delete',
    'POST /events/{id}/rsvp' => 'events_rsvp',
    'POST /events/{id}/minutes' => 'events_minutes',
    'GET /calendar/{token}.ics' => 'events_feed',

    // Votes
    'GET /polls' => 'polls_list',
    'POST /polls' => 'polls_create',
    'POST /polls/{id}/vote' => 'polls_vote',
    'POST /polls/{id}/close' => 'polls_close',
    'POST /polls/{id}/delete' => 'polls_delete',

    // Volunteering
    'GET /volunteering' => 'volunteering_list',
    'POST /volunteering' => 'volunteering_create',
    'POST /volunteering/{id}/delete' => 'volunteering_delete',
    'GET /volunteering.csv' => 'volunteering_csv',

    // Finance
    'GET /finance' => 'finance_show',
    'POST /finance' => 'finance_create',
    'GET /finance/{id}' => 'finance_edit',
    'POST /finance/{id}' => 'finance_update',
    'POST /finance/{id}/delete' => 'finance_delete',
    'POST /dues' => 'finance_dues',
    'POST /dues/{id}/delete' => 'finance_undues',
    'GET /finance.csv' => 'finance_csv',

    // Documents
    'GET /documents' => 'documents_list',
    'POST /documents' => 'documents_create',
    'GET /documents/{id}' => 'documents_edit',
    'POST /documents/{id}' => 'documents_update',
    'POST /documents/{id}/delete' => 'documents_delete',
    'GET /files/{id}' => 'documents_file',

    // Members and invitations
    'GET /members' => 'members_list',
    'POST /members' => 'members_create',
    'GET /members.csv' => 'members_csv',
    'GET /members/codes' => 'members_codes',
    'POST /members/codes' => 'members_code_create',
    'POST /members/codes/{id}/revoke' => 'members_code_revoke',
    'GET /members/{id}' => 'members_show',
    'POST /members/{id}' => 'members_update',
    'POST /members/{id}/link' => 'members_link',
    'POST /members/{id}/status' => 'members_status',
    'POST /members/{id}/delete' => 'members_delete',

    // Notifications
    'GET /notifications' => 'notifications_list',
    'GET /notifications/{id}' => 'notifications_open',
    'POST /notifications/read' => 'notifications_read_all',

    // Settings
    'GET /settings' => 'settings_profile',
    'POST /settings' => 'settings_profile_submit',
    'GET /settings/account' => 'settings_account',
    'POST /settings/password' => 'settings_password',
    'POST /settings/sessions/{id}/revoke' => 'settings_revoke',
    'POST /settings/sessions/revoke' => 'settings_revoke_others',
    'POST /settings/leave' => 'settings_leave',
    'POST /settings/delete' => 'settings_delete',
    'GET /settings/notifications' => 'settings_notifications',
    'POST /settings/notifications' => 'settings_notifications_submit',
    'POST /settings/calendar' => 'settings_calendar',
    'GET /settings/association' => 'settings_association',
    'POST /settings/association' => 'settings_association_submit',
    'GET /settings/backup' => 'settings_backup',
];
