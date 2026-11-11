@echo off
REM Fix Apache httpd.conf for PHP

set CONF_FILE=C:\xampp\apache\conf\httpd.conf

REM Backup
copy %CONF_FILE% %CONF_FILE%.backup

REM Fix LoadModule line
powershell -Command "(Get-Content '%CONF_FILE%') -replace '(LoadModule version_module modules/mod_version.so)', 'LoadModule version_module modules/mod_version.so`r`nLoadModule php_module \"C:/xampp/php/php8apache2_4.dll\"' | Set-Content '%CONF_FILE%'"

REM Fix AddType lines
powershell -Command "(Get-Content '%CONF_FILE%') -replace '<IfModule mime_module>`nAddType application/x-httpd-php .php`nAddType application/x-httpd-php-source .phps', '<IfModule mime_module>' | Set-Content '%CONF_FILE%'"

echo Fixed httpd.conf
pause