<?
/**
 * Script to dump the current contents of the database to a downloadable
 * catalog file
 *
 * @package win-get
 * @author Rico Huijbers
 */

require_once "config.php";

function esc($what) {
    $what = "'" . str_replace("'", "''", $what) . "'";

    return $what;
}

function fail($error) {
    // Send a HTTP error code to prevent clients from downloading
    header("500 Something went wrong");
    echo $error;
    die;
}

// First, were did we get this catalog from? Required for update
$output = sprintf("INSERT INTO sources(url) VALUES('%s');\n\n", "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);

// Application
$app_rev = "application.r_rev = (SELECT MAX(r_rev) FROM application sub WHERE application.applicationid = sub.applicationid) AND application.r_alive";
$rs = mysql_query("SELECT * FROM application WHERE $app_rev ORDER BY aid ASC") or fail(mysql_error());
while ($row = mysql_fetch_assoc($rs)) {
    $output .= sprintf("INSERT INTO application(aid, purpose, description, website) VALUES(%s, %s, %s, %s);\n", 
        esc($row["aid"]),
        esc($row["purpose"]),
        esc($row["description"]),
        esc($row["website"]));
}

$output .= "\n";

$pidexpr = "CONCAT_WS('-', application.aid, package.version, IF(package.language = '*' OR package.language = '', NULL, package.language))";

// Package
$pack_rev = "package.r_rev = (SELECT MAX(r_rev) FROM package sub WHERE package.packageid = sub.packageid) AND package.r_alive";
$rs = mysql_query("SELECT application.aid, $pidexpr AS pid, package.* FROM package JOIN application USING (applicationid) WHERE $app_rev AND $pack_rev ORDER BY aid ASC") or fail(mysql_error());
while ($row = mysql_fetch_assoc($rs)) {
    $output .= sprintf("INSERT INTO package(aid, pid, version, language, filename, size, silent, md5sum, installer_in_zip) VALUES(%s, %s, %s, %s, %s, %s, %s, %s, %s);\n", 
        esc($row["aid"]),
        esc($row["pid"]),
        esc($row["version"]),
        $row["language"] ? esc($row["language"]) : "'*'", /* Escape because the client can't handle empty languages yet (0.1+) */
        esc($row["filename"]),
        esc($row["size"]),
        esc($row["silent"]),
        esc($row["md5sum"]),
        esc($row["installer_in_zip"])
        );
}

$output .= "\n";

// Mirror
$mirr_rev = "mirror.r_rev = (SELECT MAX(r_rev) FROM mirror sub WHERE mirror.mirrorid = sub.mirrorid) AND mirror.r_alive";
$rs = mysql_query("SELECT $pidexpr AS pid, mirror.* FROM mirror JOIN package USING (packageid) JOIN application USING (applicationid) WHERE $app_rev AND $pack_rev AND $mirr_rev ORDER BY aid ASC, url ASC") or fail(mysql_error());
while ($row = mysql_fetch_assoc($rs)) {
    $output .= sprintf("INSERT INTO mirror(pid, url) VALUES(%s, %s);\n", 
        esc($row["pid"]),
        esc($row["url"]));
}

echo utf8_encode($output);

?>
