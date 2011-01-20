<?
/**
 * A collection of form-related classes
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright 2008 Rico Huijbers
 */

class RapidForm {
    private static $element_types = array();

    private $name;
    private $action_url;
    private $elements      = array(); // "Known" elements
    private $displays      = array(); // Rendered elements
    private $labels        = array();
    private $required      = array();
    private $hidden_values = array();
    private $postback      = array();
    private $required_note = "required field";
    private $attributes    = array();

    private $global_validators = array();

    private $post_values    = array();
    private $default_values = array();
    
    private $tags = array();

    private $anonymous_counter = 0;

    public function __construct($name, $action_url='', $attributes=array()) {
        $this->name = $name;
        $this->action_url = $action_url;
        $this->attributes = $attributes;

        $this->post_values = $_POST;
        if (get_magic_quotes_gpc()) $this->post_values = $this->strip_r($this->post_values);

        $this->register_default_types();

        $this->postback = array($this, "default_postback");
    }

    public function set_attribute($attribute, $value) {
        $this->attributes[$attribute] = $value;
    }

    public function required_note($note) {
        $this->required_note = $note;
        return $this;
    }

    /**
     * Check if the form is submitted
     * 
     * A form is submitted if:
     *
     * - All elements that always produce a value in the form have been submitted.
     * - If there are no such elements, if the submit button has been pressed.
     */
    public function is_submitted() {
        if (!count($this->elements)) throw new Exception("Empty form!");

        $submit       = null;
        $at_least_one = false;

        $always = array_filter($this->elements, F::method_fn("always_submitted"));
        if (count($always)) return F::all($always, F::method_fn("is_submitted"));

        $subs = array_filter($this->elements, F::partial(array("F", "is"), "RapidSubmitButton"));
        return F::any($subs, F::method_fn("is_submitted"));
    }

    public function tag($key, $value) {
        $this->tags[$key] = $value;
        return $this;
    }

    public function has_tag($key) {
        return isset($this->tags[$key]);
    }

    public function get_tag($key, $default="") {
        if (!$this->has_tag($key)) return $default;
        return $this->tags[$key];
    }

    public function strip_r($arg) {
        if (is_array($arg)) return array_map(array($this, "strip_r"), $arg);
        return stripslashes($arg);
    }

    public static function register_element($type, $class) {
        self::$element_types[$type] = $class;
    }

    protected function register_default_types() {
        self::register_element("text",      "RapidTextElement");
        self::register_element("textarea",  "RapidTextAreaElement");
        self::register_element("password",  "RapidPasswordElement");
        self::register_element("dropdown",  "RapidSelectElement");
        self::register_element("select",    "RapidSelectElement");
        self::register_element("submit",    "RapidSubmitButton");
        self::register_element("requiredsubmit", "RapidRequiredSubmitButton");
        self::register_element("checkbox",  "RapidCheckBox");
        self::register_element("flag",      "RapidCheckBox");
        self::register_element("static",    "RapidHtml");
        self::register_element("html",      "RapidHtml");
        self::register_element("header",    "RapidHeader");
        self::register_element("recaptcha", "RapidReCaptcha");
        self::register_element("captcha",   "RapidReCaptcha");
        self::register_element("checklist", "RapidCheckListElement");
        self::register_element("file",      "RapidFileUpload");
    }

    /**
     * Create an element of the given type
     */
    public static function create_element($type, $element_args=array()) {
        $k = null;
        if (isset(self::$element_types[$type])) $k = self::$element_types[$type];
        if (is_null($k) && class_exists($type)) $k = $type;
        if (is_null($k)) throw new Exception("Unknown form element type: $type");

        $constructor_args = func_get_args();
        $constructor_args = array_slice($constructor_args, 1);

        if (is_callable($k)) return call_user_func_array($k, $constructor_args);

        // Otherwise class instantiation
        $klass = new ReflectionClass($k); 
        return $klass->newInstanceArgs($constructor_args); 
    }

    private function get_el_name($thing) {
        if ($thing->get_name()) return $thing->get_name();
        $name =  "el" . ($this->anonymous_counter++);
        $thing->set_name($name);
        return $name;
    }

