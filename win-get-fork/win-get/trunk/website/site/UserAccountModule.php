<?
/**
 * @author Rico Huijbers
 */

class UserAccountModule {
    private $controller;

    public function do_index($controller) {
        if (!UserAccount::logged_in()) $this->try_autologin();
        
        if (UserAccount::logged_in())
            $controller->insert(".logout");
        else
            $controller->insert(".login");
    }

    private function try_autologin() {
        if (!isset($_COOKIE["remember_email"]) || !$_COOKIE["remember_email"]) return;
        if (!isset($_COOKIE["remember_hash"]) || !$_COOKIE["remember_hash"]) return;

        $user = Mapper::Get("UserAccount")->find_by_email($_COOKIE["remember_email"]);
        if (!$user) return;
        if (!$user->correct_hash($_COOKIE["remember_hash"])) return;

        $user->login();
        Session::add_message("Your login has been remembered from a previous visit.");
    }

    // -------------------- Login ---------------------------------------------------

    public function do_login($controller) {
        if ($controller->is_current($this, "register")) return; /* No point in showing the login form while registering */
        /* FIXME: Damnit this is not possible with the way things are set up right now */

        $this->controller = $controller;

        $form = new RapidForm("loginform");

        $form->required_note("");

        $form->add("text", "email", array("maxlength" => 100, "size" => 20))
            ->label("Email")
            ->required("Enter your email address");

        $form->add("password", "password", array("maxlength" => 100, "size" => 20))
            ->label("Password")
            ->required("Enter a password");

        $form->add("flag", "remember", "Remember me");

        $form->add("submit", "Login");

        $form->add("html", sprintf('<small>(<a href="%s">Register</a>)<br />(<a href="%s">I forgot my password</a>)</small>', $controller->make_url(".register"), $controller->make_url(".forgotpass")));

        $form->done(array($this, "form_login"));

        $controller->title("Login");
        echo $form->to_html();
    }

    public function form_login($form) {
        $values = $form->get_all_values();

        $user = Mapper::Get("UserAccount")->find_by_email($values["email"]);

        if (!$user) throw new Exception("Unknown email address");
        if (!$user->correct_password($values["password"])) throw new Exception("Invalid username or password");
        $user->register_login();

        $user->login();
        Session::add_message("Login succesful.");

        // Also set cookies for login remembering
        if ($values["remember"]) {
            $expire = time() + 3600 * 24 * 356;
            setcookie("remember_email", $values["email"], $expire);
            setcookie("remember_hash",  $user->password_hash, $expire);
        }
    }

    // -------------------- Logout ---------------------------------------------------

    public function do_logout($controller) {
        $this->controller = $controller;

        $form = new RapidForm("logoutform");
        $form->add("html", sprintf('Logged in as <a href="%s"><b>%s</b></a>', $controller->make_url(".profile"), UserAccount::current()->display_name));
        $form->add("submit", "Logout");
        $form->done(array($this, "form_logout"));

        $controller->title("Logout");
        echo $form->to_html();
    }
    
    public function form_logout($form) {
        UserAccount::logout();
        Session::add_message("You have been logged out.");

        // Remove persistent login if it's there
        setcookie("remember_email", false, time() - 3600);
        setcookie("remember_hash",  false, time() - 3600);
    }

    // ------------- Register ---------------------------------------------------------
    public function do_register($controller) {
        $this->controller = $controller;
        $form = new RapidForm("registerform");

        $form->add("header", "Registration form");

        $form->add("text", "reg_email", array("maxlength" => 100, "size" => 40))
            ->label("Email")
            ->required("Enter your email address")
            ->validate()->email("This does not appear to be a correct email address.")
            ->validate()->custom(partial(array(Mapper::Get("UserAccount"), "not_in_use_or_equals"), "email"), "This e-mail address is already registered to an account.");

        $form->add("password", "password", array("maxlength" => 100, "size" => 20))
            ->label("Choose a password")
            ->required("You must enter a password.");

        $form->add("password", "confirmpassword", array("maxlength" => 100, "size" => 20))
            ->label("Confirm password")
            ->required("You must enter a confirmation password.")
            ->validate()->must_match("password", "Passwords do not match.");

        $form->add("text", "display_name", array("maxlength" => 100, "size" => 30))
            ->label("Screen name")
            ->required("Enter a screen name.")
            ->validate()->custom(partial(array(Mapper::Get("UserAccount"), "not_in_use_or_equals"), "display_name"), "This display name is already in use.");

        $form->add("recaptcha", $GLOBALS["recaptcha_private"], $GLOBALS["recaptcha_public"])
            ->label("Human check");

        $form->add("submit", "Register");

        $form->done(array($this, "form_register"));

        $title = "Register";
        if (file_exists("view/form_page.php")) return include("view/form_page.php");
        $controller->title($title);
        echo $form->to_html();
    }

    public function form_register($form) {
        $values = $form->get_all_values();

        $account = UserAccount::create_new($values["reg_email"], $values["password"], $values["display_name"]);

        Session::add_message("The account has been created.");

        if (!UserAccount::logged_in()) {
            $account->login();
            Session::add_message("We have automatically logged you in as {$account->display_name}.");
        }

        $this->controller->go(".profile");
    }

