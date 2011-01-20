<?
/**
 * PHP scaffolding class
 *
 * @package scaffold
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

error_reporting(E_ALL);

class Scaffold {
    const CREATE = null;

    private $tables;
    private $table;
    private $mode;
    private $control;
    private $url_func;
    private $page;
    private $length;
    private $field_renderer;
    private $cells;
    private $id;
    private $child_scaffold = null;

    /**
     * Constructor
     *
     * Arguments must be objects or functions. If it is an object, the scaffold will call
     * their hook() function with the scaffold instance as argument. If it is a
     * callable, the scaffold will call the function directly with itself as argument.
     * Arrays will be flattened.
     * 
     * The objects can use this function call to modify the scaffold -- for
     * example to add a table or change the control source.
     */
    public function __construct() {
        $this->initialize();
        $this->hook_all(func_get_args());
        
        $this->select_table();
        $this->select_mode();
        $this->check();
    }

    public function hook_all($args) {
        foreach ($args as $arg) {
            if (is_object($arg)) $arg->hook($this);
            elseif (is_callable($arg)) c($arg, $this);
            elseif (is_array($arg)) $this->hook_all($arg);
            else throw new Exception("Invalid argument: $arg. Must be an object or callable.");
        }
    }

    public function initialize() {
        $this->tables   = array();
        $this->control  = $_GET;
        $this->url_func = array($this, "default_url_func");
    }

    public function add_table($table) {
        $this->tables[] = $table;
    }

    public function set_control($control) {
        $this->control = $control;
    }

    public function set_url_func($url_func) {
        $this->url_func = $url_func;
    }

    /**
     * Return whether the table object has the given function
     */
    private function has($fn) {
        return is_callable(array($this->table, $fn));
    }

    private function select_table() {
        $this->table = null;

        if (isset($this->control["table"]))
            foreach ($this->tables as $table)
                if ($table->table_name() == $this->control["table"]) $this->table = $table;

        if (is_null($this->table)) {
            if (!count($this->tables)) die("No tables given to scaffold");

            $this->table = $this->tables[0];
        }
    }

    private function select_mode() {
        $this->mode = "list";

        if (!isset($this->control["action"])) return;
        switch ($this->control["action"]) {
            case "list":
            case "modify":
            case "create":
            case "delete":
            case "view":
                $this->mode = $this->control["action"];
                break;
        }
    }

    private function select_page() {
        $this->page   = isset($this->control["page"]) ? $this->control["page"] : 1;
        $this->length = isset($this->control["length"]) ? $this->control["length"] : 25;

        if ($this->page < 1) $this->page = 1;
        while (($this->page - 1) * $this->length > $this->table->total_rows() && $this->page > 1) $this->page--;
    }

    private function select_id() {
        if (!isset($this->control["id"])) die("No id selected.");
        $this->id = $this->control["id"];
    }

    private function add_message($message) {
        if (!isset($_SESSION["message"])) $_SESSION["message"] = array();
        $_SESSION["message"][] = $message;
    }

    private function display_messages() {
        if (!isset($_SESSION["message"]) || !count($_SESSION["message"])) return;

        foreach ($_SESSION["message"] as $msg)
            printf('<div class="message">%s</div>', htmlspecialchars($msg));
        unset($_SESSION["message"]);
    }

    private function g(&$array, $key, $default) {
        if (isset($array[$key])) {
            $r = $array[$key];
            unset($array[$key]);
            return $r;
        }
        return $default;
    }

    private function make_query_parts($args, $array_name="") {
        $qstrings = array();
        foreach ($args as $name => $value) {
            $fullname = $array_name ? "{$array_name}[{$name}]" : $name;

            if (is_array($value))
                $qstrings = array_merge($qstrings, $this->make_query_parts($value, $fullname));
            else
                $qstrings[] = sprintf("%s=%s", urlencode($fullname), urlencode($value));
        }
        return $qstrings;
    }

    public function default_url_func($get_args) {
        $qstrings = $this->make_query_parts($get_args);

        return $_SERVER["SCRIPT_NAME"] . "?" . implode("&", $qstrings);
    }

    public function wrap_child_url($child_args) {
        return $this->make_url(array("child" => $child_args));
    }

    /**
     * Wrap the current arguments in an array, optionally adjust them, and pass
     * them off to another function for formatting (either the parent
     * scaffold's, or the default formatter).
     */
    public function make_url($args) {
        $get_args = array(
            "table"  => $this->g($args, "table", $this->table ? $this->table->table_name() : ""),
            "action" => $this->g($args, "action", $this->mode)
            );
        if ($get_args["action"] == "list") {
            $get_args["page"] = $this->g($args, "page", $this->page);
        }
        if ($get_args["action"] == "modify" || $get_args["action"] == "delete" || $get_args["action"] == "view") {
            $get_args["id"] = $this->g($args, "id", $this->id);
        }

        $get_args = array_merge($get_args, $args);

        return c($this->url_func, $get_args);
    }

    public function make_attrs() {
        $a = func_get_args();
        $attributes = call_user_func_array("array_merge", $a);

        $att = array();
        foreach ($attributes as $key=>$value)
            $att[] = sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));

        return implode(" ", $att);
    }

    public function check() {
        if (!isset($_SESSION)) session_start();
        switch ($this->mode) {
            case "view":
                $this->select_id();
                $this->check_child_scaffold();
                break;

            case "modify":
                $this->select_id();
                $this->check_submit();
                break;

            case "create":
                $this->id = self::CREATE;
                $this->check_submit();
                break;

            case "delete":
                $this->select_id();
                $this->check_delete();
                break;
        }
    }

    public function display() {
        $this->display_table_selector();
        $this->display_messages();

        switch ($this->mode) {
            case "list":
                $this->display_lister();
                break;

            case "modify":
                $this->select_id();

                $this->display_form();
                break;

            case "create":
                $this->id = self::CREATE; 
                $this->display_form();
                break;

            case "view":
                $this->select_id();
                $this->display_details();
                $this->display_details_nav();
                $this->display_child_scaffold();
                break;

            case "delete":
                $this->select_id();
                $this->display_details();
                $this->display_delete();
                break;
        }
    }

    private function child_subs() {
        if (!is_null($this->child_scaffold)) return false;

        $fn = array($this->table, "get_child_tables");
        if (!is_callable($fn)) return false;

        $tables = c($fn, $this->id);
        if (!$tables || !count($tables)) return false;

        return $tables;
    }

    private function check_child_scaffold() {
        $tables = $this->child_subs();
        if (!$tables) return;

        $child_control = isset($this->control["child"]) ? $this->control["child"] : array();

        /* Check is done in constructor */
        $this->child_scaffold = new Scaffold(
            $tables, 
            new EmbeddedScaffold($child_control, array($this, "wrap_child_url")));
    }

    public function display_child_scaffold() {
        if ($this->child_scaffold) $this->child_scaffold->display();
    }

    public function display_table_selector() {
        printf('<h2>%s</h2>', htmlspecialchars($this->table->table_name()));

        if (count($this->tables) < 2) return;

        $selectors = array();
        foreach ($this->tables as $table) {
            if ($table == $this->table)
                $selectors[] = $table->table_name();
            else
                $selectors[] = sprintf('<a href="%s">%s</a>', $this->make_url(array("table" => $table->table_name(), "action" => "list")), $table->table_name());
        }

        printf('<div class="table-selector">%s</div>', implode(" | ", $selectors));
    }

    public function display_chooser() {
        echo "<h2>Select a table</h2>";
        echo "<ul>";
        foreach ($this->tables as $table)
            printf("<li><a href=\"%s\">%s</a></li>", $this->make_url(array("table" => $table->table_name())), $table->table_name());
        echo "</ul>";
    }

    public function render_field($value, $col_info) {
        c($this->field_renderer, $value, $col_info);
    }

    // ----------------------------- LIST ACTION ---------------------------------------------------------------

    public function std_action_link($row, $caption, $action) {
        return sprintf('<a href="%s">%s</a>', $this->make_url(array("action" => $action, "id" => $row["__id"])), htmlspecialchars($caption));

    }

    public function display_lister() {
        $this->select_page();

        $this->field_renderer  = array($this, "render_list_field");
        $this->written_headers = false;

        $ids = $this->table->get_range(($this->page - 1) * $this->length, $this->length);

        $this->cells = array();

        foreach ($ids as $id) {
            $this->cells[] = array("__id" => $id);
            $this->table->render($id, $this, $this->mode);
        }

        $actions = array();
        if (!isset($ids[0]) || !(is_object($ids[0]) && !is_array($ids[0]))) 
            /* We can't serialize objects and arrays right now */
            $actions[] = partial(array($this, "std_action_link"), "View", "view");
        if ($this->has("save")) {
            $actions[] = partial(array($this, "std_action_link"), "Edit", "modify");
            $actions[] = partial(array($this, "std_action_link"), "Delete", "delete");
        }

        $h = 0;
        echo "<table>";
        foreach ($this->cells as $row) {

            if (!$h) {
                echo "<tr>";
                foreach ($row as $key=>$value) if ($key != "__id") printf("<th>%s</th>", ucfirst(htmlspecialchars($key)));
                if (count($actions)) printf('<th colspan="%d"></th>', count($actions));
                echo "</tr>";

                $h = 1;
            }

            echo "<tr>";
            foreach ($row as $key=>$value) if ($key != "__id") printf("<td>%s</td>", htmlspecialchars($value));
            foreach ($actions as $action) printf("<td>%s</td>", c($action, $row));
            echo "</tr>\r\n";
        }
        if (!$h) printf("<tr><td>No rows found.</td></tr>\r\n");
        echo "</table>";

        $this->display_pager();
        $this->display_add_link();
    }

    public function display_pager() {
        $pages = ceil($this->table->total_rows() / $this->length);
        if ($pages <= 1) return; /* Don't display a pager for one page */

        echo '<div class="pager">';
        for ($p = 1; $p <= $pages; $p++) 
            if ($p == $this->page)
                printf(' %d ', $p);
            else
                printf(' <a href="%s">%d</a> ', $this->make_url(array("page" => $p)), $p);
        echo '</div>';
    }

    public function display_add_link() {
        if ($this->has("save")) printf('<div class="nav"><a href="%s">Add row</a></div>', $this->make_url(array("action" => "create")));
    }

    public function render_list_field($value, $col_info) {
        $this->cells[count($this->cells) - 1][$col_info->name] = $value;
    }

    /**
     * A quick colinfo creator for non-database tables
     */
    public function col($name, $type="varchar", $size="255", $more=array()) {
        return (object)array_merge(compact("name", "type", "size"), $more);
    }

    private function get_submit_values() {
        return $this->stripboth($_POST);
    }

    public function stripboth($arr) {
        if (!get_magic_quotes_gpc()) return $arr;

        $r = array();
        foreach ($arr as $key=>$value) 
            $r[stripslashes($key)] = is_array($value) ? $this->stripboth($value) : stripslashes($value);

        return $r;
    }

    // ----------------------------- CREATE/MODIFY ACTION ---------------------------------------------------------------

    public function display_form() {
        $this->field_renderer  = array($this, "render_form_field");

        printf('<form method="POST"><table>');

        $this->table->render($this->id, $this, $this->mode);
        $this->form_row("", "submit", "", "Save");

        printf('</table></form>');
    }

    private function make_options($input) {
        if (is_array($input)) return $input;

        $ret = array();
        $parts = explode(",", $input);
        foreach ($parts as $part) {
            if (preg_match("/^'(.*)'$/", trim($part), $matches))
                $ret[stripslashes($matches[1])] = stripslashes($matches[1]);
            else
                $ret[$part] = $part;
        }
        return $ret;
    }

    private function render_options($list, $selected) {
        $ret = array();
        foreach ($list as $key=>$value) {
            $sel = $selected == $key ? " selected" : "";
            $ret[] = sprintf('<option value="%s"%s>%s</option>', htmlspecialchars($key), $sel, htmlspecialchars($value));
        }
        return implode("\r\n", $ret);
    }


    private function form_row($label, $type, $name, $value, $notes="", $attributes=array()) {
        switch ($type) {
            case  "textarea":
                $control = sprintf('<textarea %s>%s</textarea>', $this->make_attrs($attributes, compact("name")), htmlspecialchars($value));
                break;
            case "submit":
                if (isset($attributes["cancel"])) {
                    $cancel = sprintf('<input type="button" value="%s" onclick="history.go(-1);">', htmlspecialchars($attributes["cancel"]));
                    unset($attributes["cancel"]);
                } else $cancel = "";

                if (isset($attributes["stay"])) {
                    $stay = sprintf('<input type="submit" name="stay" value="%s">', htmlspecialchars($attributes["stay"]));
                    unset($attributes["stay"]);
                } else $stay = "";

                $submit = sprintf('<input %s>', $this->make_attrs($attributes, compact("type", "name", "value")));

                $control = "$submit $stay $cancel";
                break;
            case "select":
                $options = $attributes["options"];
                unset($attributes["options"]);
                $control = sprintf('<select %s>%s</select>', $this->make_attrs($attributes, compact("name")), $this->render_options($options, $value));
                break;
            default:
                $control = sprintf('<input %s>', $this->make_attrs($attributes, compact("type", "name", $value ? "value" : "" /* Only include value if it's non-empty, otherwise we pre-empt autocomplete */)));
                break;
        }

        printf('<tr><td><strong>%s%s</strong></td><td>%s</td>'."\r\n", ucfirst($label), $notes ? " ($notes)" : "", $control);
    }

    public function render_form_field($value, $col_info) {
        switch ($col_info->type) {
            case "text":
                $type = "textarea";
                $attr = array("cols" => 60, "rows" => 5);
                break;
            case "varchar":
                $type = "text";
                $attr = array("maxlength" => $col_info->size, "size" => isset($col_info->field_length) ? $col_info->field_length : min($col_info->size, 60));
                break;
            case "enum":
                 $type = "select";
                 $attr = array("options" => $this->make_options($col_info->size));
                 break;
            case "tinyint":
            case "flag":
                 $type = "checkbox";
                 $attr = array();
                 if ($value) $attr["checked"] = "checked";
                 $value = 1;
                 break;
            default:
                $type = "text";
                $attr = array();
                break;
        }
        $this->form_row($col_info->name, $type, $col_info->name, $value, isset($col_info->notes) ? $col_info->notes : "", $attr);
    }

    private function check_submit() {
        $post = $this->get_submit_values();
        if (!count($post)) return;

        $newid = $this->table->save($this->id, $post);
        if (!is_null($newid)) $this->id = $newid;

        $this->add_message(is_null($this->id) ? "Row added." : "Changes saved.");

        // If we have child tables, go to the view page (so we can immediately add subitems), otherwise, back to the list page for us
        if ($this->child_subs() && !is_null($this->id))
            $redirect = $this->make_url(array("action" => "view", "id" => $this->id));
        else {
            $redirect = $this->make_url(array("action" => "list"));
        }

        header("Location: $redirect");
        die;
    }

    // ----------------------------- DETAILS ACTION ---------------------------------------------------------------

    private function display_details() {
        $this->field_renderer  = array($this, "render_details_field");

        printf('<table>');
        $this->table->render($this->id, $this, $this->mode);
        printf('</table>');
    }

    public function render_details_field($value, $col_info) {
        printf("<tr><td><strong>%s</strong></td><td>%s</td></r>\r\n", ucfirst(htmlspecialchars($col_info->name)), $col_info->type == "raw" ? $value : htmlspecialchars($value));
    }

    public function display_details_nav() {
        printf('<div class="nav"><a href="%s">Edit</a> | <a href="%s">Delete</a> | <a href="%s">Back to list</a></div>',
            $this->make_url(array("action" => "modify")),
            $this->make_url(array("action" => "delete")),
            $this->make_url(array("action" => "list")));
    }

    // ----------------------------- DELETE ACTION ---------------------------------------------------------------

    private function display_delete() {
        printf('<form method="POST"><input type="hidden" name="__confirm" value="1"><table>');
        printf('<tr><td colspan="2">Really delete this row?</td></tr>');
        printf('<tr><td><input type="submit" value="Delete"></td><td><input type="button" value="Cancel" onclick="history.go(-1);"></td></tr>');
        printf('</table></form>');
    }

    private function check_delete() {
        $post = $this->get_submit_values();
        if (!isset($post["__confirm"])) return;

        $this->table->delete($this->id);

        $this->add_message("Row deleted.");
        header("Location: " . $this->make_url(array("action" => "list")));
        die;
    }
}

class EmbeddedScaffold {
    private $control;
    private $url_func;

    public function __construct($control, $url_func) {
        $this->control  = $control;
        $this->url_func = $url_func;
    }

    public function hook($scaffold) {
        $scaffold->set_control($this->control);
        $scaffold->set_url_func($this->url_func);
    }
}

/**
 * Partial function application
 *
 * From: http://metapundit.net/sections/blog/partial_function_application_in_php
 *
 * Usage: $fn = partial('htmlspecialchars', ENC_QUOTES);
 */
function partial() {
    if(!class_exists('partial')) {
        class partial{
            var $values = array();
            var $func;

            function partial($func, $args) {
                $this->values = $args;
                $this->func = $func;
            }

            function method() {
                $args = func_get_args();
                return call_user_func_array($this->func, array_merge($args, $this->values));
            }
        }
    }

    //assume $0 is funcname, $1-$x is partial values
    $args = func_get_args();
    $func = $args[0];
    $p = new partial($func, array_slice($args,1));
    return array($p, 'method');
}

/**
 * An alias for call_user_func
 */
function c() {
    $a = func_get_args();
    $fn = $a[0];
    $a = array_slice($a, 1);
    return call_user_func_array($fn, $a);
}


?>
