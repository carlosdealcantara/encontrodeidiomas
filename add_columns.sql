-- Add new columns to the hosts table
ALTER TABLE hosts ADD COLUMN region VARCHAR(100) AFTER online_description;
ALTER TABLE hosts ADD COLUMN category VARCHAR(100) AFTER region;
ALTER TABLE hosts ADD COLUMN role VARCHAR(100) AFTER category;
ALTER TABLE hosts ADD COLUMN special_badge VARCHAR(255) AFTER role;

-- Update specific hosts with their region and category
-- Carlos de Alcântara with all categories
UPDATE hosts SET 
  region = 'Brasília - DF',
  category = 'Online,Presencial,Técnica',
  role = 'Desenvolvimento,Design',
  special_badge = 'Francês & Alemão'
WHERE full_name = 'Carlos de Alcântara';

-- Michele as Technical
UPDATE hosts SET 
  region = 'Brasília - DF',
  category = 'Técnica',
  role = 'Design',
  special_badge = NULL
WHERE full_name = 'Michele';

-- Paula as In-person
UPDATE hosts SET 
  region = 'São Paulo - SP',
  category = 'Presencial',
  role = NULL,
  special_badge = NULL
WHERE full_name = 'Paula';

-- Daniel with special badge
UPDATE hosts SET 
  special_badge = 'Russo, Polonês & Sérvio'
WHERE full_name = 'Daniel';

-- Default values for all other hosts
UPDATE hosts SET 
  region = 'Não informado',
  category = 'Online',
  role = NULL
WHERE region IS NULL OR category IS NULL; 