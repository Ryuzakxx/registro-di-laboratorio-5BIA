DROP DATABASE IF EXISTS registrony;
CREATE DATABASE registrony CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE registrony;

-- Tabella utenti con colonna 'password' (corrisponde al tuo PHP)
CREATE TABLE utenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL, 
    ruolo ENUM('admin', 'docente') NOT NULL DEFAULT 'docente',
    attivo BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- Inserimento utenti richiesti
INSERT INTO utenti (id, nome, cognome, email, password, ruolo) VALUES
(1, 'Daniele', 'Signorile', 'daniele.signorile@itsff.it', 'cambiami2026', 'admin'),
(2, 'Mario', 'Rossi', 'mario.rossi@scuola.it', 'docente123', 'admin'),
(3, 'Luigi', 'Bianchi', 'luigi.bianchi@scuola.it', 'admin456', 'admin'),
(4, 'Elena', 'Torricelli', 'elena.torricelli@itsff.it', 'tecnico2026', 'admin'),
(5, 'Roberto', 'Boyle', 'roberto.boyle@itsff.it', 'docente1', 'docente');

-- Tabella laboratori
CREATE TABLE laboratori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    aula VARCHAR(50) NOT NULL,
    id_assistente_tecnico INT UNSIGNED NOT NULL,
    id_responsabile INT UNSIGNED NOT NULL,
    CONSTRAINT fk_lab_assistente FOREIGN KEY (id_assistente_tecnico) REFERENCES utenti(id),
    CONSTRAINT fk_lab_responsabile FOREIGN KEY (id_responsabile) REFERENCES utenti(id)
) ENGINE=InnoDB;

INSERT INTO laboratori (nome, aula, id_assistente_tecnico, id_responsabile) VALUES
('Lab Sistemi e Reti', 'SR-01', 1, 2),
('Lab Informatica',    'INF-02', 1, 3),
('Lab Biennio',        'B-03',   4, 1),
('Lab TIPSIT',         'T-04',   1, 4);

-- Tabella classi
CREATE TABLE classi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL,
    anno_scolastico VARCHAR(9) NOT NULL,
    indirizzo VARCHAR(100)
) ENGINE=InnoDB;

INSERT INTO classi (nome, anno_scolastico, indirizzo) VALUES
('1AIT', '2025/2026', 'Informatica e Telecomunicazioni'),
('1BIT', '2025/2026', 'Informatica e Telecomunicazioni'),
('2AIT', '2025/2026', 'Informatica e Telecomunicazioni'),
('3AIA', '2025/2026', 'Informatica'),
('3BIA', '2025/2026', 'Informatica'),
('4AIA', '2025/2026', 'Informatica'),
('5AIA', '2025/2026', 'Informatica');