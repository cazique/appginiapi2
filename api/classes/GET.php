
<?php
class GET {
    private $core;

    public function __construct($core) {
        $this->core = $core;
    }

    public function handle() {
        $sql = "SELECT * FROM `" . $this->core->table . "` LIMIT 100";
        $result = $this->core->db->query($sql);
        $rows = [];

        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = $row;
        }

        $this->core->response(200, ['data' => $rows]);
    }
}
