<?
/**
 * @author Rico Huijbers
 */

/**
 * Helper class for formatting all kinds of units
 */
class Format {

    public static function timestamp($thing) {
        if (is_integer($thing)) return $thing;
        return strtotime($thing);
    }

    public static function text_block($text) {
        return nl2br(htmlspecialchars($text));
    }

    /**
     * Format a currency
     */
    public static function currency($number, $targetOrCurrency="html", $dashSymbol="--", $groupDigits=true) {
        switch ($targetOrCurrency) {
            case "html":
                $symbol      = "&euro;";
                $dash        = "&#8211;";
                $minus       = $dash;
                $groupDigits = true;
                break;
            case "latex":
                $symbol      = "\euro";
                $dash        = "--";
                $minus       = $dash;
                $groupDigits = true;
                break;
            case "ascii":
                $symbol      = "EUR";
                $dash        = "--";
                $minus       = "-";
                $groupDigits = true;
                break;

            default:
                $symbol = $targetOrCurrency;
                $dash   = $dashSymbol;
                $minus  = "-";
                break;
        }

        $neg = ($number < 0);
        if ($neg) $number = abs($number);

        $whole = (int)$number;

        if ($whole == $number) return sprintf("%s%s %s,%s", $neg ? $minus : "", $symbol, number_format($whole, 0, ",", "."), $dash);
        else return sprintf("%s%s %s", $neg ? $minus  : "", $symbol, number_format($number, 2, ",", "."));
    }

    public static function asciiCurrency($number) {
        return self::currency($number, "ascii");
    }

    /**
     * Format a date
     */
    public static function date($value, $nice=true, $informal=true) {
        $value = self::timestamp($value);

        if ($informal) {
            // Try to recognize the date
            $day = strtotime("today",  $value);

            if ($day == strtotime("today")) return "today";
            if ($day == strtotime("today - 1 day")) return "yesterday";
            if ($day == strtotime("today + 1 day")) return "tomorrow";
        }

        setlocale(LC_TIME, "nl_NL");
        $formatted = strftime($nice ? "%d %b %Y" : "%d-%m-%Y", $value);
        if ($nice) $formatted = ltrim($formatted, "0"); // Windows won't support %e, and anyway padding with spaces is actually very ugly
        return $formatted;
    }

    /**
     * Format a date for inclusion in a paper document
     */
    public static function formalDate($value) {
        return self::date($value, false, false);
    }

    /**
     * Format a date for inclusion in a permanent record
     */
    public static function permDate($value) {
        return self::date($value, true, false);
    }

    /**
     * Format a date for display in a live system
     */
    public static function liveDate($value) {
        return self::date($value, true, true);
    }

    /**
     * Format a datetime
     */
    public static function dateTime($value, $nice=true, $informal=true) {
        $value = self::timestamp($value);

        $now = time();
        if ($value < $now && $informal) {
            // Try to recognize the time
            if ($value + 60 > $now) return "just now";
            if ($value + 120 > $now) return "1 minute ago";
            if ($value + 3600 > $now) return floor(($now - $value)/60) . " minutes ago";
        }

        return self::date($value, $nice, $informal) . " " . strftime("%H:%M", $value);
    }

    /**
     * Format a datetime for inclusion in a paper document
     */
    public static function formalDateTime($value) {
        return self::dateTime($value, false, false);
    }

    /**
     * Format a datetime for inclusion in a permanent record
     */
    public static function permDateTime($value) {
        return self::dateTime($value, true, false);
    }

    /**
     * Format a datetime for display in a live system
     */
    public static function liveDateTime($value) {
        return self::dateTime($value, true, true);
    }

    public static function bytes($bytes) {
        if ($bytes > 1024 * 1024 * 1024) return sprintf("%dG", $bytes / (1024 * 1024 * 1024));
        if ($bytes > 1024 * 1024) return sprintf("%dM", $bytes / (1024 * 1024));
        if ($bytes > 1024) return sprintf("%dk", $bytes / 1024);
        return $bytes;
    }

    /**
     * Returns a proper describing suffix for a number of things
     *
     * Returns either "1 thing" or "x things".
     */
    public static function qty($number, $thing="thing", $suffix="s") {
        if (is_array($number)) $number = count($number);
        if ($number == 1) return "1 $thing";
        if (strlen($suffix) > 2) return "$number $suffix";
        return "$number $thing$suffix";
    }

    private static function make_query_parts($args, $array_name="") {
        $qstrings = array();
        foreach ($args as $name => $value) {
            $fullname = $array_name ? "{$array_name}[{$name}]" : $name;

            if (is_array($value))
                $qstrings = array_merge($qstrings, self::make_query_parts($value, $fullname));
            else
                $qstrings[] = sprintf("%s=%s", urlencode($fullname), urlencode($value));
        }
        return $qstrings;
    }

    public static function href($args, $file="index.php") {
        // Filter empty args, to make the URL look nicer
        $args = array_filter($args, create_function('$x', 'return ($x !== "") || is_array($x) || (substr($x, 0, 4) == "_qf_");'));

        $fmtted = self::make_query_parts($args);

        // This should really -really- be &amp;, otherwise the pages we generate can't be parsed
        // by the DOM processor. If for some reason this doesn't work in a browser, we have to
        // look because it might be the doctype or the encoding that's wrong.
        return $file . (count($fmtted) ? "?" . implode("&amp;", $fmtted) : "");
    }

    /**
     * Return a list in human-readable format
     *
     * Takes a comma-separated string, or an array, and combines them with
     * comma and space, and replaces the last separator with the separation
     * word "and".
     */
    public static function collection($lst, $and="and") {
        if (!is_array($lst)) $lst = split(",", $lst);
        $lst = array_map("trim", $lst);

        if (count($lst) == 0) return "";
        if (count($lst) == 1) return $lst[0];
        $fist = array_slice($lst, 0, -1);

        return join(", ", $fist) . " $and " . $lst[count($lst) - 1];
    }
}

?>


