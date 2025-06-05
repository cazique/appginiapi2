
<?php
include_once(dirname(__FILE__) . '/../api-config.php');
include_once(dirname(__FILE__) . '/../autoload.php');
include_once(dirname(__FILE__) . '/../core/Credentials.php');

class Core {
    public $db;
    public $method;
    public $table;

    public function __construct($method, $table) {
        $this->method = strtoupper($method);
        $this->table = makeSafe($table);
        $this->db = db();

        $this->authenticate();
        $this->authorize();
    }

    private function authenticate() {
        $memberInfo = getMemberInfo();

        if (!$memberInfo || !$memberInfo['username']) {
            if (!isset($_SERVER['PHP_AUTH_USER'])) {
                header('WWW-Authenticate: Basic realm="API Access"');
                header('HTTP/1.0 401 Unauthorized');
                echo json_encode(['error' => 'Autenticación requerida.']);
                exit;
            }

            $username = makeSafe($_SERVER['PHP_AUTH_USER']);
            $password = $_SERVER['PHP_AUTH_PW'];

            $stored_hash = sqlValue("SELECT passHash FROM membership_users WHERE username='{$username}' AND isApproved=1 AND isBanned=0");
            if (!$stored_hash || !password_verify($password, $stored_hash)) {
                header('HTTP/1.0 403 Forbidden');
                echo json_encode(['error' => 'Credenciales inválidas.']);
                exit;
            }
        }
    }

    private function authorize() {
        $mi = getMemberInfo();
        $perm = check_table_permission($this->table, 'view');
        if (!$perm) {
            $this->response(403, ['error' => 'Sin permisos para acceder a la tabla.']);
        }

        if (function_exists('before_api_call')) {
            $res = before_api_call($this->method, $this->table, $mi);
            if ($res !== true) $this->response(403, $res);
        }
    }

    public function response($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
