<?php
/**
 * Standalone SEB Config Generator
 * This module generates a .seb file that forces the browser to open the Portal
 * and prevents the user from closing it without a password.
 */
require '../../db.php'; // Reach back to root db

header('Content-Type: application/seb');
header('Content-Disposition: attachment; filename="Veritas_Secure_Exam.seb"');

$settings = $db->query("SELECT * FROM seb_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$quit_pass = $settings['quit_password'] ?? 'VERITAS_EXIT_2024';

// Use default if local
$start_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace("modules/secure_gate/config_generator.php", "student_login.php", $_SERVER['REQUEST_URI']);

// Minimal SEB XML PList structure
echo '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>startURL</key>
	<string>' . htmlspecialchars($start_url) . '</string>
	<key>quitPassword</key>
	<string>' . htmlspecialchars($quit_pass) . '</string>
	<key>ignoreBrowserWindowSize</key>
	<true/>
	<key>fullScreen</key>
	<true/>
	<key>allowQuit</key>
	<true/>
	<key>browserViewMode</key>
	<integer>0</integer>
	<key>sendBrowserExamKey</key>
	<true/>
	<key>hookKeys</key>
	<true/>
	<key>enablePrintScreen</key>
	<false/>
	<key>enableF12</key>
	<false/>
	<key>mainBrowserWindowHeight</key>
	<string>100%</string>
</dict>
</plist>';
exit;
