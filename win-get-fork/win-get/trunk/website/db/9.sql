CREATE TABLE user_account(
    userid INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(40),
    display_name VARCHAR(100) NOT NULL UNIQUE,
    open_id VARCHAR(200) UNIQUE,
    class VARCHAR(40),
    registered DATETIME,
    reset_nonce VARCHAR(40),
    last_login DATETIME
    ) Engine=InnoDB;
