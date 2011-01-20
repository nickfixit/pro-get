<?
/**
 * @package scaffold
 * @author Rico Huijbers
 */

/**
 * A wrapper class for a MysqlTable in the scaffold that should be versioned.
 * 
 * Expects the following columns on the subordinate table:
 *
 * - r_rev 
 * - r_rev_by
 * - r_rev_at
 * - r_note
 * - r_alive
 * 
 * The table should have a combined primary key of (primkey, r_rev). Pass in
 * the name of the original part of the primary key (if not given, tablename +
 * 'id' is assumed).
 *
 * This class also expects a table called 'tablename_seq' with an autoincrement
 * column `seq_no` which will be used to generate primary keys for new entries
 * of the table.
 */
class VersionedTable {
    private $table;
    private $seq_table;
    private $fake_pk;

    public function __construct($table, $fake_primary_key=null) {
        $this->table = $table;

        $this->fake_pk = is_null($fake_primary_key) ? $table->table_name() . "id" : $fake_primary_key;
        $this->table->set_prim_key($this->fake_pk);
        $this->table->add_extra_condition(sprintf('r_rev = (SELECT MAX(r_rev) FROM %1$s sub WHERE %1$s.%2$s = sub.%2$s)', $table->table_name(), $this->fake_pk));
        $this->table->add_extra_condition("r_alive");

        $this->seq_table = $table->table_name() . "_seq";
    }

    // ------------------------ THE SCAFFOLDING CONTRACT ---------------------------

    public function hook($scaffold) {
        $scaffold->add_table($this); /* Hook ourselves instead of the child table */
    }

    public function table_name() {
        return $this->table->table_name();
    }

    public function get_range($offset, $max) {
        return $this->table->get_range($offset, $max);
    }

    public function total_rows() {
        return $this->table->total_rows();
    }

    public function render($id, $output, $mode) {
        $this->output = $output; /* Save output, render through this instance */
        return $this->table->render($id, $this, $mode);
    }

    public function save($id, $fields) {
        $created = false;
        if (is_null($id)) {
            $id = $this->get_new_sequence_nr(); /* Creating a new record? Then get a new sequence nr. */
            $created = true;
        }
        $rev = $this->get_new_rev_nr($id); /* Get a new rev nr */

        $db_values = $this->table->get_db_values($fields);

        $this->set_rev_info($db_values, $id, $rev);
        $db_values["r_note"] = $created ? "Created" : "Changed";
        $db_values["r_alive"] = 1;

        $this->table->insert($db_values);

        return $id;
    }

    public function delete($id) {
        $rev = $this->get_new_rev_nr($id); /* Get a new rev nr */

        $latest = get_object_vars($this->table->get($id));
        $this->set_rev_info($latest, $id, $rev);
        $latest["r_note"] = "Deleted";
        $latest["r_alive"] = 0;

        $this->table->insert($latest);
    }

    public function get_child_tables($id) {
        return $this->table->get_child_tables($id);
    }

    public function parent_id($id) {
        return $this->table->parent_id($id);
    }

    // ------------------------ PRETEND TO BE THE SCAFFOLD ---------------------------

    private $output;
    public function render_field($value, $col_info) {
        // Never render revision tracking columns
        if ($col_info->name == "r_rev" || $col_info->name == "r_rev_by" || $col_info->name == "r_rev_at" || $col_info->name == "r_note" || $col_info->name == "r_alive") return;
        $this->output->render_field($value, $col_info);
    }

    // ------------------------ 

    private function get_new_sequence_nr() {
        mysql_query(sprintf("INSERT INTO `%s`() VALUES();", $this->seq_table)) or die(mysql_error());
        return mysql_insert_id();
    }

    private function get_new_rev_nr($id) {
        $rs  = mysql_query(sprintf("SELECT IFNULL(MAX(r_rev), 0) + 1 FROM `%s` WHERE `%s` = '%s'", $this->table->table_name(), $this->fake_pk, $id));
        $row = mysql_fetch_array($rs);
        return $row[0];
    }

    public function set_rev_info(&$fields, $id, $rev) {
        $fields[$this->fake_pk] = $id;
        $fields["r_rev"]        = $rev;
        $fields["r_rev_at"]     = date("Y-m-d H:i:s");
        $fields["r_rev_by"]     = $_SERVER["REMOTE_ADDR"];
    }
}

?>
