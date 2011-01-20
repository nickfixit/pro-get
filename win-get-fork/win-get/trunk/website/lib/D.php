<?
/**
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright (C) 2008 Rico Huijbers
 */

/**
 * Data manipulation functions
 * 
 * In a class so they can be autoloaded.
 */
class D {

    /**
     * Group a collection by a field.
     *
     * Returns an associative array, where the keys are group values and all
     * the elements are the elements for the original array that return that
     * group value.
     */
    public static function group($collection, $group_fn) {
        $r = array();

        foreach ($collection as $element) {
            $group = call_user_func($group_fn, $element);

            if (!isset($r[$group])) $r[$group] = array();
            $r[$group][] = $element;
        }

        return $r;
    }


    /**
     * Transform any function call into a singleton
     *
     * Calls the function if this is the first time, or returns the originally
     * returned value if it's not.
     */
    private static $once_cache = array();
    public static function once($fn) {
        $a   = func_get_args();
        $rep = print_r($a, true); /* Too dirrrty to clean your act up! :o */

        if (!isset(self::$once_cache[$rep])) {
            $fn = array_shift($a);           
            self::$once_cache[$rep] = call_user_func_array($fn, $a);
        }

        return self::$once_cache[$rep];
    }

    /**
     * Class-extensible equality comparison
     */
    public static function eq($a, $b) {
        if (is_object($a) && is_callable(array($a, "__equals"))) return call_user_func(array($a, "__equals"), $b);
        if (is_object($b) && is_callable(array($b, "__equals"))) return call_user_func(array($b, "__equals"), $a);
        return $a == $b;
    }

    /**
     * Containment with user-defined equality
     */
    public static function in($el, $arr) {
        foreach ($arr as $x)
            if (D::eq($el, $x)) return true;
        return false;
    }
}

?>
