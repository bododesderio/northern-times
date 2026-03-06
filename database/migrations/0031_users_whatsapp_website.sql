-- Migration: 0031_users_whatsapp_website.sql
-- Adds WhatsApp number and website URL columns to users table

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS website_url     VARCHAR(500) NULL;
