$conf = Get-Content "C:\xampp\apache\conf\httpd.conf" -Raw

# First, normalize all line endings to `n
$conf = $conf -replace '`r`n', "`n"

# Fix LoadModule - remove duplicate and fix newlines
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so\nLoadModule php_module "C:/xampp/php/php8apache2_4.dll"', 'LoadModule version_module modules/mod_version.so'
$conf = $conf -replace 'LoadModule php_module "C:/xampp/php/php8apache2_4.dll"', ''

# Remove all duplicate AddType lines (keep only one set)
$conf = $conf -replace '\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', ''
$conf = $conf -replace '<IfModule mime_module>\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'
$conf = $conf -replace '<IfModule mime_module>\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'

# Remove any remaining duplicate AddType lines
$conf = $conf -replace '\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', ''

# Ensure single AddType lines
$conf = $conf -replace '<IfModule mime_module>', "<IfModule mime_module>`n    AddType application/x-httpd-php .php`n    AddType application/x-httpd-php-source .phps"

# Fix LoadModule
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so', "LoadModule version_module modules/mod_version.so`nLoadModule php_module `\"C:/xampp/php/php8apache2_4.dll`""

Set-Content "C:\xampp\apache\conf\httpd.conf" $conf
Write-Host "Fixed httpd.conf"