    public function add($el) {
        $a  = func_get_args();
        $el = call_user_func_array(array($this, "register"), $a);

        $this->displayed[] = $el;

        return $el;
    }

    public function register($el) {
        if (!$el instanceof RapidElement) {
            $args = func_get_args();
            $el = call_user_func_array(array($this, "create_element"), $args);
            if (!($el instanceof RapidElement)) throw new Exception("Unexpected output of create_element");
        }

        $name = $this->get_el_name($el);
        if ($name) $this->elements[$name] = $el;
        $el->set_form($this);

        return $el;
    }

    public function get($name) {
        if (!isset($this->elements[$name])) throw new Exception("No such element: $name");
        return $this->elements[$name];
    }

    public function get_value($name) {
        return $this->get($name)->get_value();
    }

    public function get_all() {
        return $this->get_all_values();
    }

    public function get_all_values() {
        $r = array();
        foreach ($this->elements as $name=>$element)
            $r[$name] = $element->get_value();
        return $r;
    }

    public function set_hidden($name, $value) {
        $this->hidden_values[$name] = $value;
    }

    public function to_html() {
        $r = array();
        $any_req = false;

        $attributes = $this->attributes;
        $attributes["method"] = "post";
        $attributes["action"] = $this->action_url ? $this->action_url : $_SERVER["REQUEST_URI"];

        $r[] = sprintf('<form %s><table>', $this->encode_attributes($attributes));

        foreach ($this->hidden_values as $name=>$value)
            $r[] = sprintf('<input type="hidden" name="%s" value="%s">', htmlspecialchars($name), htmlspecialchars($value));

        foreach ($this->displayed as $element) {
            $r[] = $element->embedded_html();
            if ($element->required) $any_req = true;
        }
        $r[] = '</table>';
        if ($any_req && $this->required_note) $r[] = sprintf('<p class="form-notes"><sup class="required-star">*</sup> %s</p>', htmlspecialchars($this->required_note));
        $r[] = '</form>';

        return implode("", $r);
    }

    public function set_postback($postback) {
        $this->postback = $postback;
    }

    public function default_postback($form) {
        echo "The form was submitted but I forgot to do something with it. Remember to reload!";
        die;
    }

    public function global_validator($callback) {
        $this->global_validators[] = $callback;
    }

    public function set_defaults($defaults) {
        $this->default_values = array_merge($this->default_values, $defaults);
        return $this;
    }

    /**
     * Signal that you're done setting up the form, and that it can be
     * processed now.
     */
    public function done($postback=null, $extra_args=null) {
        if (!is_null($postback)) $this->postback = $postback;

        // Copy data from all the default values
        foreach ($this->elements as $name=>$element) 
            $this->elements[$name]->set_value(isset($this->default_values[$name]) ? $this->default_values[$name] : "");

        // Copy data from all the post values
        foreach ($this->elements as $name=>$element) 
            if (isset($this->post_values[$name]))
                $this->elements[$name]->set_post_value($this->post_values[$name]);

        // If not submitted, then don't continue processing
        if (!$this->is_submitted()) return $this;

        // Validate
        $ok = $this->validate();
        if (!$ok) return $this;

        // Submitted and validated, so call postback
        try {
            $extra_args = func_get_args();
            $extra_args = array_slice($extra_args, 1);
            call_user_func_array($this->postback, array_merge(array($this), $extra_args));

            // If we got here, redirect back to ourselves to prevent double-post
            Session::redirect($_SERVER["REQUEST_URI"]);
        }
        catch (Exception $e) {
            // If we have an exception, add an error message and show the current page again
            Session::add_message("x " . $e->getMessage());
            return $this;
        }
    }

    private function do_global_validation() {
        $values = $this->get_all_values();

        $ok = true;
        foreach ($this->global_validators as $callback) {
            $r = call_user_func($callback, $values);
            if (is_array($r) && count($r)) {
                $ok = false;
                foreach ($r as $name=>$message)
                    $this->get($name)->error($message);
            }
        }
        return $ok;
    }

