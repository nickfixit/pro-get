<? if ($controller->active_action == "register" && $section == "before_form"): ?>
<h3>Register new account</h3>

<p>If you register an account:</p>

<ul>
    <li>You won't need to enter the captcha every time you suggest a change to the catalog.</li>
    <li>You can apply to become a package maintainer.</li>
</ul>

<p>You don't need an account just to use win-get. Also, forum accounts are separate
from site accounts. If you want to post on the forum, you must register a separate account
over there.</p>

<? endif ?>
<? if ($controller->active_action == "register" && $section == "after_form"): ?>
<p>On this site, we use your e-mail address to identify you. Don't worry
though, we don't display it where anyone can see it (you can select a
<strong>display name</strong> for that), and we won't send you spam. You also
won't have to confirm your e-mail address, because SourceForge doesn't currently
allow us to send mail from their website.</p>
<? endif ?>
