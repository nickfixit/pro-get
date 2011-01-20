<?
/**
 * @author Rico Huijbers
 */

class UserAccount extends DormRecord {
    public static $salt = "some_salt"; /* Set this to some value to make the password hashes harder to look up */
     
    protected $userid;
    protected $email;
    protected $password_hash;
    protected $display_name;
    protected $open_id;
    protected $class;
    protected $registered;
    protected $reset_nonce;
    protected $last_login;
    protected $website;
    protected $profile;

    public function __wakeup() {
        Mapper::Get($this)->reload($this);
    }

    public function correct_password($password) {
        return md5($password . self::$salt) == $this->password_hash;
    }

    public function correct_hash($hash) {
        return $hash == $this->password_hash;
    }

    public function set_password($password) {
        $this->password_hash = md5($password . self::$salt);
    }

    public function set_registered($registered) {
        $this->date_time($registered);
        $this->registered = $registered;
    }

    public function set_last_login($last_login) {
        $this->date_time($last_login);
        $this->last_login = $last_login;
    }

    public function register_login() {
        $this->set_last_login(time());
        $this->save();
    }

    /**
     * Create a new user account
     */
    public static function create_new($email, $password, $display_name) {
        $account = new UserAccount();
        $account->email = $email;
        $account->set_password($password);
        $account->display_name = $display_name;
        $account->set_registered(null);
        $account->set_last_login(null);
        $account->save();

        return $account;
    }

    public function get_maintained_applications() {
        return Mapper::Get("Application")->all_maintained($this->id);
    }

    public function get_pending_applications() {
        return Mapper::Get("Application")->all_maintained($this->id, false);
    }

    public static function logged_in() {
        return Session::get("current_user");
    }

    /**
     * Set this user account as the logged-in user account
     */
    public function login() {
        Session::set("current_user", $this);
    }

    public static function logout() {
        Session::remove("current_user");
    }

    public static function current() {
        return Session::get("current_user");
    }

    public function profile_page_href($controller) {
        return $controller->make_url("auth.view-profile", array("id" => $this->id));
    }

    public function profile_page_link($controller) {
        return sprintf('<a href="%s">%s</a>', $this->profile_page_href($controller), htmlspecialchars($this->display_name));
    }
}

class UserAccountMapper extends Mapper {
    public function find_by_email($email) {
        $q = Query::N()->restrict("email = %s", $email);
        return $this->load($q);
    }

    public function not_in_use_or_equals($value, $field, $equals=null) {
        if (!is_null($equals) && $value == $equals) return true;

        return Query::N($this->table)->restrict("$field = %s", $value)->count() == 0;
    }

    public function all_maintainers($applicationid, $approved=true) {
        $q = Query::N("user_account NATURAL JOIN maintainer")
            ->restrict("applicationid = %s", $applicationid)
            ->restrict($approved ? "approved" : "NOT approved")
            ->order("display_name");
        return $this->load_all($q);
    }
}


?>
