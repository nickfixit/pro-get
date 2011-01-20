<? $controller->title("$package->display_name history") ?>

<? include "command_box.php"; ?>

<!--begin content-->

<table>
    <tr>
        <th>Revision</th>
        <th>Package</th>
        <th>Filename</th>
        <th>Silent args</th>
        <th>At</th>
    </tr>
    <? foreach (array_reverse($revisions) as $revision): ?>
    <tr <?= !$revision->r_alive ? 'class="deleted"' : "" ?>>
        <td><?= fmt_text($revision->r_rev) ?></td>
        <td><?= fmt_text($revision->display_name) ?></td>
        <td><?= fmt_text($revision->filename) ?></td>
        <td><?= fmt_text($revision->silent) ?></td>
        <td><?= fmt_date($revision->r_rev_at) ?></td>
    </tr>

    <? endforeach ?>
</table>

<!--end content-->
