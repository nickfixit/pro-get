<? $controller->title($title) ?>

<!--begin content-->

<? $section = "before_form"; if (file_exists("view/decorate.{$controller->active_module}.php")) include "view/decorate.{$controller->active_module}.php"; ?>

<blockquote>
<?= $form->to_html() ?>
</blockquote>

<? $section = "after_form"; if (file_exists("view/decorate.{$controller->active_module}.php")) include "view/decorate.{$controller->active_module}.php"; ?>

<!--end content-->
