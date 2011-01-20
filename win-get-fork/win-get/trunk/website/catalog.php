<?

require_once "global.php";
require_once "site/view/helpers.php";

$controller = new Controller();
$controller->set_frame("site/view/page_frame.php");
$controller->mount("app",     new ApplicationModule());
$controller->mount("package", new PackageModule());
$controller->mount("mirror",  new MirrorModule());
$controller->mount("auth",    new UserAccountModule());

$controller->dispatch();

?>
