-- Default admin (password will be replaced later via a CLI utility)
-- For now, set password_hash to a placeholder. We'll update it in the next step with PHP's password_hash().
INSERT INTO users (username, email, password_hash, role)
VALUES ('admin', 'admin@northerntimes.local', 'REPLACE_ME', 'super_admin')
ON CONFLICT (email) DO NOTHING;

INSERT INTO categories (name, slug, description, sort_order)
VALUES
  ('Top Stories', 'top-stories', 'Lead stories and editor picks', 1),
  ('Northern Uganda', 'northern-uganda', 'Regional reporting and features', 2)
ON CONFLICT (slug) DO NOTHING;