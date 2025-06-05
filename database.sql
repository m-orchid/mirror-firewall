CREATE TABLE firewall_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri TEXT,
    request_time DATETIME
);

CREATE TABLE firewall_bans (
    ip_address VARCHAR(45) PRIMARY KEY,
    banned_until DATETIME
);

CREATE TABLE firewall_admin (
    id INT PRIMARY KEY,
    mirror_mode BOOLEAN DEFAULT 0,
    ban_duration INT DEFAULT 300
);

INSERT INTO firewall_admin (id, mirror_mode, ban_duration) VALUES (1, 0, 300);
