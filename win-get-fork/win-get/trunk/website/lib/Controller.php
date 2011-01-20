<?
/**
 * A very simple controller
 * 
 * Configure it and mount modules (classes) that will handle the actual page requests.
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

error_reporting(E_ALL);

define("REQUIRED", "\r\n--THROW--\r\n");

class Controller {
    public $get_variables;
    public $depth            = 1;
    protected $modules       = array();
    protected $url_func      = null;
    public $active_module = null;
    public $active_action = null;
    protected $frame         = null;
    public    $head          = null;
    protected $title_func    = null;
    protected $initialized   = array();

    public function __construct($get_variables=null) {
        if (is_null($get_variables)) {
            $this->get_variables = $_GET;
            if (get_magic_quotes_gpc()) $this->get_variables = $this->strip_r($this->get_variables);
        }
        else $this->get_variables = $get_variables;

        $this->url_func = array($this, "default_url_func");


        $this->head = new HtmlHead();
        $this->title_func = array($this->head, "title");
    }

    public function set_frame($frame) {
        $this->frame = $frame;
    }

    private function get_frame_html() {
        if (!$this->frame) return "";
         
        $controller = $this;

        ob_start();
        include $this->frame;
        return ob_get_clean();
    }

    public function get($name, $default="") {
        if (isset($this->get_variables[$name])) return $this->get_variables[$name];
        if ($default == REQUIRED) throw new Exception("Argument required: $name");
        return $default;
    }

    public function set_url_func($func) {
        $this->url_func = $func;
    }

    public function strip_r($arg) {
        if (is_array($arg)) return array_map(array($this, "strip_r"), $arg);
        return stripslashes($arg);
    }

    public function mount($base_url, $module) {
        $this->modules[$base_url] = $module;
    }

    /**
     * Redirect the user to a different module.action within the same controller
     */
    public function go($where, $args=array()) {
        if (class_exists("Session")) {
            Session::redirect($this->make_url($where, $args));
            return;
        }

        header("Location: " . $this->make_url($where, $args));
        die;
    }

    /**
     * Redirect the user to a different module.action within the top controller
     */
    public function escape_frame($where, $args=array()) {
        $this->go($where, $args);
    }

    /**
     * Insert the entire output of a different module.action into the current page 
     */
    public function insert($what, $extra_args=array()) {
        echo $this->invoke($what, $extra_args);
    }

    /**
     * Embed the important output of a module.action into the current page
     */
    public function embed($what, $extra_args=array()) {
        $fn = $this->title_func; $this->title_func = array($this, "discard");
        $output = $this->invoke($what);
        $this->title_func = $fn;

        // We now have the output of the embedded thing. See if we have an area
        // marked as content. If so, only return that, otherwise return
        // everything.
        echo $this->get_part($output, "content");
    }

    public function has_module($module_name) {
        return isset($this->modules[$module_name]);
    }

    /**
     * Returns a (module, action) pair for the given spec
     */
    protected function split_spec($spec) {
        if (count($this->modules) == 0) throw new Exception("No modules mounted!");

        $k = array_keys($this->modules);
        $default_module = $this->active_module ? $this->active_module : (count($k) ? $k[0] : "");
        $default_action = $this->active_action ? $this->active_action : "index";
        
        $parts = explode(".", $spec);
        if ($spec == "") {
            $module = $default_module;
            $action = $default_action;
        }
        elseif (count($parts) == 1) {
            if ($parts[0]) $module = $parts[0]; else $module = $default_module;
            $action = "index";
        }
        else {
            if ($parts[0]) $module = $parts[0]; else $module = $default_module;
            if ($parts[1]) $action = $parts[1]; else $action = "index";
        }

        if (!isset($this->modules[$module])) $module = $default_module;

        return array($module, $action);
    }

    public function action_exists($spec) {
        list($module, $action) = $this->split_spec($spec);

        if (!isset($this->modules[$module])) return false;
        $fn = array($this->modules[$module], "do_" . $action);
        return is_callable($fn);
    }

    public function this_module() {
        return $this->active_module;
    }

    public function this_action() {
        return $this->active_action;
    }

    public function is_current($module_object, $action_string) {
        if ($this->modules[$this->active_module] != $module_object) return false;
        return $this->active_action == $action_string;
    }

    /**
     * Return the handler function for the given handlerspec
     *
     * Handlerspec is like:
     * - module
     * - module.action
     * - .action
     * 
     * Return as follows:
     *
     * array(module, action, handler)
     */
    private function get_handler($handlerspec) {
        list($module, $action) = $this->split_spec($handlerspec);

        if (!isset($this->modules[$module])) throw new Exception("No such module: $module");

        $action = str_replace("-", "_", $action);

        $fn = array($this->modules[$module], "do_" . $action);
        if (!$action || !is_callable($fn)) $action = "index";

        $fn = array($this->modules[$module], "do_" . $action);
        return array($module, $action, $fn);
    }

    public function dispatch() {
        $output = $this->invoke(sprintf("%s.%s", $this->get("module"), $this->get("action")));

        if (class_exists("Session") && $this->depth == 1) {
            $output = $this->inject(Session::render_messages(), "messages", $output);
            
            if (Session::should_send()) Session::send();
        }

        // Splice the output into the given frame 
        $frame = $this->get_frame_html();
        if ($frame) $output = $this->inject($output, "content", $frame);

        // If this is depth 1, wrap in head, otherwise just echo
        if ($this->depth == 1)
            $this->head->wrap_body($output);
        else
            echo $output;
    }

    protected function call_handler($fn) {
        ob_start();
        call_user_func($fn, $this);


        ob_end_flush();
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

    /**
     * Make a URL that does not contain a reference to a module and action,
     * just contains get arguments
     */
    public function only_args_url($url_args) {
        return call_user_func($this->url_func, $url_args);
    }

    /**
     * Make a URL to a module
     *
     * Reference can be like: 
     * - module
     * - module.action
     * - .action
     *
     * If $args is an object that contains a get_id() function, it will be
     * imploded to "id" => $object->get_id().
     */
    public function make_url($what="", $args=array()) {
        $parts = explode(".", $what);
        if (count($parts) > 2) throw new Exception("make_url: too many parts");
        if (count($parts) == 1) {
            if ($parts[0]) $module = $parts[0]; else $module = $this->active_module;
            $action = null;
        }
        else {
            if ($parts[0]) $module = $parts[0]; else $module = $this->active_module;
            if ($parts[1]) $action = $parts[1]; else $action = null;
        }

        if (is_null($module)) {
            if (is_null($action)) $action = $this->active_action;
            $module = $this->active_module;
        }

        if (!is_array($args)) $args = array($args);
        foreach (array_keys($args) as $k) {
            if (is_object($args[$k]) && is_callable(array($args[$k], "get_id"))) {
                $obj = $args[$k];
                unset($args[$k]);
                $args["id"] = $obj->get_id();
            }
        }

        $url_args = array();
        if ($module) $url_args["module"] = $module;
        if ($action) $url_args["action"] = $action;
        $url_args = array_merge($url_args, $args);

        return call_user_func($this->url_func, $url_args);
    }

    /**
     * Return the URL for a GET form
     */
    public function getform_url() {
        return $this->make_url();
    }

    /**
     * Return the target URL for a POST form
     */
    public function postform_url() {
        return $this->make_url();
    }

    public function getform_hidden() {
        $r = array(sprintf('<input type="hidden" name="module" value="%s">', htmlspecialchars($this->active_module)));
        if ($this->active_action)
            $r[] = sprintf('<input type="hidden" name="action" value="%s">', htmlspecialchars($this->active_action));

        return implode("", $r);
    }

    /**
     * Initialize given module, by calling setup on it (if it exists).
     */
    public function initialize($module_name) {
        if (in_array($module_name, $this->initialized)) return;

        $fn = array($this->modules[$module_name], "setup");
        if (is_callable($fn)) call_user_func($fn, $this);

        $this->initialized[] = $module_name;
    }

    /**
     * Invoke a handler and return the output
     */
    public function invoke($what, $extra_args=array()) {
        // Save state
        $buffering = false;
        try {
            list($old_module, $old_action, $old_get) = array($this->active_module, $this->active_action, $this->get_variables);

            list($this->active_module, $this->active_action, $fn) = $this->get_handler($what);
            $this->get_variables = array_merge($this->get_variables, $extra_args);

            $this->initialize($this->active_module);

            ob_start(); $buffering = true;
            call_user_func($fn, $this);
            $output = ob_get_clean(); $buffering = false;

            // Restore state
            list($this->active_module, $this->active_action, $this->get_variables) = array($old_module, $old_action, $old_get);

            return $output;
        }
        catch (Exception $ex) {
            Session::add_message("x " . $ex->getMessage());
            if ($buffering) return ob_get_clean();
            return "";
        }
    }

    /**
     * Return the content out of a page, marked by begin and end comment tags
     *
     * Failing that, just get the body, otherwise just return everything.
     */
    protected function get_part($html, $marker) {
        if (preg_match("|<!--\s*begin\s+" . preg_quote($marker) . "\s*-->(.*)<!--\s*end\s+" . preg_quote($marker) . "\s*-->|si", $html, $matches)) return $matches[1];
        // FIXME: extract scripts and other stuff out of the <head>

        if (preg_match("|<body[^>]*>(.*)</body>|si", $html, $matches)) return $matches[1];
        return $html;
    }

    /**
     * Inject the given content into an HTML page at the location indicated by the marker comment,
     * or otherwise at the top of the head if the marker could not be found.
     */
    protected function inject($what, $marker, $html) {
        $html = preg_replace("|<!--\s*" . preg_quote($marker) . "\s+here\s*-->|i", $what, $html, 1, $count);
        if ($count) return $html;

        $html = preg_replace("|(<body[^>]*>)|i", "$1" . $what, $html, 1, $count);
        if ($count) return $html;

        return $what . $html;
    }

    /**
     * Sets the title of the current page
     *
     * Depends on what the controller is doing whether this has any effect
     */
    public function title($title) {
        call_user_func($this->title_func, $title);
    }

    public function discard($input) {
        /* Do nothing */
    }
}

