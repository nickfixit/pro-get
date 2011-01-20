<? 
if (is_dir("sf.net-frame")) $prefix = "sf.net-frame"; else $prefix = "."; 
$controller->head->css("{$prefix}/layout.css");
$controller->head->css("{$prefix}/winget.css");

$controller->head->css("../res/appeditor.css", __FILE__);

$controller->head->script_literal('var RecaptchaOptions = { theme : "white" };');
?>
<div id="maindiv">
    <div id="menudiv">
        <div id="logodiv"></div>

        <? $caption = "Home"; $href="#"; include "{$prefix}/menuitem.php"; ?>
        <? $caption = "Applications"; $href="catalog.php"; include "{$prefix}/menuitem.php"; ?>
        <? $caption = "Download"; $href="#"; include "{$prefix}/menuitem.php"; ?>
        <? $caption = "Tips &amp; Tricks"; $href="#"; include "{$prefix}/menuitem.php"; ?>
        <? $caption = "Forum"; $href="#"; include "{$prefix}/menuitem.php"; ?>
        <? $caption = "Contribute"; $href="#"; include "{$prefix}/menuitem.php"; ?>

        <div class="separator"></div>
    </div>
    <div id="contentdiv">
        <h1><?= $controller->head->title ?></h1>        
        
        <!--messages here-->

        <? include "login_box.php" ?>

        <!--content here-->
        
        <!-- Masthead stuff -->
        <div id="copyrightdiv">
            Win-get is licensed under the GNU General Public License<br> 
            Win-get and this website are copyright &copy; 2008 by Rico Huijbers<br>
            This website uses icons by Bruno Maia, <a href="http://www.icontexto.com">IconTexto</a><br>
            If you want to contact me, you can leave a note on <a
            href="https://sourceforge.net/tracker/?group_id=157786">this
            project's SourceForge support request tracker</a>, or the <a href="/forum">forum</a>.
        </div>

    </div>
    <div style="clear: both;"><!-- Make the containing block element stretch to below the floats. --></div>
</div>
