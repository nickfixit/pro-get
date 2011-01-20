<? $controller->title("Packages") ?>

<? if (count($active_packages)): ?>
<? foreach (make_columns($active_packages) as $column): ?>
    <div class="column" style="float: left; width: 30em;">
    <? foreach ($column as $package): ?>

    <div class="list-entry <?= $package->trusted ? "trusted" : "" ?>">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $package->packageid)) ?>"><?= htmlspecialchars($package->display_name) ?></a></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? endif ?>
<div class="clear"></div>

<? if (count($deleted_packages)): ?>
<h3>Deleted packages</h3>

<? foreach (make_columns($deleted_packages) as $column): ?>
    <div class="column" style="float: left; width: 30em;">
    <? foreach ($column as $package): ?>

    <div class="list-entry <?= $package->trusted ? "trusted" : "" ?>">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $package->packageid)) ?>" class="deleted"><?= htmlspecialchars($package->display_name) ?></a></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? endif ?>
<div class="clear"></div>
