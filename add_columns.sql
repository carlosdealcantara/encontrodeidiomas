-- Add new columns to the hosts table
ALTER TABLE hosts ADD COLUMN region VARCHAR(100) AFTER description;
ALTER TABLE hosts ADD COLUMN category VARCHAR(100) AFTER region;

-- Update specific hosts with their region and category
-- Carlos de Alcântara with all categories
UPDATE hosts SET 
  region = 'Brasília - DF',
  category = 'Online,Presencial,Técnica'
WHERE full_name = 'Carlos de Alcântara';

-- Michele as Technical
UPDATE hosts SET 
  region = 'Brasília - DF',
  category = 'Técnica'
WHERE full_name = 'Michele';

-- Paula as In-person
UPDATE hosts SET 
  region = 'São Paulo - SP',
  category = 'Presencial'
WHERE full_name = 'Paula';

-- Default values for all other hosts
UPDATE hosts SET 
  region = 'Não informado',
  category = 'Online'
WHERE region IS NULL OR category IS NULL; 