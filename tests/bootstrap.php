<?php

// BD de tests en un fitxer sqlite (no en memòria): RefreshDatabase hi fa
// `migrate:fresh` un sol cop per execució i després embolcalla cada test en una
// transacció que es desfà. En memòria, les ~130 migracions es tornaven a executar
// abans de CADA test (~1 s per test).
//
// Camí absolut perquè no depengui del directori des d'on es llança phpunit, i el
// fitxer s'ha de crear abans: el connector sqlite de Laravel no el crea.
$bd = dirname(__DIR__).'/database/testing.sqlite';
if (! file_exists($bd)) {
    touch($bd);
}
putenv("DB_DATABASE={$bd}");
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $bd;

require dirname(__DIR__).'/vendor/autoload.php';
