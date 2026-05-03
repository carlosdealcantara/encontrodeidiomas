-- Schema for Encontro de Idiomas Admin expansion

-- Table: meetings (Replacing/Extending events)
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT NOT NULL,
    host_id INT,
    day_of_week INT NOT NULL, -- 1=Segunda, 7=Domingo
    time_hour INT NOT NULL,    -- Hour (e.g., 19)
    title VARCHAR(255),
    description TEXT,
    meet_link VARCHAR(255),
    replay_link VARCHAR(255),
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (language_id) REFERENCES languages(id),
    FOREIGN KEY (host_id) REFERENCES hosts(id)
);

-- Table: settings (Global configurations)
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    label VARCHAR(255),
    category VARCHAR(50),
    type VARCHAR(20) DEFAULT 'text'
);

-- Table: useful_links (For links.php)
CREATE TABLE IF NOT EXISTS useful_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon VARCHAR(50), -- e.g. 'fab fa-whatsapp'
    order_index INT DEFAULT 0,
    active TINYINT(1) DEFAULT 1
);

-- Initial Settings
INSERT IGNORE INTO settings (setting_key, setting_value, label, category, type) VALUES
('site_title', 'Encontro de Idiomas', 'Título do Site', 'SEO', 'text'),
('site_description', 'Comunidade gratuita para praticar idiomas via videoconferência.', 'Descrição do Site (SEO)', 'SEO', 'textarea'),
('global_notice_active', '0', 'Ativar Aviso Global', 'Aviso', 'boolean'),
('global_notice_text', 'Feriado! Não haverá encontros nesta sexta.', 'Texto do Aviso', 'Aviso', 'textarea'),
('contact_email', 'contato@encontrodeidiomas.com.br', 'E-mail de Contato', 'Perfil', 'text');
