-- Migration: Add sort_order column to images table
-- Date: 2026-01-17
-- Purpose: Enable drag-and-drop sorting of images in galleries

-- Add sort_order column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'images'
        AND column_name = 'sort_order'
    ) THEN
        ALTER TABLE images
        ADD COLUMN sort_order INTEGER DEFAULT 0;

        -- Create index for better performance
        CREATE INDEX idx_images_sort_order
        ON images(entity_type, entity_id, sort_order);

        RAISE NOTICE 'Added sort_order column to images table';
    ELSE
        RAISE NOTICE 'sort_order column already exists in images table';
    END IF;
END $$;

-- Initialize sort_order for existing records (grouped by entity)
UPDATE images i
SET sort_order = subq.row_num
FROM (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY entity_type, entity_id ORDER BY created_at) - 1 as row_num
    FROM images
    WHERE sort_order IS NULL OR sort_order = 0
) subq
WHERE i.id = subq.id AND (i.sort_order IS NULL OR i.sort_order = 0);
