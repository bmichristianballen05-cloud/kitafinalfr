-- KITA social/messaging tables
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS friend_requests (
  request_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  from_user_id INT UNSIGNED NOT NULL,
  to_user_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  UNIQUE KEY uq_friend_req_pair (from_user_id, to_user_id),
  KEY idx_friend_req_to_status (to_user_id, status),
  KEY idx_friend_req_from_status (from_user_id, status),
  CONSTRAINT fk_friend_req_from FOREIGN KEY (from_user_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_req_to FOREIGN KEY (to_user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friendships (
  friendship_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_one_id INT UNSIGNED NOT NULL,
  user_two_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (friendship_id),
  UNIQUE KEY uq_friendship_pair (user_one_id, user_two_id),
  KEY idx_friendship_user_two (user_two_id),
  CONSTRAINT fk_friendships_one FOREIGN KEY (user_one_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_friendships_two FOREIGN KEY (user_two_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_threads (
  thread_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (thread_id),
  KEY idx_chat_threads_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_thread_members (
  thread_id INT UNSIGNED NOT NULL,
  user_id INT NOT NULL,
  last_read_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (thread_id, user_id),
  KEY idx_ctm_user (user_id),
  CONSTRAINT fk_ctm_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (thread_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
  message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id INT UNSIGNED NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id),
  KEY idx_chat_messages_thread (thread_id, created_at),
  KEY idx_chat_messages_sender (sender_id),
  CONSTRAINT fk_chat_msg_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (thread_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(255) NOT NULL,
  data_json TEXT NULL,
  external_key VARCHAR(120) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  UNIQUE KEY uq_notifications_external (user_id, external_key),
  KEY idx_notifications_user_created (user_id, created_at),
  KEY idx_notifications_user_read (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
