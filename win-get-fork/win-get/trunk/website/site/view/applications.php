<? $controller->title("Applications") ?>

<div class="searchform" style="clear: both; margin: 1em 0em;">
    <form method="GET" action="<?= $controller->getform_url() ?>">
        <?= $controller->getform_hidden() ?>
        <input type="text" name="q" size="50" value="<?= htmlspecialchars(implode(" ", $search_words)) ?>"> <input type="submit" value="Search">
    </form>
</div>

<? if (count($active_apps)): ?>
<? foreach (make_columns($active_apps) as $column): ?>
    <div class="column" style="float: left; width: 25em; margin-right: 30px;">
    <? foreach ($column as $app): ?>

    <div class="list-entry">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $app->applicationid)) ?>"><?= htmlspecialchars($app->aid) ?></a></div>
        <div class="purpose"><?= htmlspecialchars($app->purpose) ?></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? else: ?>
<p>No applications found<?= count($search_words) ? " matching '<strong>" . implode(" ", $search_words) . "</strong>'": "" ?>.</p>
<? endif ?>
<div class="clear"></div>

<? if (count($deleted_apps)): ?>
<h3>Deleted applications</h3>

<div class="explanation"><p>This list contains applications that were added at
one time, but have since been deleted by a user. They are not really deleted
though, and can be revived at any time if you feel the deletion is unjust.
</p></div>

<? foreach (make_columns($deleted_apps) as $column): ?>
    <div class="column" style="float: left; width: 25em;">
    <? foreach ($column as $app): ?>

    <div class="list-entry">
        <div class="name"><a href="<?= $controller->make_url(".view", array("id" => $app->applicationid)) ?>" class="deleted"><?= htmlspecialchars($app->aid) ?></a></div>
        <div class="purpose"><?= htmlspecialchars($app->purpose) ?></div>
    </div>

    <? endforeach ?>
    </div>
<? endforeach ?>
<? endif ?>

<div class="clear"></div>
