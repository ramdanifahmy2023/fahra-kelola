CREATE TABLE IF NOT EXISTS account_workspace (
 user_id INT NOT NULL,
 scope VARCHAR(32) NOT NULL,
 payload TEXT NOT NULL,
 updated_at DATETIME NOT NULL,
 client_updated_at BIGINT NOT NULL DEFAULT 0,
 PRIMARY KEY (user_id,scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
