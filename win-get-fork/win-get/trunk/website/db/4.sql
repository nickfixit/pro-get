ALTER TABLE application 
    MODIFY COLUMN applicationid INT UNSIGNED, /* No more auto-increment */
    DROP PRIMARY KEY,
    DROP INDEX aid,
    ADD COLUMN r_rev INT UNSIGNED DEFAULT 1,
    ADD COLUMN r_rev_by VARCHAR(50),
    ADD COLUMN r_rev_at DATETIME, 
    ADD COLUMN r_note VARCHAR(100),
    ADD COLUMN r_alive TINYINT DEFAULT 1,
    ADD PRIMARY KEY(applicationid, r_rev);


ALTER TABLE package 
    MODIFY COLUMN packageid INT UNSIGNED,
    DROP PRIMARY KEY,
    DROP INDEX filename,
    ADD COLUMN r_rev INT UNSIGNED DEFAULT 1,
    ADD COLUMN r_rev_by VARCHAR(50),
    ADD COLUMN r_rev_at DATETIME, 
    ADD COLUMN r_note VARCHAR(100),
    ADD COLUMN r_alive TINYINT DEFAULT 1,
    ADD PRIMARY KEY(packageid, r_rev);

ALTER TABLE mirror 
    MODIFY COLUMN mirrorid INT UNSIGNED,
    DROP PRIMARY KEY,
    ADD COLUMN r_rev INT UNSIGNED DEFAULT 1,
    ADD COLUMN r_rev_by VARCHAR(50),
    ADD COLUMN r_rev_at DATETIME, 
    ADD COLUMN r_note VARCHAR(100),
    ADD COLUMN r_alive TINYINT DEFAULT 1,
    ADD PRIMARY KEY(mirrorid, r_rev);

-- Insert into this table to get a new unique id
CREATE TABLE application_seq(seq_no INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);
INSERT INTO application_seq(seq_no) SELECT DISTINCT applicationid FROM application ORDER BY applicationid ASC;
CREATE TABLE package_seq(seq_no INT UNSIGNED AUTO_INCREMENT PRIMARY KEY);
INSERT INTO package_seq(seq_no) SELECT DISTINCT packageid FROM package ORDER BY packageid ASC;

-- Appropriate indexes will be added later on
