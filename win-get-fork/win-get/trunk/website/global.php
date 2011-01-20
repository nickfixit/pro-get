<?

error_reporting(E_ALL);

require_once "config.php";

function __autoload($class) {
    if ($class == "Mapper" || $class == "DormRecord") $class = "Dorm";
    else $class = preg_replace("/Mapper$/", "", $class);

    if (file_exists("site/$class.php")) { require_once "site/$class.php"; return; }
    if (file_exists("lib/$class.php"))  { require_once "lib/$class.php"; return; }
}

Mapper::Configure("Application", "application", "applicationid");
Mapper::Configure("Package", "package", "packageid");
Mapper::Configure("Mirror", "mirror", "mirrorid");
Mapper::Configure("UserAccount", "user_account", "userid");
Mapper::Configure("Maintainer", "maintainer", array("applicationid", "userid"));

?>
