<?
/**
 * @author Rico Huijbers
 */

/**
 * Local permissions class
 *
 * This class encodes the rules for "who can do what".
 */
class Perms {

    public static function can_trust($application) {
        if (!UserAccount::logged_in()) return false;
        return UserAccount::current()->class == "admin";
    }
}

?>
