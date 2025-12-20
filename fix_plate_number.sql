-- Fix for customers table plate_number constraint
-- This migration makes plate_number nullable to allow customers without plate numbers

-- First, handle existing empty plate numbers
UPDATE customers SET plate_number = NULL WHERE plate_number = '';

-- Then modify the column to allow NULL values
ALTER TABLE customers MODIFY COLUMN plate_number VARCHAR(20) NULL;

-- Add a comment to explain the change
ALTER TABLE customers ADD CONSTRAINT chk_plate_number_not_empty
    CHECK (plate_number IS NULL OR LENGTH(TRIM(plate_number)) > 0);