    public function validate() {
        $ok = true;
        foreach ($this->elements as $name=>$element) {
            $ok &= $element->is_valid();
        }

        $ok &= $this->do_global_validation();

        return $ok;
    }

    public function render_required() {
        if (!$this->required_note) return "";
        return '<sup class="required-star">*</sup>';
    }

    public function encode_attributes($assoc) {
        $r = array();
        foreach ($assoc as $key=>$value)
            $r[] = sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
        return implode(" ", $r);
    }
}

class RapidElement {
    protected $value      = "";
    protected $name;
    protected $label;
    public $required   = "";
    protected $error      = "";
    protected $filters    = array();
    protected $validators = null;
    protected $attributes = array();
    public $form;
    protected $set = false;

    public function __construct($name, $attributes=array()) {
        $this->set_name($name);
        $this->attributes = $attributes;

        $this->validators = new RapidValidators($this);
    }

    public function set_form($form) {
        $this->form = $form;
    }

    public function filter($callback) {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * This is needed because of Firefox -- it will do normalization if we don't, and then we
     * don't know about it anymore.
     */
    protected function normalize_name($name) {
        $name = strtolower($name);
        $name = preg_replace("/[^a-z0-9]+/", "_", $name);
        return $name;
    }

    /**
     * Gain access to the underlying validators object
     * 
     * The public members of the validators object will return the element
     * again, so you can chain calls.
     */
    public function validate() {
        return $this->validators;
    }

    public function always_submitted() {
        return true;
    }

    public function required($message="This value is required") {
        $this->required = $message;
        return $this;
    }

    public function label($label, $nowrap=false) {
        $this->label = $label;

        if ($nowrap) $this->label = sprintf('<span style="white-space: nowrap;">%s</span>', $this->label);

        return $this;
    }

    public function set_name($name) { $this->name = $this->normalize_name($name); }
    public function get_name() { return $this->name; }

    public function get_value() {
        return $this->value;
    }

    public function set_value($val) {
        // Run though all filters and then set
        foreach ($this->filters as $filter) {
            if (!is_callable($filter)) throw new Exception("Uncallable filter: $filter");
            $val = call_user_func($filter, $val);
        }

        $this->value = $val;
    }

    public function error($error) {
        if ($this->error == "") $this->error = $error;
        return $this;
    }

    public function is_valid() {
        // No value but required is an error
        if ($this->required && !$this->get_value()) {
            $this->error($this->required);
            return false;
        }

        // No value, no validation
        if (!$this->get_value()) return true;

        return $this->validators->validate($this->get_value());
    }

    /**
     * Decode the posted value into an actual value and set it.
     * Depends on rendering of element.
     */
    public function set_post_value($val) {
        $this->set = true;
        $this->set_value($val);
    }

    public function is_submitted() {
        return $this->set;
    }

    public function render_error() {
        if (!$this->error) return "";
        return sprintf('<div style="color: red; font-weight: bold;" class="form-error">%s</div>', htmlspecialchars($this->error));
    }

    public function render_required() {
        if (!$this->required) return "";
        return $this->form->render_required();
    }

    public function embedded_html() {
        return sprintf('<tr%s><td><strong>%s</strong>%s</td><td>%s</td></tr>'."\r\n", $this->error ? ' class="contains-error"' : '', $this->label, $this->render_required(), $this->to_html());
    }

    public function to_html() {
        return $this->render_error() . $this->render_control();
    }

    public function render_control() {
        return '<span color="red">Control rendering not implemented yet</span>';
    }

    public function encode_attributes($assoc) {
        $r = array();
        foreach ($assoc as $key=>$value)
            $r[] = sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
        return implode(" ", $r);
    }

    public function __toString() {
        return sprintf("(%s value:%s, submitted:%s)", get_class($this), $this->get_value(), $this->is_submitted() ? "yes" : "no");
    }
}

class RapidTextElement extends RapidElement {
    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "text";
        $attributes["name"]  = $this->name;
        $attributes["value"] = $this->value;

