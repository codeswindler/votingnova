-- Seed initial system settings
USE votingnova;

-- Insert default OTP setting (disabled by default)
INSERT INTO system_settings (setting_key, setting_value, description)
VALUES ('otp_enabled', '0', 'Enable/Disable OTP for user login')
ON DUPLICATE KEY UPDATE setting_value = '0';
