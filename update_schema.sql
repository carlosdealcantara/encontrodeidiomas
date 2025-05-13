-- Create a roles table for technical team members
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert some default roles
INSERT INTO roles (name) VALUES 
('Desenvolvimento'),
('Design'),
('Marketing'),
('Gestão de Conteúdo'),
('Administração');

-- Add a role_id column to hosts table (for technical team members)
ALTER TABLE hosts ADD COLUMN role_id INT AFTER category;

-- Update specific hosts with their roles
UPDATE hosts SET role_id = 1 WHERE full_name = 'Carlos de Alcântara'; -- Desenvolvimento
UPDATE hosts SET role_id = 2 WHERE full_name = 'Michele'; -- Design

-- Add a foreign key relationship
ALTER TABLE hosts 
ADD CONSTRAINT fk_host_role 
FOREIGN KEY (role_id) REFERENCES roles(id) 
ON DELETE SET NULL; 