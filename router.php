<?php
// Роутер для встроенного сервера PHP (start.bat): закрывает служебные папки.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(data|includes)(/|$)#', $path) || preg_match('#^/uploads/.*\.php#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
return false;
