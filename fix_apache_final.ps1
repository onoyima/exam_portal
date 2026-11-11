$conf = Get-Content "C:\xampp\apache\conf\httpd.conf" -Raw

# Fix the LoadModule lines
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so`r`nLoadModule php_module "C:/xampp/php/php8apache2_4.dll"', 'LoadModule version_module modules/mod_version.so'
$conf = $conf -replace 'LoadModule php_module "C:/xampp/php/php8apache2_4.dll"', ''

# Fix the AddType lines
$conf = $conf -replace '\r\nAddType application/x-httpd-php .php\r\nAddType application/x-httpd-php-source .phps', ''
$conf = $conf -replace '<IfModule mime_module>\r\nAddType application/x-httpd-php .php\r\nAddType application/x-httpd-php-source .phps', '<IfModule mime_module>'
$conf = $conf -replace '\r\nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source \.phps', ''
$conf = $conf -replace '<IfModule mime_module>\r\nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'
$conf = $conf -replace '<IfModule mime_module>`nAddType application/x-httpd-php \.php\r\nAddType application/x-httpd-php-source \.phps', '<IfModule mime_module>'

# Add correct PHP module loading
$conf = $conf -replace 'LoadModule version_module modules/mod_version.so', 'LoadModule version_module modules/mod_version.so`r`nLoadModule php_module "C:/xampp/php/php8apache2_4.dll"'

# Add correct AddType lines
$conf = $conf -replace '<IfModule mime_module>', "<IfModule mime_module>`r`n    AddType application/x-httpd-php .php`r`n    AddType application/x-httpd-php-source .phps"

Set-Content "C:\xampp\apache\conf\httpd.conf" $conf
Write-Host "Fixed httpd.conf"