<?

require_once "lib/functional.php";

/**
 * Divides a collection into 2 columns, if it is large enough
 */
function make_columns($collection, $min_length=20) {
    if (count($collection) < $min_length) return array($collection);

    $l1 = ceil(count($collection) / 2);

    return array(array_slice($collection, 0, $l1), array_slice($collection, $l1));
}

/**
 * Partition a list into two lists, based on a predicate
 */
function partition($collection, $predicate) {
    return array(
        array_filter($collection, $predicate),
        array_filter($collection, notf($predicate)));
}

function fmt_text($input) {
    return htmlspecialchars($input);
}

function fmt_date($input) {
    if (!is_numeric($input)) $input = strtotime($input);
    return date("d M Y, H:i", $input);
}

?>
