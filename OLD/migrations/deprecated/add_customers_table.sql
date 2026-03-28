-- Create customers table
CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- Add client_id to projects
ALTER TABLE projects ADD COLUMN IF NOT EXISTS client_id INT NULL;
CREATE INDEX IF NOT EXISTS idx_projects_client_id ON projects(client_id);
