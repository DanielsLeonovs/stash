<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(data|tests)/|\.json$|\.sh$|/\.#', $path)) {
    http_response_code(404);
    exit;
}
return false;
