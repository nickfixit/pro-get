<?
/**
 * Query abstraction class
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

class QueryException extends Exception {  }

class Query {
    private $source = "";
    private $columns = array("*");
    public $restrictions = array();
    private $offset = null;
    private $length = null;
    private $order  = array();

    public function __construct($source=null, $columns=array("*")) {
        $this->source  = $source;
        $this->columns = $columns;
    }

    /**
     * An alternative for the constructor, so we can use the fluent interface
     */
    public static function N($source=null, $columns=array("*")) {
        return new Query($source, $columns);
    }

    public function columns($columns) {
        $columns = func_get_args();
        if (count($columns) == 1 && is_array($columns[0])) $columns = $columns[1];
        $this->columns = $columns;
        return $this;
    }

    public function restrict_fields($fields, $values) {
        if (!is_array($fields)) $fields = array($fields);
        if (!is_array($values)) $values = array($values);

        if (count($fields) != count($values)) throw new Exception(sprintf("restrict_fields: %d fields (%s) but %d values (%s)", count($fields), implode(", ", $fields), count($values), implode(", ", $values)));

        $i = 0;
        while ($i < count($fields)) {
            $this->restrict(self::equals(array($fields[$i] => $values[$i])));
            $i++;
        }

        return $this;
    }

    public function restrict($restr) {
        if (is_array($restr)) {
            foreach ($restr as $r) $this->restrict($r);
            return $this;
        }

        $a = func_get_args();
        $a = array_map(array("Query", "value"), array_slice($a, 1));
        $this->restrictions[] = call_user_func_array("sprintf", array_merge(array("($restr)"), $a));

        return $this;
    }

    public function limit($offset, $length=null) {
        if (is_null($length)) {
            /* Actually setting length instead of offset */
            $length = $offset;
            $offset = 0;
        }

        $ofs0 = is_null($this->offset) ? 0 : $this->offset;
        $len0 = is_null($this->length) ? 0x7FFFFFFF : $this->length;

        $ofs1 = $ofs0 + $offset;
        $len1 = $length;

        $this->offset = $ofs0 + $offset; /* Offset inside the bigger window */
        $this->length = min($ofs1 + $len1, $ofs0 + $len0) - $ofs1;

        return $this;
    }

    public function order($column, $dir="") {
        $this->order[] = sprintf("%s %s", self::field($column), $dir);
        return $this;
    }

    public function copy() {
        $q = new Query($this->source, $this->columns);
        $q->restrictions = $this->restrictions;
        $q->offset       = $this->offset;
        $q->length       = $this->length;
        $q->order        = $this->order;
        return $q;
    }

    public function count() {
        $c = $this->copy()->columns(new SqlExpr("COUNT(" . $this->columns_string() . ")"))->q0();
        if (is_null($this->length)) return $c;
        return min($c, $this->length);
    }

    public function columns_string() {
        return implode(", ", array_map(array("Query", "field"), $this->columns));
    }

    public function __toString() {
        $p = array(sprintf("SELECT %s FROM %s", $this->columns_string(), $this->source));
        if (count($this->restrictions)) $p[] = "WHERE " . implode(" AND ", $this->restrictions);
        if (count($this->order)) $p[] = "ORDER BY " . implode(", ", $this->order);

        if (!is_null($this->length) && !is_null($this->offset)) $p[] = sprintf("LIMIT %d, %d", $this->offset, $this->length);
        elseif (!is_null($this->length)) $p[] = sprintf("LIMIT %d", $this->length);

        return implode(" ", $p);
    }

    public function q() {
        if (!($r = mysql_query((string)$this))) throw new QueryException(mysql_error() . "(" . $this . ")");
        return $r;
    }

    public function q0() {
        $col0 = mysql_fetch_row($this->q());
        return $col0[0];
    }

    public function q1() {
        return mysql_fetch_assoc($this->copy()->limit(1)->q());
    }

    public function copy_extra_from($query) {
        $this->restrictions = array_merge($this->restrictions, $query->restrictions);
        $this->order        = $query->order;
        $this->offset       = $query->offset;
        $this->length       = $query->length;
        if ($query->source) $this->source = $query->source;
    }

    public function rows() {
        $r = array();
        $rs = $this->q();
        while ($row = mysql_fetch_assoc($rs))
            $r[] = $row;
        return $r;
    }

    public static function equals($assoc, $assign=false) {
        if (!is_array($assoc)) {
            $a = func_get_args();
            $assoc = array($a[0] => $a[1]);
            if (count($a) >= 3) $assign = $a[2]; else $assign = false;
        }

        $r = array();
        foreach ($assoc as $field=>$value) 
            if (is_null($value) && !$assign)
                $r[] = sprintf("%s IS NULL", self::field($field));
            else
                $r[] = sprintf("%s = %s", self::field($field), self::value($value));

        if ($assign) return implode(", ", $r);
        return implode(" AND ", $r);
    }

    public static function insert_q($table, $fields) {
        return sprintf("INSERT INTO `%s`(%s) VALUES(%s)", 
            $table,
            implode(", ", array_map(array("Query", "field"), array_keys($fields))),
            implode(", ", array_map(array("Query", "value"), array_values($fields))));
    }

    public static function update_q($table, $fields, $where) {
        if (!is_array($where)) $where = array($where);
        return sprintf("UPDATE `%s` SET %s WHERE %s",
            $table,
            self::equals($fields, true),
            count($where) ? implode(" AND ", $where) : "1");
    }

    public static function insert_update_q($table, $fields) {
        return self::insert_q($table, $fields) . " ON DUPLICATE KEY UPDATE " . self::equals($fields, true);
    }

    public static function insert_update($table, $fields) {
        $q = self::insert_update_q($table, $fields);
        $r = mysql_query($q);
        if (!$r) throw new Exception(mysql_error() . " ($q)");
        return $r;
    }

    public static function insert($table, $fields) {
        $q =  self::insert_q($table, $fields);
        $r = mysql_query($q);
        if (!$r) throw new Exception(mysql_error() . " ($q)");
        return $r;
    }

    public static function update($table, $fields, $where) {
        $q = self::update_q($table, $fields, $where);
        $r = mysql_query($q);
        if (!$r) throw new Exception(mysql_error() . " ($q)");
        return $r;
    }

    public static function field($fld) {
        if ($fld instanceof SqlExpr) return (string)$fld;
        $parts = explode(".", $fld);
        $r = array();
        foreach ($parts as $part) $r[] = $part == "*" ? $part : "`$part`";
        return implode(".", $r);
    }

    public static function value($val) {
        if ($val instanceof SqlExpr) return (string)$val;
        if (is_null($val)) return "NULL";
        return "'" . mysql_real_escape_string($val) . "'";
    }

    public static function date_time($timestamp) {
        return date("Y-m-d H:i:s");
    }
}

class SqlExpr {
    private $sql;

    public function __construct($sql) {
        $a = func_get_args();
        $a = array_map(array("Query", "value"), array_slice($a, 1));
        $this->sql = call_user_func_array("sprintf", array_merge(array($sql), $a));
    }

    public function __toString() {
        return $this->sql;
    }
}

?>
