<?
/**
 * Mirror module
 *
 * @author Rico Huijbers
 */

class MirrorModule {
    private $controller;

    public function do_index($controller) {
        $controller->go(".list");
    }

    public function do_list($controller) {
        $packageid = $controller->get("packageid", null);

        $mirrors = Mapper::Get("Mirror")->all_latest($packageid);

        list($active_mirrors, $deleted_mirrors) = partition($mirrors, create_function('$x', 'return $x->r_alive;'));

        include "view/mirrors.php";
    }

    public function do_view($controller) {
        $mirror = Mapper::Get("Mirror")->retrieve($controller->get("id"));

        $mirrors = new SubController($controller, "mirrors", "mirror", array("mirrorid" => $mirror->mirrorid));

        include "view/mirror_view.php";
    }

    public function do_edit($controller) {
        $this->controller = $controller;
        $mirror = Mapper::Get("Mirror")->retrieve($controller->get("id"));

        $form = $this->make_form($mirror)
            ->set_defaults($mirror->get_all())
            ->done(array($this, "pb_save"));

        include "view/mirror_edit.php";
    }

    public function pb_save($form) {
        $values  = $form->get_all_values();
        $mirror = $form->get_tag("mirror");

        if (!Perms::can_trust($mirror->package->application) && isset($values["trusted"])) unset($values["trusted"]); /* Don't change trusted, if the URL hasn't changed */
        if ($values["url"] == $mirror->url && !isset($values["trusted"])) $values["trusted"] = false; /* Reset trust when url changed by non-mod */

        $mirror->set_all($values);
        $mirror->save();

        Session::add_message("Changes to mirror saved.");

        $this->controller->go(".view", array("id" => $mirror->mirrorid));
    }

    private function make_form($mirror) {
        $frm = new RapidForm("mirrorform");
        $frm->tag("mirror", $mirror);

        $frm->add("text", "url", array("maxlength" => 300, "size" => 60))
            ->label("URL")
            ->required("Enter a download URL");

        if (Perms::can_trust($mirror->package->application)) $frm->add("flag", "trusted", "Trusted");

        if (!UserAccount::logged_in()) {
            $frm->add("static", "<small>Please fill out this captcha. If you register an account, you won't need do this every time.</small>");
            $frm->add("recaptcha")->label("Spam protection");
        }

        $frm->add("submit", "Save");

        return $frm;
    }
}

?>
