<?
/**
 * Basic ORM system
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

require_once "Query.php";

class DormRecord {
    protected $_original_values = array();
    protected $_constructing = false;

    public function get_id() { return Mapper::Get($this)->get_id($this); }
    public function set_id($id) { Mapper::Get($this)->set_id($this); }

    public function has_id() {
        $id = $this->get_id();
        if (is_null($id)) return false;
        if (is_array($id) && count($id) == 1 && is_null($id[0])) return false;
        return true;
    }

    public function __get($key) {
        $getter = array($this, "get_" . $key);
        if (is_callable($getter)) return call_user_func($getter);
        if (property_exists(get_class($this), $key)) return $this->$key;
        throw new Exception(sprintf("No such field: %s.%s", get_class($this), $key));
    }

    public function __set($key, $value) {
        $setter = array($this, "set_" . $key);
        if (is_callable($setter)) return call_user_func($setter, $value);
        $this->$key = $value;
    }

    public function set_all($assoc) {
        foreach ($assoc as $key=>$value)
            $this->__set($key, $value);
    }

    public function get_constructing() {
        return $this->_constructing;
    }

    public function set_constructing($constructing) {
        $this->_constructing = $constructing;
    }

    public function get_all() {
        return Mapper::Get($this)->leech($this);
    }

    public function set_original_values($orig) {
        $this->_original_values = $orig;
    }

    public function raw_dump($o) {
        ob_start();
        var_dump($o);
        return ob_get_clean();
    }

    public function is_changed($field=null) {
        if ($field) {
            // Use array_key_exists() instead of isset() because isset returns false on NULLs
            if (!array_key_exists($field, $this->_original_values)) return true;
            return $this->_original_values[$field] != $this->__get($field);
        }

        // All fields
        $fields = Mapper::Get($this)->record_fields();
        foreach ($fields as $field) {
            if ($this->is_changed($field)) return true;
        }

        return false;
    }

    public function save() {
        return Mapper::Get($this)->save($this);
    }

    /**
     * Format the given value so it is formatted as a database datetime value
     *
     * Regardless of whether it was a datetime string or a timestamp. Also Null
     * is translated to "now".
     * 
     * Has no effect while the object is being constructed, to prevent corruption
     * of database values.
     */
    protected function date_time(&$value) {
        if ($this->constructing) return;

        if (is_null($value))     $value = time();
        if (!is_integer($value)) $value = strtotime($value);

        $value = date("Y-m-d H:i:s", $value);
    }

    public function mapper() {
        return Mapper::Get($this);
    }

    /**
     * Default equality test for database objects
     */
    public function __equals($x) {
        return (get_class($x) == get_class($this)) && ($this->get_id() == $x->get_id());
    }
}

class Mapper {
    protected $query;
    protected $record_class;
    protected $table;
    protected $primary_key;

    public function __construct($record_class, $table, $primary_key) {
        $this->record_class = $record_class;
        $this->table        = $table;
        $this->primary_key  = $primary_key;

        $this->query = new Query($table, $this->prefix($this->all_fields(), $this->table));
    }

    private static $configures = array();
    public static function Configure($class, $table, $primary_key, $mapper_class=null) {
        if (!is_array($primary_key)) $primary_key = array($primary_key);
        if (class_exists($class . "Mapper")) $mapper_class = $class . "Mapper"; else $mapper_class = "Mapper";

        self::$configures[$class] = compact("table", "primary_key", "mapper_class");
    }

    private static $instances = array();
    /**
     * Return a singleton of the mapper for the class
     */
    public static function Get($class) {
        if (is_object($class)) $class = get_class($class);
        if (!isset(self::$configures[$class])) throw new Exception("No mapper configured for class '$class'");

        if (!isset(self::$instances[$class])) {
            $c =& self::$configures[$class];
            $mapper = $c["mapper_class"];
            self::$instances[$class] = new $mapper($class, $c["table"], $c["primary_key"]);
        }
        return self::$instances[$class];
    }

    public function record_fields() {
        return array_filter($this->get_class_props($this->record_class), array($this, "no_hidden"));
    }

