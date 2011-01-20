<?
/**
 * Database update script
 *
 * @package win-get website
 * @author Rico Huijbers
 */

require_once "../config.php";

mysql_query("CREATE TABLE IF NOT EXISTS _meta(`key`  VARCHAR(50) PRIMARY KEY, value TEXT)") or die(mysql_error());

$current_version = 0;
$rs = mysql_query("SELECT value FROM _meta WHERE `key` = 'version' LIMIT 1");
while ($row = mysql_fetch_array($rs)) $current_version = $row[0];

function set_version($v) {
    global $current_version;
    $r = mysql_query(sprintf("UPDATE _meta SET value = '%d' WHERE `key` = 'version'", $v));
    if (!mysql_affected_rows()) mysql_query(sprintf("INSERT INTO _meta(`key`, value) VALUES('version', '%d')", $v));
    $current_version = $v;
}

$update_version = 1;
while (file_exists($update_version . ".sql")) $update_version++;
$update_version--;


?>
<html>
    <head>
        <title>Database update script</title>
    </head>
    <body>
        <h1>Database update script</h1>
<?
/* Do update */
if (isset($_POST["doupdate"])) {
    printf('<table>');
    $v = $current_version;
    while ($v < $update_version) {
        $v++;
        printf('<tr><td>%d</td><td>', $v);
        $sql = file_get_contents($v . ".sql");

        $queries = explode(";", $sql);
        $ok = true;

        foreach ($queries as $query) {
            if (!trim($query)) continue;

            $res = mysql_query($query);
            if (!$res) {
                printf(mysql_error());
                $ok = false;
                break;
            }
            else 
                printf("ok. ");
        }
        printf('</td></tr>');

        if ($ok) set_version($v);
        else break;
    }
    printf('</table>');
}
?>

<?
printf("Current database version: <b>%d</b>.<br />", $current_version);

if ($update_version > $current_version) {
    printf("<b>%d</b> update(s) available.<br />", $update_version - $current_version);
    printf('<form method="POST"><input type="hidden" name="doupdate" value="1"><input type="submit" value="Update now"></form>');
}
else printf("No updates available.");
?>
    </body>
</html>
