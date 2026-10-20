<?php
define('OMEKA_PATH', '/home/claude/omeka-s');
require '/home/claude/omeka-s/vendor/autoload.php';
spl_autoload_register(function ($c) {
    $map = ['VisualPageBuilder\\' => '/home/claude/vpb/module/VisualPageBuilder/src/', 'Omeka\\' => '/home/claude/omeka-s/application/src/'];
    foreach ($map as $prefix => $dir) {
        if (strpos($c, $prefix) === 0) {
            $f = $dir . str_replace('\\', '/', substr($c, strlen($prefix))) . '.php';
            if (is_file($f)) {
                require $f;
            }
        }
    }
});
