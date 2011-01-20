<?
/**
 * Application module
 *
 * @author Rico Huijbers
 */

class ApplicationModule {
    private $controller;

    public function do_index($controller) {
        $controller->go(".list");
    }

    public function do_list($controller) {
        $search_words = array_filter(explode(" ", $controller->get("q")));

        $apps = Mapper::Get("Application")->all_latest($search_words);

        list($active_apps, $deleted_apps) = partition($apps, create_function('$x', 'return $x->r_alive;'));

        include "view/applications.php";
    }

    public function do_view($controller) {
        $app = Mapper::Get("Application")->retrieve($controller->get("id"));

        $packages = new SubController($controller, "packages", "package", array("applicationid" => $app->applicationid));

        include "view/application_view.php";
    }

    public function do_edit($controller) {
        $this->controller = $controller;
        $app = Mapper::Get("Application")->retrieve($controller->get("id"));

        $form = $this->make_appform()
            ->tag("app", $app)
            ->set_defaults($app->get_all())
            ->done(array($this, "pb_save"));

        include "view/application_edit.php";
    }

    public function pb_save($form) {
        $values = $form->get_all_values();
        $app = $form->get_tag("app", new Application());

        $app->set_all($values);
        $app->save();

        Session::add_message("Changes to application saved.");

        $this->controller->go(".view", array("id" => $app->applicationid));
    }

    private function make_appform() {
        $frm = new RapidForm("appform");
        $frm->add("text", "aid", array("maxlength" => 100, "size" => 20))
            ->label("Identifier")
            ->required("Give the application an identifier")
            ->filter(array("Application", "format_aid"));

        $frm->add("text", "purpose", array("maxlength" => 200, "size" => 60))
            ->label("Purpose")
            ->required("How would you feel without a purpose in life? Please give this application one");

        $frm->add("textarea", "description", array("cols" => 50, "rows" => 6))
            ->label("Description")
            ->required("Please also give a more elaborate description");

        $frm->add("text", "website", array("maxlength" => 200, "size" => 40))
            ->label("Website");

        if (!UserAccount::logged_in()) {
            $frm->add("static", "<small>Please fill out this captcha. If you register an account, you won't need do this every time.</small>");
            $frm->add("recaptcha")->label("Spam protection");
        }

        $frm->add("submit", "Save");

        return $frm;
    }

    public function do_history($controller) {
        $revisions = Mapper::Get("Application")->all_revisions($controller->get("id"));
        $revisions = array_reverse($revisions);

        $app = $revisions[count($revisions) - 1];

        include "view/application_history.php";
    }
}

?>
