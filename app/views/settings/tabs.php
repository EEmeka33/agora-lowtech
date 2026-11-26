<?php
$items = [
    'profile' => [t('settings.tabs.profile'), url('/settings')],
    'account' => [t('settings.tabs.security'), url('/settings/account')],
    'notifications' => [t('settings.tabs.notifications'), url('/settings/notifications')],
];
if (current_association() && is_admin(current_user())) {
    $items['association'] = [t('settings.tabs.association'), url('/settings/association')];
    $items['backup'] = [t('settings.tabs.data'), url('/settings/backup')];
}
?>
<?= page_head(t('settings.title'), t('settings.subtitle')) ?>
<?= tabs_html($items, $tab) ?>