        return sprintf('<input %s />', $this->encode_attributes($attributes));
    }
}

class RapidTextAreaElement extends RapidElement {
    public function render_control() {
        $attributes = $this->attributes;
        $attributes["name"]  = $this->name;

        return sprintf('<textarea %s>%s</textarea>', $this->encode_attributes($attributes), htmlspecialchars($this->value));
    }
}

class RapidPasswordElement extends RapidElement {
    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "password";
        $attributes["name"]  = $this->name;
        $attributes["value"] = $this->value;

        return sprintf('<input %s />', $this->encode_attributes($attributes));
    }
}

class RapidOptionsElement extends RapidElement {
    protected $options;

    public function __construct($name, $options, $attributes=array()) {
        parent::__construct($name, $attributes);

        if (!($options instanceof RapidOptions)) $options = new RapidOptions($options);

        $this->options = $options;
    }
}

class RapidSelectElement extends RapidOptionsElement {
    public function render_control() {
        $attributes = $this->attributes;
        $attributes["name"]  = $this->name;

        return sprintf('<select %s>%s</select>', $this->encode_attributes($attributes), $this->render_options());
    }

    private $r;

    protected function render_options() {
        $this->r = array();
        $this->options->render_options(array($this, "option"), array($this, "begin_group"), array($this, "end_group"));
        return implode("\r\n", $this->r);
    }

    public function option($value, $caption) {
        $selected = $this->get_value() == $value ? ' selected="selected"' : ""; /* Form required for XHTML */

        if ($value == $caption)
            $this->r[] = sprintf('<option%s>%s</option>', $selected, htmlspecialchars($caption));
        else
            $this->r[] = sprintf('<option value="%s"%s>%s</option>', htmlspecialchars($value), $selected, htmlspecialchars($caption));
    }

    public function begin_group($group_name) {
        $this->r[] = sprintf('<optgroup label="%s">', htmlspecialchars($group_name));
    }

    public function end_group() {
        $this->r[] = sprintf('</optgroup>');
    }
}

class RapidCheckListElement extends RapidOptionsElement {
    protected $cb_ctr = 0;

    public function __construct($name, $options, $attributes=array()) {
        parent::__construct($name, $options, $attributes);
        $this->value = array();
    }

    public function render_control() {
        $r = array();

        foreach ($this->options->get_list() as $value=>$caption) {
            $r[] = $this->render_checkbox($value, $caption);
        }
        return implode("<br />\r\n", $r);
    }

    protected function render_checkbox($value, $caption) {
        $attributes = $this->attributes;
        $attributes["type"]  = "checkbox";
        $attributes["name"]  = $this->name . "[]";
        $attributes["value"] = $value; 
        $attributes["id"]    = $this->name . "_cb" . $this->cb_ctr++;

        $checkbox = sprintf('<input %s%s />', $this->encode_attributes($attributes), in_array($value, $this->value) ? ' checked="checked"' : "");
        $caption  = sprintf('<label for="%s">%s</label>', htmlspecialchars($attributes["id"]), htmlspecialchars($caption));

        return $checkbox . $caption;
    }

    public function set_value($val) {
        if (!is_array($val)) {
            if ($val)
                $val = array($val);
            else
                $val = array();
        }
            
        parent::set_value($val);
    }

    public function always_submitted() {
        return false;
    }
}

class RapidSubmitButton extends RapidElement {
    private $caption;

    public function __construct($name, $attributes=array()) {
        $this->caption = $name; /* Name will be normalized, so we need a copy */
        parent::__construct($name, $attributes);
    }

    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "submit";
        $attributes["name"]  = $this->name;
        $attributes["value"] = $this->caption;

        return sprintf('<input %s />', $this->encode_attributes($attributes));
    }

    public function always_submitted() {
        return false;
    }
}

/**
 * Prevents the form from being submitted if this button is not hit
 */
class RapidRequiredSubmitButton extends RapidElement {
    private $caption;

    public function __construct($name, $attributes=array()) {
        $this->caption = $name; /* Name will be normalized, so we need a copy */
        parent::__construct($name, $attributes);
    }

    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "submit";
        $attributes["name"]  = $this->name;
        $attributes["value"] = $this->caption;

