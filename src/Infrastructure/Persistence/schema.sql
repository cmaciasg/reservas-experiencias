CREATE TABLE IF NOT EXISTS experience (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    provider_id VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS session (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    experience_id VARCHAR(36) NOT NULL,
    start_date DATETIME NOT NULL,
    session_date DATE GENERATED ALWAYS AS (DATE(start_date)) STORED,
    capacity INT UNSIGNED NOT NULL,
    available_seats INT UNSIGNED NOT NULL,
    price_cents INT UNSIGNED NOT NULL,
    CONSTRAINT fk_session_experience FOREIGN KEY (experience_id) REFERENCES experience (id),
    UNIQUE KEY uniq_experience_session_date (experience_id, session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking (
    id VARCHAR(36) NOT NULL PRIMARY KEY,
    session_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    seats INT UNSIGNED NOT NULL,
    total_price_cents INT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_booking_session FOREIGN KEY (session_id) REFERENCES session (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
