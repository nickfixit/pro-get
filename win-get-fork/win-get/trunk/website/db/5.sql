-- Application history
CREATE OR REPLACE VIEW application_history AS
SELECT applicationid, r_rev, CONCAT_WS(' ', aid, LOWER(r_note), 'by', r_rev_by, 'at', r_rev_at) AS message
FROM application
WHERE r_rev_at IS NOT NULL
ORDER BY r_rev_at DESC;

-- Package history
CREATE OR REPLACE VIEW package_history AS
SELECT packageid, r_rev, applicationid, CONCAT_WS(' ', CONCAT_WS('-', version, language), LOWER(r_note), 'by', r_rev_by, 'at', r_rev_at) AS message
FROM package
WHERE r_rev_at IS NOT NULL
ORDER BY r_rev_at DESC;

-- Mirror history
CREATE OR REPLACE VIEW mirror_history AS
SELECT mirrorid, r_rev, packageid, CONCAT_WS(' ', url, LOWER(r_note), 'by', r_rev_by, 'at', r_rev_at) AS message
FROM mirror
WHERE r_rev_at IS NOT NULL
ORDER BY r_rev_at DESC;

-- Add indexes to speed up the view
ALTER TABLE application ADD INDEX(r_rev_at);
ALTER TABLE package ADD INDEX(r_rev_at);
ALTER TABLE mirror ADD INDEX(r_rev_at);