        return sprintf('<input %s />', $this->encode_attributes($attributes));
    }

    public function always_submitted() {
        return true;
    }
}

class RapidCheckBox extends RapidElement {
    protected $caption;

    public function __construct($name, $caption, $attributes=array()) {
        parent::__construct($name, $attributes);
        $this->caption = $caption;
    }

    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "checkbox";
        $attributes["name"]  = $this->name;
        $attributes["value"] = "1"; 
        $attributes["id"]    = "cb_" . $this->name;

        $checkbox = sprintf('<input %s%s />', $this->encode_attributes($attributes), $this->value ? ' checked="checked"' : "");
        $caption  = $this->caption ? sprintf('<label for="%s">%s</label>', htmlspecialchars($attributes["id"]), htmlspecialchars($this->caption)) : "";

        return $checkbox . $caption;
    }

    public function always_submitted() {
        return false;
    }
}

class RapidHtml extends RapidElement {
    protected $html;

    public function __construct($html) {
        parent::__construct("", array());
        $this->html = $html;
    }

    public function render_control() {
        return $this->html;
    }

    public function always_submitted() {
        return false;
    }
}

class RapidHeader extends RapidElement {
    protected $text;

    public function __construct($text) {
        parent::__construct("", array());
        $this->text = $text;
    }

    public function always_submitted() {
        return false;
    }

    public function embedded_html() {
        return sprintf('<tr><th colspan="2">%s</th></tr>'."\r\n", htmlspecialchars($this->text));
    }
}

class RapidReCaptcha extends RapidElement {
    protected $private_key;
    protected $public_key;
    protected $fail_message;

    public function __construct($private_key=null, $public_key=null, $fail_message="Please confirm that you are a human and not a bot by filling out the forms below.") {
        if (is_null($private_key)) if (isset($GLOBALS["recaptcha_private"])) $private_key = $GLOBALS["recaptcha_private"]; else throw new Exception("Give a private key for recaptcha or set the 'recaptcha_private' global.");
        if (is_null($public_key))  if (isset($GLOBALS["recaptcha_public"])) $public_key = $GLOBALS["recaptcha_public"]; else throw new Exception("Give a public key for recaptcha or set the 'recaptcha_public' global.");

        $this->private_key  = $private_key;
        $this->public_key   = $public_key;
        $this->fail_message = $fail_message;

        require_once dirname(__FILE__) . "/recaptchalib.php";

        parent::__construct("recaptcha_response_field", array());
    }

    public function render_control() {
        return recaptcha_get_html($this->public_key);
    }

    public function is_valid() {
        // Captcha is always required
        $resp = recaptcha_check_answer($this->private_key,
                                       $_SERVER["REMOTE_ADDR"],
                                       $_POST["recaptcha_challenge_field"],
                                       $_POST["recaptcha_response_field"]);

        if (!$resp->is_valid) {
            $this->error($this->fail_message);
            return false;
        }

        return true;
    }
}

class RapidFileUpload extends RapidElement {
    private $max_size;
    private $language;

    public function __construct($name, $max_size=null, $language="en", $attributes=array()) {
        $this->max_size = $max_size ? $max_size : ini_get("upload_max_filesize");
        $this->language = $language;
        
        parent::__construct($name, $attributes);
    }
    
    public function set_form($form) {
        $this->form = $form;
        $form->set_attribute("enctype", "multipart/form-data"); // Change encoding required for uploads
    }
     
    public function is_submitted() {
        return isset($_FILES[$this->name]);
    }

    /**
     * get_value() returns the uploaded file's name.
     */
    public function get_value() {
        if (!$this->is_submitted()) return false;
        return $_FILES[$this->name]["name"];
    }

    /**
     * get_file_info() returns the array with the following files:
     *
     * - name: Filename
     * - tmp_name: Local temporary name
     * - size: Size of uploaded file
     * - error: Any error encountered
     * - type: MIME type of uploaded file
     *
     * @param _
     * @return _
     */
    public function get_file_info() {
        if (!$this->is_submitted()) return false;
        return $_FILES[$this->name];
    }

