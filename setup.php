<?php
/**
 * Script di setup iniziale del database.
 * Eseguire una sola volta per creare il database e i dati di esempio.
 *
 * Credenziali demo:
 *   Admin:   mario.rossi@scuola.it   / admin123
 *   Admin:   luigi.bianchi@scuola.it / admin123
 *   Docente: anna.verdi@scuola.it    / docente123
 *   Docente: paolo.neri@scuola.it    / docente123
 *   Docente: sara.gialli@scuola.it   / docente123
 *
 * Uso: php setup.php  (da riga di comando)
 *      oppure visita http://localhost/registrony/setup.php (da browser)
 */

// Connessione senza specificare il database
$host = 'localhost';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("Errore connessione MySQL: " . $e->getMessage() . "\n");
}

echo "Connessione MySQL OK\n";

// Leggi e esegui lo script SQL (senza i dati di esempio e le query di verifica)
echo "Creazione database e tabelle...\n";

$sql = "
DROP DATABASE IF EXISTS registro_laboratori;
CREATE DATABASE registro_laboratori CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE registro_laboratori;
";

// Esegui creazione DB
$pdo->exec($sql);

// Riconnetti al nuovo database
$pdo = new PDO("mysql:host=$host;dbname=registro_laboratori;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Crea tabelle
$pdo->exec("
CREATE TABLE utenti (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    ruolo ENUM('admin', 'docente') NOT NULL DEFAULT 'docente',
    telefono VARCHAR(20) DEFAULT NULL,
    attivo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_utenti_ruolo (ruolo),
    INDEX idx_utenti_cognome_nome (cognome, nome),
    INDEX idx_utenti_attivo (attivo)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE laboratori (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    aula VARCHAR(50) NOT NULL,
    id_assistente_tecnico INT UNSIGNED NOT NULL,
    id_responsabile INT UNSIGNED NOT NULL,
    descrizione TEXT DEFAULT NULL,
    attivo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lab_assistente FOREIGN KEY (id_assistente_tecnico) REFERENCES utenti(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lab_responsabile FOREIGN KEY (id_responsabile) REFERENCES utenti(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_laboratori_aula (aula),
    INDEX idx_laboratori_attivo (attivo)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE classi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(20) NOT NULL,
    anno_scolastico VARCHAR(9) NOT NULL,
    indirizzo VARCHAR(100) DEFAULT NULL,
    attivo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_classe_anno (nome, anno_scolastico),
    INDEX idx_classi_anno (anno_scolastico),
    INDEX idx_classi_attivo (attivo)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE materiali (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descrizione TEXT DEFAULT NULL,
    unita_misura VARCHAR(30) DEFAULT NULL,
    id_laboratorio INT UNSIGNED NOT NULL,
    quantita_disponibile DECIMAL(10,2) DEFAULT NULL,
    soglia_minima DECIMAL(10,2) DEFAULT NULL,
    attivo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_materiale_laboratorio FOREIGN KEY (id_laboratorio) REFERENCES laboratori(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_materiali_laboratorio (id_laboratorio),
    INDEX idx_materiali_attivo (attivo)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE sessioni_laboratorio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_laboratorio INT UNSIGNED NOT NULL,
    id_classe INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    ora_ingresso TIME NOT NULL,
    ora_uscita TIME DEFAULT NULL,
    attivita_svolta TEXT DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sessione_laboratorio FOREIGN KEY (id_laboratorio) REFERENCES laboratori(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_sessione_classe FOREIGN KEY (id_classe) REFERENCES classi(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_sessioni_data (data),
    INDEX idx_sessioni_laboratorio (id_laboratorio),
    INDEX idx_sessioni_classe (id_classe),
    INDEX idx_sessioni_lab_data (id_laboratorio, data)
) ENGINE=InnoDB;
");

$pdo->exec("
CREATE TABLE firme_sessioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sessione INT UNSIGNED NOT NULL,
    id_docente INT UNSIGNED NOT NULL,
    tipo_presenza ENUM('titolare', 'compresenza') NOT NULL DEFAULT 'titolare',
    ora_firma TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_firma_sessione FOREIGN KEY (id_sessione) REFERENCES sessioni_laboratorio(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_firma_docente FOREIGN KEY (id_docente) REFERENCES utenti(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uk_firma_docente_sessione (id_sessione, id_docente),
    INDEX idx_firme_docente (id_docente),
    INDEX idx_firme_sessione (id_sessione)
) ENGINE=InnoDB;
");

echo "Creazione trigger firme...\n";
$pdo->exec("
CREATE TRIGGER trg_firme_max_due_insert
BEFORE INSERT ON firme_sessioni
FOR EACH ROW
BEGIN
    DECLARE conteggio INT;
    SELECT COUNT(*) INTO conteggio FROM firme_sessioni WHERE id_sessione = NEW.id_sessione;
    IF conteggio >= 2 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Errore: massimo 2 firme per sessione (compresenza).';
    END IF;
END
");

$pdo->exec("
CREATE TABLE utilizzo_materiali (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_sessione INT UNSIGNED NOT NULL,
    id_materiale INT UNSIGNED NOT NULL,
    quantita_usata DECIMAL(10,2) NOT NULL DEFAULT 0,
    esaurito BOOLEAN NOT NULL DEFAULT FALSE,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_utilizzo_sessione FOREIGN KEY (id_sessione) REFERENCES sessioni_laboratorio(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_utilizzo_materiale FOREIGN KEY (id_materiale) REFERENCES materiali(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE KEY uk_utilizzo_sessione_materiale (id_sessione, id_materiale),
    INDEX idx_utilizzo_sessione (id_sessione),
    INDEX idx_utilizzo_materiale (id_materiale)
) ENGINE=InnoDB;
");

echo "Creazione trigger materiali...\n";
$pdo->exec("
CREATE TRIGGER trg_aggiorna_quantita_materiale
AFTER INSERT ON utilizzo_materiali
FOR EACH ROW
BEGIN
    UPDATE materiali
    SET quantita_disponibile = GREATEST(0, IFNULL(quantita_disponibile, 0) - NEW.quantita_usata)
    WHERE id = NEW.id_materiale AND quantita_disponibile IS NOT NULL;
    IF NEW.esaurito = TRUE THEN
        UPDATE materiali SET quantita_disponibile = 0 WHERE id = NEW.id_materiale;
    END IF;
END
");

$pdo->exec("
CREATE TABLE segnalazioni (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_laboratorio INT UNSIGNED NOT NULL,
    id_sessione INT UNSIGNED DEFAULT NULL,
    id_utente INT UNSIGNED NOT NULL,
    titolo VARCHAR(255) NOT NULL,
    descrizione TEXT NOT NULL,
    priorita ENUM('bassa', 'media', 'alta', 'urgente') NOT NULL DEFAULT 'media',
    stato ENUM('aperta', 'in_lavorazione', 'risolta', 'chiusa') NOT NULL DEFAULT 'aperta',
    data_segnalazione TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_risoluzione TIMESTAMP DEFAULT NULL,
    note_risoluzione TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_segnalazione_laboratorio FOREIGN KEY (id_laboratorio) REFERENCES laboratori(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_segnalazione_sessione FOREIGN KEY (id_sessione) REFERENCES sessioni_laboratorio(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_segnalazione_utente FOREIGN KEY (id_utente) REFERENCES utenti(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    INDEX idx_segnalazioni_laboratorio (id_laboratorio),
    INDEX idx_segnalazioni_stato (stato),
    INDEX idx_segnalazioni_priorita (priorita),
    INDEX idx_segnalazioni_utente (id_utente),
    INDEX idx_segnalazioni_data (data_segnalazione)
) ENGINE=InnoDB;
");

echo "Tabelle create!\n";

// Inserisci dati di esempio con password hash reali
echo "Inserimento dati di esempio...\n";

$adminHash = password_hash('admin123', PASSWORD_DEFAULT);
$docenteHash = password_hash('docente123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO utenti (nome, cognome, email, password_hash, ruolo, telefono) VALUES (?,?,?,?,?,?)");
$stmt->execute(['Mario', 'Rossi', 'mario.rossi@scuola.it', $adminHash, 'admin', '333-1111111']);
$stmt->execute(['Luigi', 'Bianchi', 'luigi.bianchi@scuola.it', $adminHash, 'admin', '333-2222222']);
$stmt->execute(['Anna', 'Verdi', 'anna.verdi@scuola.it', $docenteHash, 'docente', '333-3333333']);
$stmt->execute(['Paolo', 'Neri', 'paolo.neri@scuola.it', $docenteHash, 'docente', '333-4444444']);
$stmt->execute(['Sara', 'Gialli', 'sara.gialli@scuola.it', $docenteHash, 'docente', null]);

$pdo->exec("INSERT INTO laboratori (nome, aula, id_assistente_tecnico, id_responsabile, descrizione) VALUES
    ('Laboratorio Informatica 1', 'A101', 1, 2, 'Laboratorio con 30 postazioni PC'),
    ('Laboratorio Elettronica', 'B205', 2, 1, 'Laboratorio con banchi di lavoro e strumentazione elettronica'),
    ('Laboratorio Chimica', 'C110', 1, 2, 'Laboratorio con cappe aspiranti e reagenti')
");

$pdo->exec("INSERT INTO classi (nome, anno_scolastico, indirizzo) VALUES
    ('3A', '2025/2026', 'Informatica'),
    ('4B', '2025/2026', 'Elettronica'),
    ('5A', '2025/2026', 'Informatica'),
    ('3B', '2025/2026', 'Chimica')
");

$pdo->exec("INSERT INTO materiali (nome, descrizione, unita_misura, id_laboratorio, quantita_disponibile, soglia_minima) VALUES
    ('Cavo Ethernet Cat.6', 'Cavi di rete per connessione postazioni', 'pezzi', 1, 50, 10),
    ('Mouse USB', 'Mouse ottico USB di ricambio', 'pezzi', 1, 15, 5),
    ('Tastiera USB', 'Tastiera standard USB di ricambio', 'pezzi', 1, 10, 3),
    ('Resistenze 1K Ohm', 'Pacchetto resistenze 1K Ohm 1/4W', 'pezzi', 2, 200, 50),
    ('Breadboard', 'Basetta sperimentale senza saldatura', 'pezzi', 2, 30, 10),
    ('LED rossi 5mm', 'LED standard rossi 5mm', 'pezzi', 2, 100, 20),
    ('Acido cloridrico 37%', 'HCl concentrato per esperimenti', 'litri', 3, 5.00, 1.00),
    ('Becher 250ml', 'Becher in vetro borosilicato 250ml', 'pezzi', 3, 20, 5),
    ('Guanti in nitrile M', 'Guanti monouso misura M', 'pezzi', 3, 100, 30)
");

$today = date('Y-m-d');

$pdo->exec("INSERT INTO sessioni_laboratorio (id_laboratorio, id_classe, data, ora_ingresso, ora_uscita, attivita_svolta, note) VALUES
    (1, 1, '$today', '08:30:00', '10:30:00', 'Esercitazione su reti LAN: configurazione IP e subnet mask.', NULL),
    (2, 2, '$today', '10:30:00', '12:30:00', 'Montaggio circuito con LED e resistenze su breadboard.', 'Alcuni studenti hanno avuto difficolta con il calcolo delle resistenze.')
");

$pdo->exec("INSERT INTO firme_sessioni (id_sessione, id_docente, tipo_presenza) VALUES (1, 3, 'titolare')");
$pdo->exec("INSERT INTO firme_sessioni (id_sessione, id_docente, tipo_presenza) VALUES (2, 4, 'titolare'), (2, 5, 'compresenza')");

$pdo->exec("INSERT INTO utilizzo_materiali (id_sessione, id_materiale, quantita_usata, esaurito, note) VALUES
    (1, 1, 5, FALSE, 'Usati per collegamento nuove postazioni'),
    (2, 4, 30, FALSE, NULL),
    (2, 5, 15, FALSE, NULL),
    (2, 6, 30, FALSE, NULL)
");

$pdo->exec("INSERT INTO segnalazioni (id_laboratorio, id_sessione, id_utente, titolo, descrizione, priorita, stato) VALUES
    (1, 1, 3, 'PC postazione 12 non si accende', 'Il PC della postazione numero 12 non si accende. Provato a cambiare presa elettrica ma il problema persiste. Potrebbe essere l alimentatore.', 'alta', 'aperta'),
    (2, NULL, 1, 'Oscilloscopio banco 3 da calibrare', 'L oscilloscopio del banco 3 mostra letture imprecise. Necessaria calibrazione.', 'media', 'aperta')
");

echo "\n========================================\n";
echo "SETUP COMPLETATO CON SUCCESSO!\n";
echo "========================================\n";
echo "\nCredenziali di accesso:\n";
echo "  ADMIN:   mario.rossi@scuola.it   / admin123\n";
echo "  ADMIN:   luigi.bianchi@scuola.it / admin123\n";
echo "  DOCENTE: anna.verdi@scuola.it    / docente123\n";
echo "  DOCENTE: paolo.neri@scuola.it    / docente123\n";
echo "  DOCENTE: sara.gialli@scuola.it   / docente123\n";
echo "\n";
