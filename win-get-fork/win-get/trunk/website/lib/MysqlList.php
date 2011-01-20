<?
/**
 * Mysql list class for use with the scaffold (it's like a read-only table with
 * more control over the actions)
 *
 * @package scaffold
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

class MysqlList {
    private $name;
    private $query;
    private $row_formatter;
    private $total_count      = null;
    private $extra_conditions = array();

    /**
     * Scaffolding subject for a MySQL table
     *
     * After the first argument follows either a list of child tables, or the
     * name of the foreign key field if this is the constructor of such a child table.
     */
    public function __construct($name, $query, $row_formatter) {
        $this->name          = $name;
        $this->query         = $query;
        $this->row_formatter = $row_formatter;
    }

    // ------------------------ THE SCAFFOLDING CONTRACT ---------------------------

    public function hook($scaffold) {
        $scaffold->add_table($this);
    }

    public function table_name() {
        return $this->name;
    }

    public function get_range($offset, $max) {
        $rs = $this->query->copy()->limit($offset, $max)->q();

        $objs = array();
        while ($obj = mysql_fetch_object($rs)) {
            $objs[] = $obj;
        }
        return $objs;
    }

    public function total_rows() {
        if (is_null($this->total_count)) 
            $this->total_count = $this->query->count();

        return $this->total_count;
    }

    public function render($obj, $output, $mode) {
        if (is_null($obj)) die("Something's wrong here -- we can't be rendering no NULL object... I don't know nothing about that!");

        $vars = get_object_vars($obj);
        foreach ($vars as $name=>$value) {
            $output->render_field($value, $output->col($name));
        }
    }
}

?>