    public function get_contents() {
        $info = $this->get_file_info();
        return file_get_contents($info["tmp_name"]);
    }

    /**
     * Save the uploaded file somewhere
     */
    public function save($path) {
        $info = $this->get_file_info();
        move_uploaded_file($info["tmp_name"], $path);
    }

    public function render_control() {
        $attributes = $this->attributes;
        $attributes["type"]  = "file";
        $attributes["name"]  = $this->name;
        $attributes["value"] = $this->value;

        $max_size_control = $this->max_size ? sprintf('<input type="hidden" name="MAX_FILE_SIZE" value="%d" />', $this->max_size) : "";

        return $max_size_control . sprintf('<input %s />', $this->encode_attributes($attributes));
    }

    public function is_valid() {
        if ($_FILES[$this->name]["error"] && $_FILES[$this->name]["error"] != UPLOAD_ERR_NO_FILE) { /* "No upload" error will be checked later (or not) */
            $this->error($this->translate_upload_error($_FILES[$this->name]["error"]));
            return false;
        }

        return parent::is_valid();
    }

    public function translate_upload_error($code) {
        if ($this->language == "nl") {
            switch ($code) {
                case UPLOAD_ERR_INI_SIZE: 
                case UPLOAD_ERR_FORM_SIZE:
                    return "Het bestand dat u gekozen heeft is te groot. Het bestand mag maximaal {$this->max_size} bytes groot zijn.";
                case UPLOAD_ERR_PARTIAL:
                    return "Het bestand is maar gedeeltelijk geupload. Mogelijk is er iets mis met uw internetverbinding of uw browser.";
                case UPLOAD_ERR_NO_FILE:
                    return "U heeft geen bestand geupload.";
                case UPLOAD_ERR_NO_FILE:
                    return "De server is momenteel niet juist ingesteld om uploads te accepteren. Er ontbreekt een tijdelijke map.";
                case UPLOAD_ERR_CANT_WRITE:
                    return "De server is momenteel niet juist ingesteld om uploads te accepteren. De web server kan niet naar de schijf schrijven.";
                case UPLOAD_ERR_EXTENSION:
                    return "Een extensie heeft de upload gestopt.";
                default:
                    return "Er is een fout opgetreden die ik niet herken: $code";
            }
        }

        switch ($code) {
            case UPLOAD_ERR_INI_SIZE: 
            case UPLOAD_ERR_FORM_SIZE:
                return "The file you selected is too big to be accepted. Maximum size accepted: {$this->max_size} bytes.";
            case UPLOAD_ERR_PARTIAL:
                return "The file was only partially uploaded. Something may be wrong with your connection or your browser.";
            case UPLOAD_ERR_NO_FILE:
                return "You didn't upload a file.";
            case UPLOAD_ERR_NO_FILE:
                return "The server is not correctly configured to accept file uploads right now. A temporary folder is missing.";
            case UPLOAD_ERR_CANT_WRITE:
                return "The server is not correctly configured to accept file uploads right now. The web server cannot write to the disk.";
            case UPLOAD_ERR_EXTENSION:
                return "An extension stopped the file upload.";
            default:
                return "An error that I do not recognize occurred: $code";
        }
    }
}

/**
 * Validators object
 *
 * A validators object is owned by every control.
 */
class RapidValidators {
    protected $element;
    protected $validators = array();

    public function __construct($element) {
        $this->element = $element;
    }

    public function validate($value) {
        foreach ($this->validators as $v) {
            list($message, $validator) = $v;

            $r = call_user_func($validator, $value);
            if (!$r) {
                $this->element->error($message);
                return false;
            }
        }

        return true;
    }

    public function email($message) {
        $this->validators[] = array($message, array($this, "validate_email"));
        return $this->element;
    }

    private function validate_email($email) {
        return preg_match("/^[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[A-Z]{2}|com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum)$/i", $email, $matches);
    }

    public function custom($callback, $message) {
        $this->validators[] = array($message, $callback);
        return $this->element;
    }

