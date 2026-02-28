<?php
// Render the homepage by detecting template for CLI and printing output
require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../resources/php/helpers/templates.php';

// ensure DB link is available (bootstrap may not create it)
global $link;
if (!isset($link) || !($link instanceof PDO)) {
	$dbHelper = __DIR__ . '/../resources/php/helpers/database.php';
	if (file_exists($dbHelper)) {
		require_once $dbHelper;
		if (!isset($link) && isset($GLOBALS['link']) && $GLOBALS['link'] instanceof PDO) {
			$link = $GLOBALS['link'];
		}
	}
}

// simulate URL home
$result = detect_template_from_url(['home']);
// load template with detected context
ob_start();
load_template($result['template'], $result['context']);
$output = ob_get_clean();

echo $output;
