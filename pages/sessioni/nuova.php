<?php
$pageTitle = 'Nuova Sessione';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getConnection();

// Dati per i select
$labs = $pdo->query("SELECT id, nome, aula FROM laboratori WHERE attivo = 1 ORDER BY nome")->fetchAll();
$classi = $pdo->query("SELECT id, nome, anno_scolastico FROM classi WHERE attivo = 1 ORDER BY nome")->fetchAll();
$docenti = $pdo->query("SELECT id, nome, cognome FROM utenti WHERE attivo = 1 ORDER BY cognome, nome")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idLab = intval($_POST['id_laboratorio'] ?? 0);
    $idClasse = intval($_POST['id_classe'] ?? 0);
    $data = $_POST['data'] ?? '';
    $oraIngresso = $_POST['ora_ingresso'] ?? '';
    $oraUscita = $_POST['ora_uscita'] ?? '';
    $attivita = trim($_POST['attivita_svolta'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $docenteTitolare = intval($_POST['docente_titolare'] ?? 0);
    $docenteCompresenza = intval($_POST['docente_compresenza'] ?? 0);

    // Validazione
    if (!$idLab) $errors[] = 'Seleziona un laboratorio.';
    if (!$idClasse) $errors[] = 'Seleziona una classe.';
    if (!$data) $errors[] = 'Inserisci la data.';
    if (!$oraIngresso) $errors[] = 'Inserisci l\'ora di ingresso.';
    if (!$docenteTitolare) $errors[] = 'Seleziona il docente titolare.';
    if ($docenteCompresenza && $docenteCompresenza === $docenteTitolare) {
        $errors[] = 'Il docente in compresenza deve essere diverso dal titolare.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Inserisci sessione
            $stmt = $pdo->prepare("
                INSERT INTO sessioni_laboratorio (id_laboratorio, id_classe, data, ora_ingresso, ora_uscita, attivita_svolta, note)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idLab, $idClasse, $data, $oraIngresso,
                $oraUscita ?: null, $attivita ?: null, $note ?: null
            ]);
            $sessioneId = $pdo->lastInsertId();

            // Firma docente titolare
            $stmtFirma = $pdo->prepare("
                INSERT INTO firme_sessioni (id_sessione, id_docente, tipo_presenza) VALUES (?, ?, ?)
            ");
            $stmtFirma->execute([$sessioneId, $docenteTitolare, 'titolare']);

            // Firma docente compresenza (opzionale)
            if ($docenteCompresenza) {
                $stmtFirma->execute([$sessioneId, $docenteCompresenza, 'compresenza']);
            }

            $pdo->commit();
            header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $sessioneId . '&success=Sessione creata con successo!');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Errore database: ' . $e->getMessage();
        }
    }
}
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Errori:</strong><br>
        <?php foreach ($errors as $err): ?>
            - <?= htmlspecialchars($err) ?><br>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>&#10133; Registra nuova sessione di laboratorio</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="id_laboratorio">Laboratorio *</label>
                    <select name="id_laboratorio" id="id_laboratorio" class="form-control" required>
                        <option value="">-- Seleziona laboratorio --</option>
                        <?php foreach ($labs as $lab): ?>
                            <option value="<?= $lab['id'] ?>" <?= ($lab['id'] == ($_POST['id_laboratorio'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lab['nome'] . ' (' . $lab['aula'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_classe">Classe *</label>
                    <select name="id_classe" id="id_classe" class="form-control" required>
                        <option value="">-- Seleziona classe --</option>
                        <?php foreach ($classi as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= ($cl['id'] == ($_POST['id_classe'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['nome'] . ' - ' . $cl['anno_scolastico']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="data">Data *</label>
                    <input type="date" name="data" id="data" class="form-control" required
                           value="<?= htmlspecialchars($_POST['data'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="form-group">
                    <label for="ora_ingresso">Ora Ingresso *</label>
                    <input type="time" name="ora_ingresso" id="ora_ingresso" class="form-control" required
                           value="<?= htmlspecialchars($_POST['ora_ingresso'] ?? date('H:i')) ?>">
                </div>
                <div class="form-group">
                    <label for="ora_uscita">Ora Uscita</label>
                    <input type="time" name="ora_uscita" id="ora_uscita" class="form-control"
                           value="<?= htmlspecialchars($_POST['ora_uscita'] ?? '') ?>">
                    <div class="form-text">Lascia vuoto se la sessione e' in corso.</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="docente_titolare">Docente Titolare *</label>
                    <select name="docente_titolare" id="docente_titolare" class="form-control" required>
                        <option value="">-- Seleziona docente --</option>
                        <?php foreach ($docenti as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= ($doc['id'] == ($_POST['docente_titolare'] ?? getCurrentUserId())) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="docente_compresenza">Docente Compresenza</label>
                    <select name="docente_compresenza" id="docente_compresenza" class="form-control">
                        <option value="">-- Nessuno --</option>
                        <?php foreach ($docenti as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= ($doc['id'] == ($_POST['docente_compresenza'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Opzionale, per sessioni in compresenza.</div>
                </div>
            </div>

            <div class="form-group">
                <label for="attivita_svolta">Attivita Svolta</label>
                <textarea name="attivita_svolta" id="attivita_svolta" class="form-control" rows="3"
                          placeholder="Descrivi l'attivita svolta in laboratorio..."><?= htmlspecialchars($_POST['attivita_svolta'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="note">Note</label>
                <textarea name="note" id="note" class="form-control" rows="2"
                          placeholder="Eventuali note aggiuntive..."><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">&#10004; Registra Sessione</button>
                <a href="<?= BASE_PATH ?>/pages/sessioni/index.php" class="btn btn-secondary">Annulla</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