    public function must_match($other_field, $message) {
        $this->validators[] = array($message, partial(array($this, "validate_match"), $other_field));
        return $this->element;
    }

    /* Must be public because of partial() call */
    public function validate_match($value, $other_field) { 
        $other = $this->element->form->get($other_field);
        return $value == $other->get_value();
    }
}

/**
 * Rapid option source
 *
 * Can translate any collection of thing (usually objects)
 * into an option list for use in a SELECT control or checklist.
 * 
 * If group_fn is given, it is a function that receives all items,
 * and should return the name of the group the item should belong to.
 */
class RapidOptions {
    protected $options;
    protected $prompt;
    protected $group_fn;

    public function __construct($options, $prompt="", $group_fn=null) {
        $this->options  = $options;
        $this->prompt   = $prompt;
        $this->group_fn = $group_fn;
    }

    protected function get_value($thing) {
        if (is_object($thing)) {
            if (is_callable(array($thing, "get_id"))) {
                $id = $thing->get_id();
                if (is_array($id)) return implode(",", $id);
                return $id;
            }
        }

        return (string)$thing;
    }

    protected function get_caption($thing) {
        if (is_object($thing)) {
        }

        return (string)$thing;
    }

    public function make_groups($opt_list, $preserve_keys) {
        if (!$this->group_fn) return array("" => $opt_list); /* Everything in the "anonymous" group */

        $grouped = array();
        foreach ($opt_list as $key=>$opt) {
            $group = call_user_func($this->group_fn, $opt);
            if (!isset($grouped[$group])) $grouped[$group] = array();

            if ($preserve_keys)
                $grouped[$group][$key] = $opt;
            else
                $grouped[$group][] = $opt;
        }

        return $grouped;
    }

    /**
     * Returns an id=>caption list of all the contained elements
     */
    public function get_list() {
        if (!is_array($this->options)) throw new Exception("Options is supposed to be an array: {$this->options}");

        $keys    = array_keys($this->options);
        $is_list = ($keys[0] == 0 && $keys[count($keys) - 1] == count($keys) - 1);

        $r = array();
        if ($this->prompt) $r[""] = $this->prompt;

        foreach ($this->options as $value=>$thing) {
            if ($is_list)
                $r[$this->get_value($thing)] = $this->get_caption($thing);
            else
                $r[$value] = $this->get_caption($thing);
        }

        return $r;
    }

    /**
     * Render options with callbacks for rendering an item, beginning a group
     * and ending a group
     */
    public function render_options($option_fn, $group_fn_b, $group_fn_e) {
        if (!is_array($this->options)) throw new Exception("Options is supposed to be an array: {$this->options}");

        $keys    = array_keys($this->options);
        $is_list = ($keys[0] == 0 && $keys[count($keys) - 1] == count($keys) - 1);

        $grouped = $this->make_groups($this->options, !$is_list);

        // Render the grouped options
        if ($this->prompt) call_user_func($option_fn, "", $this->prompt);

        foreach ($grouped as $group_name=>$group_elements) {
            if ($group_name) call_user_func($group_fn_b, $group_name);

            foreach ($group_elements as $value=>$thing) {
                if ($is_list)
                    call_user_func($option_fn, $this->get_value($thing), $this->get_caption($thing));
                else
                    call_user_func($option_fn, $value, $this->get_caption($thing));
            }

            if ($group_name) call_user_func($group_fn_e, $group_name);
        }
    }
}

if (!function_exists("partial")) {
    function partial() {
        if(!class_exists('partial')) {
            class partial{
                var $values = array();
                var $func;

                function partial($func, $args) {
                    $this->values = $args;
                    $this->func = $func;
                }

                function method() {
                    $args = func_get_args();
                    return call_user_func_array($this->func, array_merge($args, $this->values));
                }
            }
        }
        //assume $0 is funcname, $1-$x is partial values
        $args = func_get_args();   
        $func = $args[0];
        $p = new partial($func, array_slice($args,1));
        return array($p, 'method');
    }
}

?>
