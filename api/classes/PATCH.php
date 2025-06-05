
<?php
class PATCH {
    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function handle() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['id'])) {
            $this->core->response(400, ['error' => 'ID obligatorio para modificar']);
        }

        $id = $data['id'];
        unset($data['id']);
        $fields = array_keys($data);
        $assignments = implode(', ', array_map(fn($f) => "$f = :$f", $fields));

        $sql = "UPDATE `" . $this->core->table . "` SET $assignments WHERE id = :id";
        $stmt = $this->core->db->prepare($sql);
        $data['id'] = $id;
        $stmt->execute($data);

        $this->core->response(200, ['success' => true]);
    }
}
