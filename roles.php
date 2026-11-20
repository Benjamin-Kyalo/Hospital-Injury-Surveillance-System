<?php
// Compatibility only: role management now lives on the Users & roles page.
// Old bookmarks and links to roles.php land on the Roles tab, with no duplicate UI.
require_once __DIR__ . '/includes/common.php';
redirect('users.php?tab=roles');
