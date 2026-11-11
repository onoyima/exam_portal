$conf = Get-Content "C:\xampp\apache\conf\httpd.conf" -Raw

# Normalize line endings to LF
$conf = $conf -replace '`r`n', "`n"

# Fix LoadModule - remove duplicate and fix newlines
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so\nLoadModule php_module "C:/xampp/php/php8apache2_4.dll"', 'LoadModule version_module modules/mod_version.so'
$conf = $conf -replace 'LoadModule php_module "C:/xampp/php/php8apache2_4.dll"', ''

# Remove duplicate AddType lines
$conf = $conf -replace '\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', ''
$conf = $conf -replace '<IfModule mime_module>\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'
$conf = $conf -replace '\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', ''
$conf = $conf -replace '<IfModule mime_module>\nAddType application/x-httpd-php \.php\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'

# Ensure single AddType lines
$conf = $conf -replace '<IfModule mime_module>', "<IfModule mime_module>\n    AddType application/x-httpd-php .php`n    AddType application/x-httpd-php-source .phps"

# Fix LoadModule - remove duplicate and add correct one
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so\nLoadModule php_module "C:/xampp/php/php8apache2_4.dll"', 'LoadModule version_module modules/mod_version.so'
$conf = $conf -replace 'LoadModule php_module "C:/xampp/php/php8apache2_4.dll"', ''

# Add correct LoadModule line
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so', "LoadModule version_module modules/mod_version.so`nLoadModule php_module `\"C:/xampp/php/php8apache2_4.dll`""

# Ensure single AddType lines
$conf = $conf -replace '<IfModule mime_module>', "<IfModule mime_module>\n    AddType application/x-httpd-php .php`n    AddType application/x-httpd-php-source .phps"

# Remove any duplicate AddType lines that might remain
$conf = $conf -replace '(\n\s*AddType application/x-httpd-php \.php\n\s*AddType application/x-httpd-php-source \.phps)+', "`$0"

Set-Content "C:\xampp\apache\conf\httpd.conf" $conf
Write-Host "Fixed httpd.conf"