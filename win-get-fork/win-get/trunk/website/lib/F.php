<?
/*
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright (C) 2008 Rico Huijbers
 */

/**
 * Functional programming routines
 *
 * In a class so they can be autoloaded.
 */
class F {
    public static function partial() {
        //assume $0 is funcname, $1-$x is partial values
        $args = func_get_args();   
        $func = $args[0];
        $p = new F_partial($func, array_slice($args,1));
        return array($p, 'method');
    }

    public static function method($object, $method) {
        $a = func_get_args();
        $meth = array_slice($a, 0, 2);
        $args = array_slice($a, 2);
        return call_user_func_array($meth, $args);
    }

    public static function method_fn($method) {
        return F::partial(array("F", "method"), $method);
    }

    public static function field($object, $field) {
        return $object->$field;
    }

    public static function field_fn($field) {
        return F::partial(array("F", "field"), $field);
    }

    /**
     * Returns the function with the given name on this class (for example for
     * use with a mapping function or somesuch).
     */
    public static function M($member) {
        return array("F", $member);
    }

    public static function is($object, $class) {
        return $object instanceof $class;
    }

    /**
     * Returns a function that will compare its argument to $x
     */
    public static function equals($x) {
        return F::partial(array("D", "eq"), $x);
    }

    public static function all($list, $predicate) {
        foreach ($list as $element)
            if (!call_user_func($predicate, $element)) return false;
        return true;
    }

    public static function any($list, $predicate) {
        foreach ($list as $element)
            if (call_user_func($predicate, $element)) return true;
        return false;
    }

    /**
     * Returns g o f
     *
     * Following the rule: (g o f)(x) == g(f(x))
     */
    public static function compose($g, $f) {
        $a = func_get_args();
        $c = new F_composition($a);
        return array($c, "method");
    }
}

class F_partial {
    var $values = array();
    var $func;

    function __construct($func, $args) {
        $this->values = $args;
        $this->func = $func;
    }

    function method() {
        $args = func_get_args();
        return call_user_func_array($this->func, array_merge($args, $this->values));
    }
}

class F_composition {
    var $callables = array();

    function __construct($callables) {
        $this->callables = $callables;
    }

    function method() {
        $args = func_get_args();

        foreach (array_reverse($this->callables) as $fn)
            $args = array(call_user_func_array($fn, $args));

        return $args[0];
    }
}

?>
