<?
/**
 * @author Rico Huijbers
 */

require_once "Versioned.php";

class Application extends Versioned {
    protected $applicationid;
    protected $aid;
    protected $purpose;
    protected $description;
    protected $website;

    /**
     * Format an aid
     * 
     * Make it lowercase, replace non-alphanumeric characters with dashes.
     */
    public static function format_aid($aid) {
        $aid = strtolower($aid);
        $aid = preg_replace("|[^a-z0-9+.]+|", "-", $aid);
        return $aid;
    }

    public function details_page_href($controller) {
        return $controller->make_url("application.view", array("id" => $this->applicationid));
    }

    public function details_page_link($controller) {
        return sprintf('<a href="%s">%s</a>', $this->details_page_href($controller), htmlspecialchars($this->aid));
    }

    public function get_maintainers() {
        return Mapper::Get("UserAccount")->all_maintainers($this->id);
    }

    public function is_maintainer($user) {
        if (!$user) return false;
        if (!($user instanceof UserAccount)) return false;
        return in_array($user, $this->maintainers);
    }
}

class ApplicationMapper extends VersionedMapper {
    public static function Get() {
        return Mapper::Get("Application");
    }

    public function all_maintained($userid, $approved=true) {
        $q = Query::N("application NATURAL JOIN maintainer")
            ->restrict("userid = %s", $userid)
            ->restrict($approved ? "approved" : "NOT approved")
            ->order("aid");
        return $this->load_all($q);
    }

    public function all_latest($search_words=array()) {
        $q = Query::N()
            ->restrict("r_rev = (SELECT MAX(r_rev) FROM application a WHERE a.applicationid = application.applicationid)")
            ->order("r_alive", "DESC")
            ->order("aid", "ASC");
        foreach ($search_words as $word) 
            $q->restrictions[] = sprintf("aid LIKE '%%%s%%' OR purpose LIKE '%%%s%%' OR description LIKE '%%%s%%'", mysql_real_escape_string($word), mysql_real_escape_string($word), mysql_real_escape_string($word));
        return $this->load_all($q);
    }

    public function retrieve($id, $rev=null) {
        $q = Query::N();

        $q->restrict("applicationid = %s", $id);
        if ($rev)
            $q->restrict(Query::equals(array("r_rev" => $rev)));
        else
            $q->restrict("r_rev = (SELECT MAX(r_rev) FROM application a WHERE a.applicationid = application.applicationid)");

        return $this->load($q);
    }

    public function all_revisions($id) {
        $q = Query::N()
            ->restrict("applicationid = %s", $id)
            ->order("r_rev", "DESC");
        return $this->load_all($q);
    }

}

?>
