#LoadModule usertrack_module modules/mod_usertrack.so
LoadModule version_module modules/mod_version.so
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
#LoadModule vhost_alias_module modules/mod_vhost_alias.so

<IfModule mime_module>
    AddType application/x-httpd-php .php
    AddType application/x-httpd-php-source .phps
</IfModule>