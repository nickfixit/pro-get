CREATE TABLE application(
    applicationid INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    aid VARCHAR(100) UNIQUE, 
    purpose VARCHAR(200),
    description TEXT,
    website VARCHAR(200)
    );

CREATE TABLE package(
    packageid INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    applicationid INT UNSIGNED,
    pid VARCHAR(200) UNIQUE, 
    version VARCHAR(20) DEFAULT '0.1',
    language VARCHAR(10) DEFAULT '*',
    filename VARCHAR(100) UNIQUE,
    silent VARCHAR(100),
    size INT,
    md5sum VARCHAR(32),
    installer_in_zip VARCHAR(100)
    );

CREATE TABLE mirror(
    mirrorid INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    packageid INT UNSIGNED,
    url VARCHAR(250)
    );
