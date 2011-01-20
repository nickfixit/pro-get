<?
error_reporting(E_ALL);

require_once "global.php";

$mirror      = new VersionedTable(new MysqlTable("mirror", "packageid"));
$package     = new VersionedTable(new MysqlTable("package", "applicationid"));
$application = new VersionedTable(new MysqlTable("application"));

$hist = new MysqlList("history", Query::N("history")->columns("r_rev_at", "r_rev", "message")->order("r_rev_at", "DESC")->limit(50), "blah");

$scaffold = new Scaffold($application, $hist);

?>
<html>
    <head>
    <title>win-get quick &amp; dirty catalog editor</title>
    </head>
    <body>
<?
    $scaffold->display();
?>
    </body>
</html>
