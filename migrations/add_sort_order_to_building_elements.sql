-- Migration: Add sort_order column to building_elements table
-- Date: 2026-01-16
-- Purpose: Enable drag-and-drop sorting of building elements

-- Add sort_order column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'building_elements'
        AND column_name = 'sort_order'
    ) THEN
        ALTER TABLE building_elements
        ADD COLUMN sort_order INTEGER DEFAULT 0;

        -- Create index for better performance
        CREATE INDEX idx_building_elements_sort_order
        ON building_elements(building_id, sort_order);

        RAISE NOTICE 'Added sort_order column to building_elements table';
    ELSE
        RAISE NOTICE 'sort_order column already exists in building_elements table';
    END IF;
END $$;

-- Initialize sort_order for existing records (grouped by building)
UPDATE building_elements be
SET sort_order = subq.row_num
FROM (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY building_id ORDER BY name) - 1 as row_num
    FROM building_elements
    WHERE sort_order IS NULL OR sort_order = 0
) subq
WHERE be.id = subq.id AND (be.sort_order IS NULL OR be.sort_order = 0);
