DROP VIEW IF EXISTS application_history; -- Don't need these anymore
DROP VIEW IF EXISTS package_history; 

-- Add columns for `trusted' applications. Very important to keep malicious users at bay.
ALTER TABLE package ADD COLUMN trusted TINYINT DEFAULT 0 AFTER installer_in_zip;
ALTER TABLE mirror ADD COLUMN trusted TINYINT DEFAULT 0 AFTER url;