class SubController extends Controller {
    private $parent_controller;
    private $child_name;
    private $child_module;

    public function __construct($parent_controller, $child_name, $initial_location="", $extra_arguments=array()) {
        $this->parent_controller = $parent_controller;
        $this->child_name        = $child_name;

        $this->get_variables     = array_merge($extra_arguments, $parent_controller->get($this->child_name, array()));
        $this->url_func          = array($this, "wrap_child_url");
        $this->depth             = $parent_controller->depth + 1;
        $this->head              = $parent_controller->head;

        if ($this->depth < 50) $this->modules = $parent_controller->modules; /* Safety */
        list($this->active_module, $this->active_action) = $this->split_spec($initial_location);
    }

    public function wrap_child_url($child_args) {
        return $this->parent_controller->only_args_url(array_merge($this->parent_controller->get_variables, array($this->child_name => $child_args)));
    }

    protected function call_handler($fn) {
        ob_start();
        call_user_func($fn, $this);
        $output = ob_get_clean();

        echo $this->get_part($output, "frame");
    }

    public function escape_frame($where, $args=array()) {
        $this->parent_controller->escape_frame($where, $args);
    }

    public function title($title) {
        // Discard titles of subcontrollers
    }
}

class HtmlHead {
    private $doctype  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> ';
    private $encoding = "iso-8859-15";
    public  $title;
    public  $scripts = array();
    public  $script_literals = array();
    public  $css     = array();

