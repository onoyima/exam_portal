$conf = Get-Content "C:\xampp\apache\conf\httpd.conf" -Raw
$conf = $conf -replace '(LoadModule version_module modules/mod_version.so)', "`$0`LoadModule php_module `\"C:/xampp/php/php8apache2_4.dll`""
$conf = $conf -replace '(<IfModule mime_module>)', "`$0`AddType application/x-httpd-php .php`nAddType application/x-httpd-php-source .phps"
Set-Content "C:\xampp\apache\conf\httpd.conf" $conf
echo "Updated httpd.conf"