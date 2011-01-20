<? $controller->title("Mirrors") ?>

<? if (count($active_mirrors)): ?>
<? foreach (make_columns($active_mirrors) as $column): ?>
    <div class="column" style="float: left; width: 30em;">
    <? foreach ($column as $mirror): ?>

    <div class="list-entry <?= $mirror->trusted ? "trusted" : "" ?>">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $mirror->mirrorid)) ?>"><?= htmlspecialchars($mirror->url) ?></a></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? endif ?>
<div class="clear"></div>

<? if (count($deleted_mirrors)): ?>
<h3>Deleted mirrors</h3>

<? foreach (make_columns($deleted_mirrors) as $column): ?>
    <div class="column" style="float: left; width: 30em;">
    <? foreach ($column as $mirror): ?>

    <div class="list-entry <?= $mirror->trusted ? "trusted" : "" ?>">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $mirror->mirrorid)) ?>" class="deleted"><?= htmlspecialchars($mirror->url) ?></a></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? endif ?>
<div class="clear"></div>
