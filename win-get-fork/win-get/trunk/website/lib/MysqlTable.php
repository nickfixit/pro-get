<?
/**
 * Mysql table class for use with the scaffolding class
 *
 * @package scaffold
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

class MysqlTable {
    private $table;
    private $cache;
    private $columns      = array();
    private $analyzed     = false;
    private $prim_key     = "";
    private $total_count  = null;
    private $fk_field     = null;
    private $fk_value;
    private $child_tables = array();
    private $extra_conditions = array();

    /**
     * Scaffolding subject for a MySQL table
     *
     * After the first argument follows either a list of child tables, or the
     * name of the foreign key field if this is the constructor of such a child table.
     */
    public function __construct($table, $rest=array()) {
        $this->table = $table;
        $this->analyze_table();

        $rest = array_slice(func_get_args(), 1);
        if (count($rest) && is_string($rest[0])) { $this->fk_field = $rest[0]; $rest = array_slice($rest, 1); }
        $this->child_tables = $rest;
    }

    // ------------------------ THE SCAFFOLDING CONTRACT ---------------------------

    public function hook($scaffold) {
        $scaffold->add_table($this);
    }

    public function table_name() {
        return $this->table;
    }

    public function get_range($offset, $max) {
        $rs = $this->q("SELECT * FROM `%s` WHERE %s LIMIT %d, %d", 
            $this->table, 
            $this->get_extra_conditions(),
            $offset, $max);

        $ids = array();
        while ($obj = mysql_fetch_object($rs)) {
            $this->add_to_cache($obj);
            $ids[] = $this->get_id($obj);
        }
        return $ids;
    }

    public function total_rows() {
        if (is_null($this->total_count)) 
            $this->total_count = $this->q0("SELECT COUNT(*) FROM `%s` WHERE %s", 
                $this->table,
                $this->get_extra_conditions()
                );

        return $this->total_count;
    }

    public function render($id, $output, $mode) {
        if (!is_null($id))
            $vars = get_object_vars($this->get($id));

        foreach ($this->columns as $col)
            if ($col->name != $this->prim_key && $col->name != $this->fk_field) {
                $value = !is_null($id) ? $vars[$col->name] : "";

                if ($mode == "list" && strlen($value) > 60) $value = substr($value, 0, 57) . "...";

                $output->render_field($value, $col);
            }
    }

    public function save($id, $fields) {
        $values = $this->get_db_values($fields);

        if (is_null($id)) {
            $this->insert($values);
            return $this->last_insert_id();
        }
        else {
            $this->update($id, $values);
            return $id;
        }
    }

    public function delete($id) {
        $this->q("DELETE FROM `%s` WHERE `%s` = '%s'", 
            $this->table,
            $this->id_column(),
            mysql_real_escape_string($id));
    }

    public function get_child_tables($id) {
        foreach ($this->child_tables as $child)
            $child->parent_id($id);

        return $this->child_tables;
    }

    public function parent_id($id) {
        $this->fk_value = $id;
    }

    // ------------------------ QUERY ABSTRACTIONS ---------------------------

    private function q() {
        $args = func_get_args();
        $q = call_user_func_array("sprintf", $args);
        if (!($r = mysql_query($q))) die(mysql_error() . "($q)");
        return $r;
    }

    private function q0() {
        $args = func_get_args();
        $rs = call_user_func_array(array($this, "q"), $args);
        $row = mysql_fetch_row($rs);
        return $row[0];
    }

    public function add_extra_condition($condition) {
        $this->extra_conditions[] = $condition;
    }

    private function get_extra_conditions() {
        $conds = $this->extra_conditions;
        if (!is_null($this->fk_field)) $conds[] = sprintf("`%s` = '%s'", $this->fk_field, mysql_real_escape_string($this->fk_value));

        if (!count($conds)) return "1";
        return implode(" AND ", $conds);
    }

    /**
     * Allow to override primary key
     */
    public function set_prim_key($prim_key) {
        $this->prim_key = $prim_key;
    }

    public function get_db_values($fields) {
        $values = array();
        foreach ($this->columns as $col)
            if (isset($fields[$col->name]))
                $values[$col->name] = $fields[$col->name];

        if (!is_null($this->fk_field))
            $values[$this->fk_field] = $this->fk_value;

        return $values;
    }

    public function insert($values) {
        $this->q("INSERT INTO `%s`(%s) VALUES(%s)", 
            $this->table,
            implode(", ", array_keys($values)),
            implode(", ", array_map(array($this, "value"), array_values($values)))
            );
    }

    public function update($id, $values) {
        $this->q("UPDATE `%s` SET %s WHERE `%s` = '%s'", 
            $this->table,
            implode(", ", array_map(array($this, "update_clause"), array_keys($values), array_values($values))),
            $this->id_column(),
            mysql_real_escape_string($id)
            );
    }

    public function has_column($name) {
        foreach ($this->columns as $col)
            if ($col->name == $name)
                return true;
        return false;
    }

    public function last_insert_id() {
        return mysql_insert_id();
    }

    public function set_columns($columns) {
        $columns = func_get_args();
        if (count($columns) == 1 && is_array($columns[0])) $columns = $columns[0];

        // For a view, it's very hard to detect the columns properly, so we allow to override them
        foreach ($columns as $col) {
            $this->columns[] = (object)array(
                "name" => $col,
                "type" => "varchar",
                "size" => 255
                );
        }


        if (is_null($this->prim_key) && count($this->columns)) $this->prim_key = $this->columns[0]->name;
    }

    private function analyze_table() {
        if ($this->analyzed) return;
        $this->analyzed = true;

        $row = mysql_fetch_row( $this->q('SHOW CREATE TABLE `%s`', $this->table) );
        $create_table = $row[1];

        $this->prim_key = null;

        if (preg_match('/primary key\s*\(`([^`]+)`\)/i', $create_table, $matches)) $this->prim_key = $matches[1];
        if (preg_match_all('/^\s* `([^`]+)` \s+ (\w+) (\([^)]+\))? (?: \s+ :unsigned)? (\s+ not\snull|null)? \s* ([^,()]*)/xmi', $create_table, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $this->columns[] = (object)array(
                    "name"  => $m[1],
                    "type"  => strtolower($m[2]),
                    "size"  => preg_replace("/^\(|\)$/", "", $m[3]),
                    "null"  => strtolower(trim($m[4])) == "null",
                    "extra" => $m[5]
                    );
            }
        }

        if (is_null($this->prim_key) && count($this->columns)) $this->prim_key = $this->columns[0]->name;
    }

    private function id_column() {
        $this->analyze_table();
        return $this->prim_key;
    }

    public function get_id($obj) {
        $key = $this->prim_key;
        return $obj->$key;
    }

    public function get($id) {
        if (!isset($this->cache[$id])) {
            $rs = $this->q("SELECT * FROM `%s` WHERE `%s` = '%s' AND %s LIMIT 1", $this->table, $this->id_column(), mysql_real_escape_string($id), $this->get_extra_conditions());
            $obj = mysql_fetch_object($rs);
            if (!$obj) die("Not found: $id");
            $this->add_to_cache($obj);
        }

        return $this->cache[$id];
    }

    private function add_to_cache($obj) {
        $this->cache[$this->get_id($obj)] = $obj;
    }

    public function render_child_tables($output) {
        foreach ($this->child_tables as $child)
            $output->render_child_table($child);
    }

    private function value($v) {
        return sprintf("'%s'", mysql_real_escape_string($v));
    }

    private function update_clause($k, $v) {
        return sprintf("`%s` = '%s'", $k, mysql_real_escape_string($v));
    }

}

?>
