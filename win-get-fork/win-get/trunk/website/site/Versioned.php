<?

class Versioned extends DormRecord {
    protected $r_rev;
    protected $r_rev_by;
    protected $r_rev_at;
    protected $r_note;
    protected $r_alive;
}

class VersionedMapper extends Mapper {
    protected $id_field;
    protected $seq_table;

    public function __construct($record_class, $table, $primary_key) {
        $this->id_field  = $primary_key[0];
        $this->seq_table = "{$table}_seq";

        parent::__construct($record_class, $table, $primary_key);
    }

    public function get_new_id() {
        Query::insert($this->seq_table, array());
        return mysql_insert_id();
    }

    public function get_new_revision($id) {
        return Query::N($this->table, array(new SqlExpr("IFNULL(MAX(r_rev), 0) + 1")))->restrict("{$this->id_field} = %s", $id)->q0();
    }

    /**
     * A save of a versioned object always results in a new record
     */
    public function save($object) {
        if (!$object->is_changed()) return false;
        if (!$object->__get($this->id_field)) $object->__set($this->id_field, $this->get_new_id());

        $fields = $this->leech($object);

        $fields["r_rev"]    = $this->get_new_revision($object->__get($this->id_field));
        $fields["r_rev_by"] = $_SERVER["REMOTE_ADDR"];
        $fields["r_rev_at"] = Query::date_time(time());
        $fields["r_note"]   = "Edited.";
        $fields["r_alive"]  = 1;

        Query::insert($this->table, $fields);

        return true;
    }
}

?>
