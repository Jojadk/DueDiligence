-- Add parent_id and sort_order to building_elements
ALTER TABLE building_elements 
ADD COLUMN IF NOT EXISTS parent_id INT NULL,
ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;

-- Create index for parent_id
CREATE INDEX IF NOT EXISTS idx_building_elements_parent_id ON building_elements(parent_id);
CREATE INDEX IF NOT EXISTS idx_building_elements_project_id ON building_elements(project_id);
