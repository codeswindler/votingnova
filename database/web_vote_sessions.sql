-- Web vote sessions: stores pending web vote context for M-Pesa callback
-- Same flow as USSD: callback looks up by checkout_request_id to create vote and send SMS
USE votingnova;

CREATE TABLE IF NOT EXISTS web_vote_sessions (
    checkout_request_id VARCHAR(100) PRIMARY KEY,
    nominee_id INT NOT NULL,
    votes_count INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone (phone),
    INDEX idx_created (created_at),
    FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
