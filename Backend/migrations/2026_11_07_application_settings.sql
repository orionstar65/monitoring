-- Optional operational settings table. Existing values are intentionally not
-- overwritten: safe defaults remain in Backend/config/AppSettings.php.
CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(128) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  updated_by INT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key),
  KEY idx_app_settings_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the initial values. ON DUPLICATE KEY does not reset Super Admin edits.
INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('interval.screenshot.seconds','60'),
('interval.heartbeat.seconds','300'),
('interval.browser.seconds','300'),
('interval.keyboard.seconds','300'),
('interval.usb.seconds','300'),
('interval.config_refresh.seconds','300'),
('feature.screenshot.enabled','1'),
('feature.browser_history.enabled','1'),
('feature.keyboard.enabled','1'),
('feature.usb.enabled','1'),
('feature.debug_logging.enabled','0'),
('feature.auto_update.enabled','0'),
('server.max_retry_attempts','3'),
('server.connection_timeout.seconds','10'),
('server.request_timeout.seconds','30'),
('server.chunk_size','1000'),
('server.compression.enabled','1'),
('logging.api_requests.enabled','1'),
('retention.default_months','3');
-- Platform override keys are created only when configured. Omitting one
-- keeps its client interval equal to the global interval.
