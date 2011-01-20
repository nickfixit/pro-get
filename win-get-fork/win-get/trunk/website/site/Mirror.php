<?
/**
 * @author Rico Huijbers
 */

require_once "Versioned.php";

class Mirror extends Versioned {
    protected $mirrorid;
    protected $packageid;
    protected $url;
    protected $trusted;

    public function __construct() {
        $this->r_alive = 1;
    }

    public function get_package() {
        return Mapper::Get("Package")->retrieve($this->packageid);
    }
}

class MirrorMapper extends VersionedMapper {
    public static function Get() {
        return Mapper::Get("Mirror");
    }

    public function all_latest($packageid="") {
        if ($packageid) {
            $q = Query::N()
                ->restrict("mirror.r_rev = (SELECT MAX(m.r_rev) FROM mirror m WHERE m.mirrorid = mirror.mirrorid)")
                ->restrict("packageid = %s", $packageid)
                ->order("mirror.r_alive", "DESC");
        }
        else {
            $q = Query::N("mirror JOIN package USING (packageid) JOIN application USING (applicationid)")
                ->restrict("application.r_rev = (SELECT MAX(a.r_rev) FROM application a WHERE a.applicationid = application.applicationid)")
                ->restrict("package.r_rev = (SELECT MAX(p.r_rev) FROM package p WHERE p.packageid = package.packageid)")
                ->order("application.aid", "ASC")
                ->order("mirror.r_alive", "DESC");
        }

        return $this->load_all($q);
    }

    public function retrieve($id) {
        $q = Query::N();
        $q->restrict("mirrorid = %s", $id);

        return $this->load($q);
    }

    public function all_revisions($id) {
        $q = Query::N()
            ->restrict("mirrorid = %s", $id)
            ->order("r_rev", "DESC");
        return $this->load_all($q);
    }
}

?>