    // ------------- Forgot pass ---------------------------------------------------------
    public function do_forgotpass($controller) {
        $controller->title("Recover password");

        printf("<p>Since we are are currently hosting this site at SourceForge.net, and SourceForge does not allow its websites to send out e-mails, we cannot automatically recover your e-mail address.</p>");

        printf("<p>Instead, please <a href=\"%s\">come to the forum</a> and request that your password be reset.</p>", "http://win-get.sourceforge.net/forum/index.php?board=6.0");

        printf("<p>We're sorry for the roundabout method but there is just no other way right now.</p>");
    }

    // ------------- Profile ---------------------------------------------------------
    public function do_profile($controller) {
        $user = UserAccount::current();
        if (!$user) $controller->go(".index");

        $controller->title("{$user->display_name}'s profile");
        echo "You have the following options:";
        echo "<ul>";
        printf('<li><a href="%s">View your profile</a></li>',    $controller->make_url(".view-profile", array("id" => $user->id)));
        printf('<li><a href="%s">Edit your profile</a></li>',  $controller->make_url(".edit-profile"));
        printf('<li><a href="%s">Change your password</a></li>', $controller->make_url(".change-password"));
        echo "</ul>";
    }

    // ------------- Change account details ---------------------------------------------------------
    public function do_edit_profile($controller) {
        $user = UserAccount::current();
        if (!$user) $controller->go(".index");

        $form = new RapidForm("accountform");

        $form->add("header", "Profile information");

        $form->add("text", "email", array("maxlength" => 100, "size" => 40))
            ->label("Email")
            ->required("Enter your email address")
            ->validate()->email("This does not appear to be a correct email address.")
            ->validate()->custom(partial(array(Mapper::Get("UserAccount"), "not_in_use_or_equals"), "email", $user->email), "This e-mail address is already registered to an account.");

        $form->add("text", "display_name", array("maxlength" => 100, "size" => 30))
            ->label("Screen name")
            ->required("Enter a screen name.")
            ->validate()->custom(partial(array(Mapper::Get("UserAccount"), "not_in_use_or_equals"), "display_name", $user->display_name), "This display name is already in use.");

        $form->add("html", "The following information is optional, but you can add it to complete your profile if you wish.");

        $form->add("text", "website", array("maxlength" => 200, "size" => 40))
            ->label("Your website");

        $form->add("textarea", "profile", array("cols" => 60, "rows" => 6))
            ->label("Some information about yourself");

        $form->add("submit", "Save changes");

        $form->set_defaults($user->get_all());
        $form->done(array($this, "postback_edit_profile"), $controller, $user);

        $title = "{$user->display_name}'s profile";
        if (file_exists("view/form_page.php")) return include("view/form_page.php");
        $controller->title($title);
        echo $form->to_html();

        printf('<p>(<a href="%s">Return to profile menu</a>)</p>', $controller->make_url(".profile"));
   }

   public function postback_edit_profile($form, $controller, $user) {
       $user->set_all($form->get_all());

       if ($user->save())
           Session::add_message("The changes to {$user->display_name}'s account have been saved.");
        else
           Session::add_message("! No change.");

       $controller->go(".profile");
   }

    // ------------- View profile ---------------------------------------------------------
    public function do_view_profile($controller) {
        $user = Mapper::Get("UserAccount")->load($controller->get("id", REQUIRED));

        $controller->title("{$user->display_name}'s profile");
        include "view/profile_view.php";
    }

    // ------------- Change password ---------------------------------------------------------
    public function do_change_password($controller) {
        $user = UserAccount::current();
        if (!$user) $controller->go(".index");

        $form = new RapidForm("accountform");

        $form->add("header", "Change password");

        $form->add("password", "old_password", array("maxlength" => 100, "size" => 15))
            ->label("Old password")
            ->required("You must enter your old password")
            ->validate()->custom(array($user, "correct_password"), "Invalid password");

        $form->add("password", "new_password", array("maxlength" => 100, "size" => 20))
            ->label("New password")
            ->required("You must enter a new password.");

        $form->add("password", "confirmpassword", array("maxlength" => 100, "size" => 20))
            ->label("Confirm new password")
            ->required("You must enter a confirmation password.")
            ->validate()->must_match("new_password", "Passwords do not match.");

        $form->add("submit", "Change password");

        $form->done(array($this, "postback_change_password"), $controller, $user);

        $title = "Change {$user->display_name}'s password";
        if (file_exists("view/form_page.php")) return include("view/form_page.php");
        $controller->title($title);
        echo $form->to_html();
        printf('<p>(<a href="%s">Return to profile menu</a>)</p>', $controller->make_url(".profile"));
    }

    public function postback_change_password($form, $controller, $user) {
        $user->set_password($form->get_value("new_password"));
        if ($user->save())
            Session::add_message("{$user->display_name}'s password has been changed.");
        else
            Session::add_message("! No change.");

        $controller->go(".profile");
    }
}

?>
