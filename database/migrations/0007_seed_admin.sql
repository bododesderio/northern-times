-- Default admin: admin@northerntimes.local / admin123 (change on first login)
INSERT INTO users (username, email, password_hash, role)
VALUES ('admin', 'admin@northerntimes.local', '$2y$10$wI1DVdVgYS3pSyFmxkzxuOCtfDI7Mhani8CyAU7XZV9TdO32hI36i', 'super_admin')
ON CONFLICT (email) DO NOTHING;

INSERT INTO categories (name, slug, description, sort_order)
VALUES
  ('Top Stories', 'top-stories', 'Lead stories and editor picks', 1),
  ('Northern Uganda', 'northern-uganda', 'Regional reporting and features', 2)
ON CONFLICT (slug) DO NOTHING;