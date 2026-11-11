@echo off
REM Fix Apache httpd.conf for PHP

set CONF_FILE=C:\xampp\apache\conf\httpd.conf

REM Backup
copy %CONF_FILE% %CONF_FILE%.backup2

REM Create a clean version using PowerShell with proper escaping
powershell -Command "
$conf = Get-Content '%CONF_FILE%' -Raw
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so\r\nLoadModule php_module ""C:/xampp/php/php8apache2_4.dll""', 'LoadModule version_module modules/mod_version.so'
$conf = $conf -replace 'LoadModule php_module ""C:/xampp/php/php8apache2_4.dll""', ''
$conf = $conf -replace '`r`nAddType application/x-httpd-php .php\r\nAddType application/x-httpd-php-source .phps', ''
$conf = $conf -replace '<IfModule mime_module>`r`nAddType application/x-httpd-php .php\r\nAddType application/x-httpd-php-source .phps', '<IfModule mime_module>'
$conf = $conf -replace '`r`nAddType application/x-httpd-php .php\r\nAddType application/x-httpd-php-source .phps', ''
$conf = $conf -replace '<IfModule mime_module>`r`nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source .phps', '<IfModule mime_module>'
$conf = $conf -replace '<IfModule mime_module>`nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'
$conf = $conf -replace '<IfModule mime_module>`nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'

$conf = $conf -replace 'LoadModule version_module modules/mod_version.so', 'LoadModule version_module modules/mod_version.so`r`nLoadModule php_module ""C:/xampp/php/php8apache2_4.dll""'
$conf = $conf -replace '<IfModule mime_module>', '<IfModule mime_module>`r`n    AddType application/x-httpd-php .php`r`n    AddType application/x-httpd-php-source .phps'

Set-Content '%CONF_FILE%' $conf

echo Fixed httpd.conf
pause