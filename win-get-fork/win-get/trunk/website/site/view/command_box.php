<?

if (!function_exists("single_command")) {
function single_command($controller, $action, $caption) {
    if (!$controller->action_exists(".$action")) return;

    if ($controller->this_action() != $action)
        printf('<a class="command-button" href="%s">%s</a>', $controller->make_url(".$action", array("id" => $controller->get("id"))), $caption);
    else
        printf('<div class="command-button">%s</div>', $caption);
}
}

?>
<div class="command-box">
<div class="back-button"><a href="<?= $controller->make_url(".list") ?>">Back to list</a></div>
<? single_command($controller, "view",    "View") ?>
<? single_command($controller, "edit",    "Edit") ?>
<? single_command($controller, "history", "History") ?>
</div>
