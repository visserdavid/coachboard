-- CoachBoard — Seed Data
-- Run this once after importing schema.sql to create the first administrator account.
-- Change the email address to your own before importing.

-- Change this email to your own before importing
INSERT INTO `user` (first_name, email, is_administrator, active, created_at, updated_at)
VALUES ('Admin', 'admin@example.com', 1, 1, NOW(), NOW());