    private function get_class_props($klass) {
        $r = array();

        $cls = new ReflectionClass($klass);
        foreach ($cls->getProperties() as $prop)
            if (!$prop->isStatic())
                $r[] = $prop->getName();

        return $r;
    }

    public function all_fields() {
        return array_merge($this->record_fields(), $this->primkey_fields());
    }

    public function prefix($fields, $prefix) {
        $r = array();
        foreach ($fields as $field) $r[] = "$prefix.$field";
        return $r;
    }

    public function primkey_fields() {
        return $this->primary_key;
    }

    public function no_hidden($s) {
        return $s{0} != "_";
    }

    public function fill($object, $assoc) {
        foreach ($this->record_fields() as $fld) 
            $object->$fld = $assoc[$fld];

        $object->set_original_values($assoc);
    }

    public function create($assoc) {
        if (!$assoc) return null;

        $k = $this->record_class;
        $object = new $k();
        $object->constructing = true;
        $this->fill($object, $assoc);
        $this->set_id($object, $this->pick($this->primkey_fields(), $assoc));
        $object->constructing = false;
        return $object;
    }

    public function leech($object) {
        $assoc = array();
        foreach ($this->record_fields() as $fld)
            $assoc[$fld] = $object->$fld;
        return $assoc;
    }

    public function load($primary_key_values) {
        /* If this is already an instance of the object, return it immediately. This allows you to write code that accepts both an object and an id */
        if ($primary_key_values instanceof $this->record_class) return $primary_key_values;

        $q = $this->query->copy();
        if ($primary_key_values instanceof Query) {
            // It's already a query
            $q->copy_extra_from($primary_key_values);
        }
        else {
            if (!is_array($primary_key_values)) $primary_key_values = func_get_args();
            $q->restrict_fields($this->primkey_fields(), $primary_key_values);
        }
        
        return $this->create( $q->q1() );
    }

    /**
     * Reload the contents of an object from the database
     */
    public function reload($object) {
        $id = $this->get_id($object);

        $q = $this->query->copy();
        $q->restrict_fields($this->primkey_fields(), $id);

        $this->fill($object, $q->q1());
    }

    public function load_all($restrictions=array()) {
        $q = $this->query->copy();
        if ($restrictions instanceof Query)
            $q->copy_extra_from($restrictions);
        else
            $q->restrict($restrictions);

        $ret = array();
        foreach ($q->rows() as $assoc) $ret[] = $this->create($assoc);
        return $ret;
    }

    protected function zip($a1, $a2) {
        if (!is_array($a1)) $a1 = array($a1);
        if (!is_array($a2)) $a2 = array($a2);

        $r = array();
        $i = 0;
        while ($i < count($a1) && $i < count($a2)) {
            $r[$a1[$i]] = $a2[$i];
            $i++;
        }
        return $r;
    }

    protected function id_clause($pk_values) {
        if (!is_array($pk_values)) $pk_values = func_get_args();

        return Query::equals($this->zip($this->primkey_fields(), $pk_values));
    }

    public function pick($fields, $assoc) {
        $r = array();
        if (is_array($assoc))
            foreach ($fields as $field) $r[] = $assoc[$field];
        else
            foreach ($fields as $field) $r[] = $assoc->$field;
        return $r;
    }

    public function get_id($object) {
        $id = $this->pick($this->primkey_fields(), $object);
        if (count($id) == 1) return $id[0];
        return $id;
    }

    public function set_id($object, $id) {
        if (!is_array($id)) $id = array($id);
        foreach ($this->zip($this->primkey_fields(), $id) as $key=>$value) $object->$key = $value;
    }

    public function save($object) {
        if (!$object->is_changed()) return false;

        $fields = $this->leech($object);

        if ($object->has_id()) {
            /* Update */
            $key = $this->zip($this->primkey_fields(), $object->id);
            Query::update($this->table, $fields, Query::equals($key));
        }
        else {
            /* Insert */
            Query::insert($this->table, $fields);

            $this->set_id($object, mysql_insert_id());
        }

        return true;
    }
}

?>
