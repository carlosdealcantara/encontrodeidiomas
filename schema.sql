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

-- ==========================================
-- MÓDULO MENTORIA & COBRANÇAS
-- ==========================================

-- Table: mentoria_alunos
CREATE TABLE IF NOT EXISTS mentoria_alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(50) NOT NULL,
    status_aluno ENUM('Ativo', 'Inativo', 'Vitalício') DEFAULT 'Ativo',
    valor_mensalidade DECIMAL(10,2) DEFAULT 0.00,
    total_investido DECIMAL(10,2) DEFAULT 0.00,
    dia_vencimento INT NOT NULL,
    proximo_vencimento DATE NOT NULL,
    status_pagamento ENUM('Pendente', 'Pago', 'Suspenso', 'Isento') DEFAULT 'Pendente',
    grupo_atual VARCHAR(100) DEFAULT 'Our Meetups',
    observacoes TEXT,
    data_inicio DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: mentoria_mensagens (Dashboard de textos)
CREATE TABLE IF NOT EXISTS mentoria_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cenario VARCHAR(100) NOT NULL,
    dias_antes INT NOT NULL,
    texto TEXT NOT NULL,
    ativo TINYINT(1) DEFAULT 1
);

-- Insert Default Messages
INSERT IGNORE INTO mentoria_mensagens (cenario, dias_antes, texto) VALUES
('Aviso Amigável (Prévio)', 3, '🤖 MENSAGEM AUTOMÁTICA:\nOlá, {nome}. Consta no Sistema que o seu ciclo de acesso vence em 3 dias.\nPara evitar a suspensão automática pelo servidor, realize a renovação e nos envie o comprovante.'),
('Aviso de Véspera', 1, '🤖 MENSAGEM AUTOMÁTICA:\nOlá, {nome}. O seu ciclo de acesso vence amanhã.\nPor favor, realize a renovação.'),
('Aviso de Vencimento', 0, '🤖 MENSAGEM AUTOMÁTICA:\nOlá, {nome}. O prazo de renovação do seu acesso encerra hoje.'),
('Aviso de Suspensão', -1, '🤖 AVISO DE SUSPENSÃO:\nOlá, {nome}. O sistema suspendeu seu acesso devido à não identificação da renovação.\nEntre em contato com o suporte para reativarmos manualmente.');

-- ==========================================
-- MÓDULO ODYSEE PIPELINE
-- ==========================================

-- Add columns to languages table
-- Note: Assuming the 'languages' table exists as it's referenced in 'meetings'
-- ALTER TABLE languages ADD COLUMN odysee_channel_id VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE languages ADD COLUMN odysee_channel_name VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE languages ADD COLUMN whatsapp_group_id VARCHAR(255) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS odysee_publish_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT NOT NULL,
    drive_file_id VARCHAR(255) NOT NULL,
    drive_file_name VARCHAR(500) NOT NULL,
    topico VARCHAR(500) NOT NULL,
    titulo_final VARCHAR(700) DEFAULT NULL,
    odysee_slug VARCHAR(100) DEFAULT NULL,
    odysee_url VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','processing','done','error') DEFAULT 'pending',
    error_message TEXT DEFAULT NULL,
    retry_count TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (language_id) REFERENCES languages(id)
);
