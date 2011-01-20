-- Create a "maintainers" table
CREATE TABLE maintainer (
    applicationid INT UNSIGNED NOT NULL,
    userid INT UNSIGNED NOT NULL,
    approved TINYINT NOT NULL,
    reason TEXT,
    PRIMARY KEY (applicationid, userid)
    );

-- Add a website, description column to the user account. Nice for a "profile" page.
ALTER TABLE user_account
    ADD COLUMN website VARCHAR(100) AFTER last_login,
    ADD COLUMN profile TEXT AFTER website;
