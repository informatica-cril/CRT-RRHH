<?php

/**
 * Router per al servidor de desenvolupament (php -S 127.0.0.1:8000 server.php).
 *
 * El de sèrie de Laravel fa `return false` per als fitxers estàtics, que només
 * funciona si el docroot és `public/`. Aquí el servidor s'arrenca des de l'arrel
 * del projecte, així que el `return false` feia que /build/assets/*.js no es
 * trobés mai i el frontend Inertia no arrenqués. Aquest router serveix ell mateix
 * els fitxers de `public/` amb el MIME correcte i passa la resta a Laravel.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && $uri !== '') {
    $fitxer = realpath(__DIR__ . '/public' . $uri);
    if ($fitxer !== false && str_starts_with($fitxer, realpath(__DIR__ . '/public')) && is_file($fitxer)) {
        $ext = strtolower(pathinfo($fitxer, PATHINFO_EXTENSION));
        $mimes = [
            'js' => 'application/javascript', 'mjs' => 'application/javascript',
            'css' => 'text/css', 'json' => 'application/json',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'gif' => 'image/gif',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            'map' => 'application/json', 'webp' => 'image/webp', 'txt' => 'text/plain',
        ];
        if ($ext !== 'php') {
            header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
            header('Content-Length: ' . filesize($fitxer));
            readfile($fitxer);
            exit;
        }
    }
}

require_once __DIR__ . '/public/index.php';
