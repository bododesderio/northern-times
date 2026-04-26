-- Fix admin user: set real bcrypt password and proper email
-- Default credentials: admin@northerntimes.local / Admin@NT2026!
-- CHANGE THIS PASSWORD IMMEDIATELY after first login!

UPDATE users
SET password_hash = '$2y$10$IFWGj2tbOdhdWZPbYpP8h.YFxMGBwu0m50gpiR9hL3sAyjBHfL5B.',
    display_name = 'Administrator'
WHERE email = 'admin@northerntimes.local' AND password_hash = 'REPLACE_ME';
