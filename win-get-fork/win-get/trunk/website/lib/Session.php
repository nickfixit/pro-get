<?

/**
 * Session management class
 *
 * This class has the advantage that it will not send out session cookies if
 * the session is empty (thus saving state).
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */
class Session {
    private $session_vars = array();

    protected function __construct() {
        $this->start();
    }

    protected function start() {
        // Prevent PHP from sending out a default sessionid or doing URL rewriting
        ini_set('session.use_cookies', 0);
        ini_set('session.use_only_cookies', 1);
        ini_set('url_rewriter.tags', '');
        session_cache_limiter("");

        if (isset($_COOKIE[session_name()])) {
            $sid = $_COOKIE[session_name()];
            if (get_magic_quotes_gpc()) $sid = stripslashes($sid);
            
            session_id($sid);
        }

        session_start();
        $this->session_vars =& $_SESSION;
    }

    public function _send($force=false) {
        if (count($this->session_vars) || $force) {
            header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
            setcookie(session_name(), session_id(), 0, "/");
        }
    }

    private static $instance = null;
    public static function Instance() {
        if (!self::$instance) self::$instance = new Session();
        return self::$instance;
    }

    public function _get($var, $default='') {
        if (isset($this->session_vars[$var])) return $this->session_vars[$var];

        return $default;
    }

    public function &_get_ref($var, $default='') {
        if (!isset($this->session_vars[$var])) $this->session_vars[$var] = $default;

        return $this->session_vars[$var];
    }

    public function _set($var, $value) {
        $this->session_vars[$var] = $value;
    }

    public function _remove($var) {
        if (isset($this->session_vars[$var])) unset($this->session_vars[$var]);
    }

    public function _should_send() {
        return count($this->session_vars);
    }

    public function _add($var, $value) {
        if (!isset($this->session_vars[$var])) {
            $this->session_vars[$var] = array($value);
            return;
        }

        if (is_array($this->session_vars[$var])) {
            $this->session_vars[$var][] = $value;
            return;
        }

        $this->session_vars[$var] = array($this->session_vars[$var], $value);
    }

    public static function get($var, $default='') {
        return Session::Instance()->_get($var, $default);
    }

    public static function get_once($var, $default='') {
        $r = Session::Instance()->_get($var, $default);
        Session::Instance()->_remove($var);
        return $r;
    }

    public static function &get_ref($var, $default='') {
        return Session::Instance()->_get_ref($var, $default);
    }

    public static function set($var, $value) {
        Session::Instance()->_set($var, $value);
    }

    public static function add($var, $value) {
        Session::Instance()->_add($var, $value);
    }

    public static function remove($var) {
        Session::Instance()->_remove($var);
    }

    public static function should_send() {
        return Session::Instance()->_should_send();
    }

    public static function send($force=false) {
        Session::Instance()->_send($force);
    }

    public static function redirect($url) {
        Session::send();
        header("Location: $url");
        die;
    }

    //---------- Message functions ---------------------------------------

    /**
     * Add a processing message to the session. The message will be cleared
     * after it is processed.
     *
     * Messages that start with a "!" count as warnings, while messages that
     * start with an "x" count as errors. Otherwise, it's a success message.
     */
    public static function add_message($message) {
        if (!is_string($message)) $message = print_r($message, true);
        Session::add("user_messages", $message);
    }

    public static function get_messages() {
        return Session::get_once("user_messages", array());
    }

    /**
     * Call this when you're sure the user has got the messages.
     */
    public static function clear_messages() {
        Session::remove("user_messages");
    }

    public static function split_message($msg) {
        $class   = "success";
        $message = trim($msg);

        if (preg_match("|^!\s*(.*)$|", $msg, $matches)) { $class = "warning"; $message = $matches[1]; }
        elseif (preg_match("|^x\s*(.*)$|m", $msg, $matches)) { $class = "error"; $message = $matches[1]; }

        return array($class, $message);
    }

    public static function render_messages() {
        $m = "";
        foreach (self::get_messages() as $message) {
            list($class, $message) = self::split_message($message);

            $m .= sprintf('<div class="%s usermessage">%s</div>', $class, htmlspecialchars($message));
        }
        return $m;
    }
}

?>
