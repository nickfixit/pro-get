<?
/**
 * Functional programming routines
 *
 * @author Rico Huijbers <rix0rrr@gmail.com>
 * @copyright (C) 2008 Rico Huijbers
 */

function notf($function) {
    if (!class_exists("notf_class")) {
        class notf_class {
            private $fn;
            public function __construct($fn) {
                $this->fn = $fn;
            }
            public function call() {
                $a = func_get_args();
                return !call_user_func_array($this->fn, $a);
            }
        }
    }

    $clos = new notf_class($function);
    return array($clos, "call");
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

function method($object, $method) {
    $a = func_get_args();
    $meth = array_slice($a, 0, 2);
    $args = array_slice($a, 2);
    return call_user_func_array($meth, $args);
}

function field($object, $field) {
    return $object->$field;
}

?>
