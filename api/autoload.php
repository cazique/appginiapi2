
<?php
spl_autoload_register(function ($class_name) {
    $paths = ['core', 'classes'];
    foreach ($paths as $path) {
        $file = __DIR__ . '/' . $path . '/' . $class_name . '.php';
        if (file_exists($file)) {
            include_once $file;
            return;
        }
    }
});
