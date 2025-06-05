
<?php
class DELETE {
    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function handle() {
        parse_str(file_get_contents('php://input'), $params);
        if (!isset($params['id'])) {
            $this->core->response(400, ['error' => 'ID obligatorio para eliminar']);
        }

        $sql = "DELETE FROM `" . $this->core->table . "` WHERE id = :id";
        $stmt = $this->core->db->prepare($sql);
        $stmt->execute(['id' => $params['id']]);

        $this->core->response(200, ['success' => true]);
    }
}
