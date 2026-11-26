<?php
/*
 * The activity feed: everything that happened in the association, 50 per page.
 */
require_once APP . '/pages/dashboard.php';

function activity_list(): void
{
    require_member();
    $page = max(1, (int) get_str('page'));
    $items = recent_activity(51, ($page - 1) * 50);
    render('activity/list', ['items' => array_slice($items, 0, 50), 'page' => $page, 'more' => count($items) > 50], t('activity.title'));
}
