<? $controller->title($app->aid); ?>

<? include "command_box.php"; ?>

<!--begin content-->

<div><?= htmlspecialchars($app->aid) ?> <?= !$app->r_alive ? "(deleted)" : "" ?></div>
<div><?= htmlspecialchars($app->purpose) ?></div>
<div><?= nl2br(htmlspecialchars($app->description)) ?></div>
<? if ($app->website): ?>
<div><a href="<?= htmlspecialchars($app->website) ?>" target="_blank"><?= htmlspecialchars($app->website) ?></a></div>
<? endif ?>

<? if (count($maint = $app->maintainers)): ?>
<div>This application is maintained by <?= Format::collection(array_map(partial("method", "profile_page_link", $controller), $maint)) ?>.</div>
<? else: ?>
<div>Nobody is maintaining this application right now.</div>
<? endif ?>

<? if (!$app->is_maintainer(UserAccount::current())): /* Only if the current user is not maintainer of this app */?>
<? if (UserAccount::logged_in()): ?>
<div>If you're interested in becoming a maintainer of this application, you can <a href="<?= $controller->make_url("application.apply", $app) ?>">apply here</a>.</div>
<? else: ?>
<div>If you were <a href="<?= $controller->make_url("auth.login") ?>">logged in</a>, you could become maintainer of this application.</div>
<? endif ?>
<? endif ?>

<? $packages->dispatch(); ?>

<!--end content-->
