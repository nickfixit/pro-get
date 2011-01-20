<? $controller->title($package->display_name) ?>

<? include "command_box.php"; ?>

<!--begin content-->

<table>
    <tr>
        <th colspan="2"><?= htmlspecialchars($package->display_name) ?> <?= !$package->r_alive ? "(deleted)" : "" ?></th>
    </tr>
    <tr>
        <td>Filename</td>
        <td><?= htmlspecialchars($package->filename) ?></td>
    </tr>
    <tr>
        <td>Silent arguments</td>
        <td><tt><?= htmlspecialchars($package->silent) ?></tt></td>
    </tr>
    <tr>
        <td>Size</td>
        <td><?= htmlspecialchars($package->size) ?></td>
    </tr>
    <tr>
        <td>MD5 checksum</td>
        <td><tt><?= htmlspecialchars($package->md5sum) ?></tt></td>
    </tr>
    <tr>
        <td>Installer in ZIP</td>
        <td><tt><?= htmlspecialchars($package->installer_in_zip) ?></tt></td>
    </tr>
</table>

<? $mirrors->dispatch(); ?>

<!--end content-->
