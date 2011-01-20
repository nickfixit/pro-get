<?
/**
 * @author Rico Huijbers
 */

require_once "Versioned.php";

class Package extends Versioned {
    protected $packageid;
    protected $applicationid;
    protected $version;
    protected $language;
    protected $filename;
    protected $silent;
    protected $size;
    protected $md5sum;
    protected $installer_in_zip;
    protected $trusted;

    public function get_application() {
        return Mapper::Get("Application")->retrieve($this->applicationid);
    }

    public function set_language($language) {
        /* We need to use _ instead of - to separate language & region: en_US instead of en-US */
        $language = str_replace("-", "_", $language);
        $parts = explode("_", $language);

        if (count($parts) == 1) {
            $this->language = strtolower($parts[0]);
        }
        else {
            $this->language = strtolower($parts[0]) . "_" . strtoupper($parts[1]);
        }
    }

    public function get_display_name() {
        return implode("-", array_filter(array($this->application->aid, $this->version, $this->language && $this->language != "*" ? $this->language : "")));
    }

    public static function languages() {
        return array(
            "*"     => "Multiple languages",
            ""      => "Not applicable",
            "en"    => "English",
            "en_US" => "English (US)",
            "en_GB" => "English (GB)",
            "nl"    => "Nederlands",
            "nl_NL" => "Nederlands (Nederland)",
            "nl_BE" => "Nederlands (België)",
            "de"    => "Deutsch",
            "fr"    => "François",
            );
    }
}

class PackageMapper extends VersionedMapper {
    public static function Get() {
        return Mapper::Get("Package");
    }

    public function all_latest($applicationid="") {
        if ($applicationid) {
            $q = Query::N()
                ->restrict("package.r_rev = (SELECT MAX(p.r_rev) FROM package p WHERE p.packageid = package.packageid)")
                ->restrict("applicationid = %s", $applicationid)
                ->order("package.r_alive", "DESC");
        }
        else {
            $q = Query::N("application JOIN package USING (applicationid)")
                ->restrict("application.r_rev = (SELECT MAX(a.r_rev) FROM application a WHERE a.applicationid = application.applicationid)")
                ->restrict("package.r_rev = (SELECT MAX(p.r_rev) FROM package p WHERE p.packageid = package.packageid)")
                ->order("application.aid", "ASC")
                ->order("package.r_alive", "DESC")
                ->order("version", "DESC");
        }

        return $this->load_all($q);
    }

    public function retrieve($id, $rev=null) {
        $q = Query::N();

        $q->restrict("packageid = %s", $id);
        if ($rev)
            $q->restrict(Query::equals(array("r_rev" => $rev)));
        else
            $q->restrict("r_rev = (SELECT MAX(r_rev) FROM package p WHERE p.packageid = package.packageid)");

        return $this->load($q);
    }

    public function all_revisions($id) {
        $q = Query::N()
            ->restrict("packageid = %s", $id)
            ->order("r_rev", "DESC");
        return $this->load_all($q);
    }
}

?>
