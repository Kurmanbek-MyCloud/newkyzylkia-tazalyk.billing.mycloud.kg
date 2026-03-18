CREATE TABLE IF NOT EXISTS vtiger_pending_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    record_module VARCHAR(50) NOT NULL DEFAULT 'Estates',
    user_id INT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    is_modal_dismissed TINYINT(1) DEFAULT 0,
    is_resolved TINYINT(1) DEFAULT 0,
    created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_time DATETIME NULL,
    INDEX idx_user_type_resolved (user_id, notification_type, is_resolved),
    INDEX idx_record (record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
