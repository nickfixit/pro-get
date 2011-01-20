<?
/**
 * Application module
 *
 * @author Rico Huijbers
 */

class PackageModule {
    private $controller;

    public function do_index($controller) {
        $controller->go(".list");
    }

    public function do_list($controller) {
        $applicationid = $controller->get("applicationid", null);

        $packages = Mapper::Get("Package")->all_latest($applicationid);

        list($active_packages, $deleted_packages) = partition($packages, create_function('$x', 'return $x->r_alive;'));

        include "view/packages.php";
    }

    public function do_view($controller) {
        $package = Mapper::Get("Package")->retrieve($controller->get("id"));

        $mirrors = new SubController($controller, "mirrors", "mirror", array("packageid" => $package->packageid));

        include "view/package_view.php";
    }

    public function do_edit($controller) {
        $package = Mapper::Get("Package")->retrieve($controller->get("id"));

        $form = $this->make_form($package)
            ->set_defaults($package->get_all())
            ->done(array($this, "pb_save"), $controller);

        include "view/package_edit.php";
    }

    public function pb_save($form, $controller) {
        $values  = $form->get_all_values();
        $package = $form->get_tag("package");

        if (!Perms::can_trust($package->application) && isset($values["trusted"])) unset($values["trusted"]); /* Make sure non-mods can't change trusted */
        if ($values["md5sum"] != $package->md5sum && !isset($values["trusted"])) $values["trusted"] = false; /* Reset trust when MD5 changes by non-mod */

        $package->set_all($values);
        if ($package->save())
            Session::add_message("Changes to package saved.");
        else
            Session::add_message("! No change.");

        $controller->go(".view", array("id" => $package->packageid));
    }

    private function make_form($package) {
        $frm = new RapidForm("packageform");
        $frm->tag("package", $package);

        $frm->add("text", "version", array("maxlength" => 20, "size" => 10))
            ->label("Version")
            ->required("Enter a version number");

        $frm->add("dropdown", "language", Package::languages())
            ->label("Language");

        $frm->add("text", "filename", array("maxlength" => 100, "size" => 30))
            ->label("Filename")
            ->required("Enter a filename");

        $frm->add("text", "silent", array("maxlength" => 200, "size" => 40))
            ->label("Silent aguments");

        $frm->add("text", "silent", array("maxlength" => 20, "size" => 10))
            ->label("Size");

        $frm->add("text", "md5sum", array("maxlength" => 40, "size" => 40))
            ->label("MD5 checksum");

        $frm->add("text", "installer_in_zip", array("maxlength" => 100, "size" => 35))
            ->label("Installer in ZIP");

        if (Perms::can_trust($package->application)) $frm->add("flag", "trusted", "Trusted");

        if (!UserAccount::logged_in()) {
            $frm->add("static", "<small>Please fill out this captcha. If you register an account, you won't need do this every time.</small>");
            $frm->add("recaptcha")->label("Spam protection");
        }

        $frm->add("submit", "Save");

        return $frm;
    }

    public function do_history($controller) {
        $revisions = Mapper::Get("Package")->all_revisions($controller->get("id"));
        $revisions = array_reverse($revisions);

        $package = $revisions[count($revisions) - 1];

        include "view/package_history.php";
    }
}

?>
