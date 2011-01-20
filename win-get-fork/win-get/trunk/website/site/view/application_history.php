<? $controller->title("$app->aid history"); ?>

<? include "command_box.php"; ?>

<!--begin content-->

<table>
    <tr>
        <th>Revision</th>
        <th>Identifier</th>
        <th>Purpose</th>
        <th>Description</th>
        <th>Website</th>
        <th>At</th>
    </tr>
    <? foreach (array_reverse($revisions) as $revision): ?>
    <tr <?= !$revision->r_alive ? 'class="deleted"' : "" ?>>
        <td><?= fmt_text($revision->r_rev) ?></td>
        <td><?= fmt_text($revision->aid) ?></td>
        <td><?= fmt_text($revision->purpose) ?></td>
        <td><?= fmt_text($revision->description) ?></td>
        <td><?= fmt_text($revision->website) ?></td>
        <td><?= fmt_date($revision->r_rev_at) ?></td>
    </tr>

    <? endforeach ?>
</table>

<!--end content-->
