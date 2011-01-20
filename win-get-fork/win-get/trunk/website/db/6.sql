-- Complete history
CREATE OR REPLACE VIEW history AS
(SELECT CONCAT('a', applicationid, r_rev) AS id, 'application' AS src, applicationid, NULL as packageid, NULL AS mirrorid, r_rev, r_rev_at, CAST(CONCAT_WS(' ', aid, LOWER(r_note), 'by', r_rev_by) AS CHAR) AS message
FROM application
WHERE r_rev_at IS NOT NULL)
UNION
(SELECT CONCAT('p', packageid, r_rev) AS id, 'package', applicationid, packageid, NULL, r_rev, r_rev_at, CONCAT_WS(' ', CONCAT_WS('-', 
    (SELECT aid FROM application WHERE application.applicationid = package.applicationid AND application.r_rev_at < package.r_rev_at ORDER BY application.r_rev_at DESC LIMIT 1)
    , version, language), LOWER(r_note), 'by', r_rev_by) AS message
FROM package 
WHERE r_rev_at IS NOT NULL)
UNION
(SELECT CONCAT('m', mirrorid, r_rev) AS id, 'mirror', NULL, packageid, mirrorid, r_rev, r_rev_at, CONCAT_WS(' ', url, LOWER(r_note), 'by', r_rev_by) AS message
FROM mirror
WHERE r_rev_at IS NOT NULL
ORDER BY r_rev_at DESC);