    public function transitional() {
        $this->doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> ';
    }

    public function encoding($e) {
        $this->encoding = $e;
    }

    public function title($title) {
        $this->title = $title;
    }

    public function make_abs($path, $rel) {
        if (!$rel || !$path) return $path;

        if (preg_match("|^/|", $path) || preg_match("/[a-z]:\\\/i", $path)) return $path; /* Absolute */

        /* Make relative wrt. directory of rel */
        if (is_dir($rel))
            $path = realpath($rel) . "/" . $path;
        else
            $path = dirname(realpath($rel)) . "/" . $path;

        return $path;
    }

    public function make_href($path) {
        // Make this into an href, i.e., wrt. to the page that's currently being viewed
        $view_dir = dirname($_SERVER["SCRIPT_FILENAME"]) . "/";

        if (substr($path, 0, strlen($view_dir)) == $view_dir) $path = substr($path, strlen($view_dir));
        return $path;
    }

    public function script($script, $rel=null) {
        $script = $this->make_abs($script, $rel);

        if (in_array($script, $this->scripts)) return;
        if (!is_file($script)) Session::add_message("! Required script file not found: $script");
        $this->scripts[] = $script;
    }

    public function script_literal($literal) {
        $this->script_literals[] = $literal;
    }

    public function css($css, $rel=null) {
        $css = $this->make_abs($css, $rel);
        if (in_array($css, $this->css)) return;
        if (!is_file($css)) Session::add_message("! Required CSS file not found: $css");
        $this->css[] = $css;
    }

    public function wrap_body($content) {
        ?><?= $this->doctype ?>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?= $this->encoding ?>" />
        <? foreach ($this->scripts as $script): ?>
        <script src="<?= $this->make_href($script) ?>" type="text/javascript"></script>
        <? endforeach ?>
        <? foreach ($this->css as $css): ?>
        <link rel="stylesheet" type="text/css" href="<?= $this->make_href($css) ?>" />
        <? endforeach ?>
        <? foreach ($this->script_literals as $literal): ?>
        <script type="text/javascript"><?= $literal ?></script>
        <? endforeach ?>
        <title><?= htmlspecialchars($this->title) ?></title>
    </head>
    <body>
        <?= $content ?>
    </body>
</html><?
    }
}

?>
