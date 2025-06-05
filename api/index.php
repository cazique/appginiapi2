
<?php
$method = $_SERVER['REQUEST_METHOD'];
$table = isset($_GET['table']) ? $_GET['table'] : '';

require_once(__DIR__ . '/autoload.php');

$classMap = [
    'GET' => 'GET',
    'POST' => 'POST',
    'PUT' => 'PUT',
    'DELETE' => 'DELETE'
];

if (!array_key_exists($method, $classMap)) {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$core = new Core($method, $table);
$handlerClass = $classMap[$method];
$handler = new $handlerClass($core);
$handler->handle();
