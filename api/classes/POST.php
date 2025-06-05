
<?php
class POST {
    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function handle() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $this->core->response(400, ['error' => 'Datos JSON inválidos']);
        }

        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ':' . $f, $fields);

        $sql = "INSERT INTO `" . $this->core->table . "` (" . implode(',', $fields) . ")
                VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->core->db->prepare($sql);
        $stmt->execute($data);

        $id = $this->core->db->lastInsertId();
        $this->core->response(201, ['success' => true, 'inserted_id' => $id]);
    }
}
