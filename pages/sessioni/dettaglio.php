<?php
$pageTitle = 'Dettaglio Sessione';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getConnection();
$id = intval($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . BASE_PATH . '/pages/sessioni/index.php?error=Sessione non trovata');
    exit;
}

// Gestione azioni POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Chiudi sessione (registra ora uscita)
    if ($action === 'chiudi_sessione') {
        $oraUscita = $_POST['ora_uscita'] ?? date('H:i');
        $stmt = $pdo->prepare("UPDATE sessioni_laboratorio SET ora_uscita = ? WHERE id = ? AND ora_uscita IS NULL");
        $stmt->execute([$oraUscita, $id]);
        header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&success=Sessione chiusa!');
        exit;
    }

    // Aggiorna attivita e note
    if ($action === 'aggiorna') {
        $attivita = trim($_POST['attivita_svolta'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $stmt = $pdo->prepare("UPDATE sessioni_laboratorio SET attivita_svolta = ?, note = ? WHERE id = ?");
        $stmt->execute([$attivita ?: null, $note ?: null, $id]);
        header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&success=Sessione aggiornata!');
        exit;
    }

    // Aggiungi materiale usato
    if ($action === 'aggiungi_materiale') {
        $idMateriale = intval($_POST['id_materiale'] ?? 0);
        $quantita = floatval($_POST['quantita_usata'] ?? 0);
        $esaurito = isset($_POST['esaurito']) ? 1 : 0;
        $noteMat = trim($_POST['note_materiale'] ?? '');
        if ($idMateriale && $quantita > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO utilizzo_materiali (id_sessione, id_materiale, quantita_usata, esaurito, note) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $idMateriale, $quantita, $esaurito, $noteMat ?: null]);
                header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&success=Materiale registrato!');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&error=Materiale gia registrato per questa sessione');
                    exit;
                }
                throw $e;
            }
        }
    }

    // Aggiungi firma compresenza
    if ($action === 'aggiungi_firma') {
        $idDocente = intval($_POST['id_docente'] ?? 0);
        if ($idDocente) {
            try {
                $stmt = $pdo->prepare("INSERT INTO firme_sessioni (id_sessione, id_docente, tipo_presenza) VALUES (?, ?, 'compresenza')");
                $stmt->execute([$id, $idDocente]);
                header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&success=Firma aggiunta!');
                exit;
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (strpos($msg, 'massimo 2 firme') !== false) {
                    header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&error=Massimo 2 firme per sessione');
                } elseif (strpos($msg, 'Duplicate') !== false) {
                    header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&error=Questo docente ha gia firmato');
                } else {
                    header('Location: ' . BASE_PATH . '/pages/sessioni/dettaglio.php?id=' . $id . '&error=' . urlencode($msg));
                }
                exit;
            }
        }
    }
}

// Carica sessione
$stmt = $pdo->prepare("
    SELECT s.*, l.nome AS laboratorio, l.aula, c.nome AS classe, c.anno_scolastico
    FROM sessioni_laboratorio s
    JOIN laboratori l ON s.id_laboratorio = l.id
    JOIN classi c ON s.id_classe = c.id
    WHERE s.id = ?
");
$stmt->execute([$id]);
$sessione = $stmt->fetch();

if (!$sessione) {
    header('Location: ' . BASE_PATH . '/pages/sessioni/index.php?error=Sessione non trovata');
    exit;
}

// Firme
$stmtFirme = $pdo->prepare("
    SELECT f.*, u.nome, u.cognome
    FROM firme_sessioni f
    JOIN utenti u ON f.id_docente = u.id
    WHERE f.id_sessione = ?
    ORDER BY f.tipo_presenza
");
$stmtFirme->execute([$id]);
$firme = $stmtFirme->fetchAll();

// Materiali usati
$stmtMat = $pdo->prepare("
    SELECT um.*, m.nome AS materiale, m.unita_misura
    FROM utilizzo_materiali um
    JOIN materiali m ON um.id_materiale = m.id
    WHERE um.id_sessione = ?
");
$stmtMat->execute([$id]);
$materialiUsati = $stmtMat->fetchAll();

// Materiali disponibili per il laboratorio (non ancora usati in questa sessione)
$stmtMatDisp = $pdo->prepare("
    SELECT m.id, m.nome, m.unita_misura, m.quantita_disponibile
    FROM materiali m
    WHERE m.id_laboratorio = ? AND m.attivo = 1
      AND m.id NOT IN (SELECT id_materiale FROM utilizzo_materiali WHERE id_sessione = ?)
    ORDER BY m.nome
");
$stmtMatDisp->execute([$sessione['id_laboratorio'], $id]);
$materialiDisponibili = $stmtMatDisp->fetchAll();

// Docenti per eventuale firma compresenza
$docenti = $pdo->query("SELECT id, nome, cognome FROM utenti WHERE attivo = 1 ORDER BY cognome, nome")->fetchAll();

$inCorso = is_null($sessione['ora_uscita']);
?>

<!-- Info sessione -->
<div class="card">
    <div class="card-header">
        <h3>&#128203; Sessione #<?= $id ?></h3>
        <?php if ($inCorso): ?>
            <span class="badge badge-success" style="font-size:13px; padding:6px 14px;">&#9679; IN CORSO</span>
        <?php else: ?>
            <span class="badge badge-secondary" style="font-size:13px; padding:6px 14px;">Completata</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="form-row">
            <div>
                <strong>Laboratorio:</strong><br>
                <?= htmlspecialchars($sessione['laboratorio']) ?> (<?= htmlspecialchars($sessione['aula']) ?>)
            </div>
            <div>
                <strong>Classe:</strong><br>
                <span class="badge badge-primary"><?= htmlspecialchars($sessione['classe']) ?></span>
                <?= htmlspecialchars($sessione['anno_scolastico']) ?>
            </div>
            <div>
                <strong>Data:</strong><br>
                <?= date('d/m/Y', strtotime($sessione['data'])) ?>
            </div>
            <div>
                <strong>Orario:</strong><br>
                <?= substr($sessione['ora_ingresso'], 0, 5) ?>
                -
                <?= $sessione['ora_uscita'] ? substr($sessione['ora_uscita'], 0, 5) : '...' ?>
            </div>
        </div>
    </div>
</div>

<!-- Firme docenti -->
<div class="card">
    <div class="card-header">
        <h3>&#9997; Firme Docenti</h3>
    </div>
    <div class="card-body">
        <?php if (empty($firme)): ?>
            <p class="text-muted">Nessuna firma registrata.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Docente</th><th>Tipo</th><th>Ora Firma</th></tr></thead>
                    <tbody>
                        <?php foreach ($firme as $f): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($f['cognome'] . ' ' . $f['nome']) ?></strong></td>
                            <td><span class="badge <?= $f['tipo_presenza'] === 'titolare' ? 'badge-primary' : 'badge-info' ?>"><?= $f['tipo_presenza'] ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($f['ora_firma'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (count($firme) < 2): ?>
            <form method="POST" class="mt-2 d-flex gap-2 align-center flex-wrap">
                <input type="hidden" name="action" value="aggiungi_firma">
                <select name="id_docente" class="form-control" style="max-width:300px" required>
                    <option value="">-- Aggiungi firma compresenza --</option>
                    <?php foreach ($docenti as $doc): ?>
                        <?php
                        $giaFirmato = false;
                        foreach ($firme as $f) { if ($f['id_docente'] == $doc['id']) { $giaFirmato = true; break; } }
                        if ($giaFirmato) continue;
                        ?>
                        <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['cognome'] . ' ' . $doc['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Aggiungi Firma</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Chiudi sessione -->
<?php if ($inCorso): ?>
<div class="card">
    <div class="card-header">
        <h3>&#128308; Chiudi Sessione</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="d-flex gap-2 align-center flex-wrap">
            <input type="hidden" name="action" value="chiudi_sessione">
            <div class="form-group" style="margin-bottom:0">
                <label>Ora Uscita</label>
                <input type="time" name="ora_uscita" class="form-control" value="<?= date('H:i') ?>" required>
            </div>
            <button type="submit" class="btn btn-danger btn-sm" style="margin-top:22px;">Chiudi Sessione</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Attivita e note -->
<div class="card">
    <div class="card-header">
        <h3>&#128221; Attivita e Note</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="aggiorna">
            <div class="form-group">
                <label>Attivita Svolta</label>
                <textarea name="attivita_svolta" class="form-control" rows="3"><?= htmlspecialchars($sessione['attivita_svolta'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label>Note</label>
                <textarea name="note" class="form-control" rows="2"><?= htmlspecialchars($sessione['note'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Aggiorna</button>
        </form>
    </div>
</div>

<!-- Materiali usati -->
<div class="card">
    <div class="card-header">
        <h3>&#128230; Materiali Utilizzati</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($materialiUsati)): ?>
            <div class="table-responsive mb-2">
                <table class="table">
                    <thead><tr><th>Materiale</th><th>Quantita</th><th>Unita</th><th>Esaurito</th><th>Note</th></tr></thead>
                    <tbody>
                        <?php foreach ($materialiUsati as $mu): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($mu['materiale']) ?></strong></td>
                            <td><?= $mu['quantita_usata'] ?></td>
                            <td><?= htmlspecialchars($mu['unita_misura'] ?? '-') ?></td>
                            <td>
                                <?php if ($mu['esaurito']): ?>
                                    <span class="badge badge-danger">Esaurito</span>
                                <?php else: ?>
                                    <span class="badge badge-success">OK</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($mu['note'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted mb-2">Nessun materiale registrato per questa sessione.</p>
        <?php endif; ?>

        <?php if (!empty($materialiDisponibili)): ?>
            <form method="POST" class="mt-1">
                <input type="hidden" name="action" value="aggiungi_materiale">
                <h4 class="mb-1" style="font-size:14px; font-weight:600;">Aggiungi materiale:</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>Materiale</label>
                        <select name="id_materiale" class="form-control" required>
                            <option value="">-- Seleziona --</option>
                            <?php foreach ($materialiDisponibili as $md): ?>
                                <option value="<?= $md['id'] ?>">
                                    <?= htmlspecialchars($md['nome']) ?>
                                    <?php if ($md['quantita_disponibile'] !== null): ?>
                                        (disp: <?= $md['quantita_disponibile'] ?> <?= htmlspecialchars($md['unita_misura'] ?? '') ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantita Usata</label>
                        <input type="number" name="quantita_usata" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <input type="text" name="note_materiale" class="form-control" placeholder="Opzionale">
                    </div>
                </div>
                <div class="d-flex gap-2 align-center">
                    <label style="font-weight:normal; font-size:13px; display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" name="esaurito" value="1"> Materiale esaurito/finito
                    </label>
                    <button type="submit" class="btn btn-warning btn-sm">Aggiungi Materiale</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2 mt-2">
    <a href="<?= BASE_PATH ?>/pages/sessioni/index.php" class="btn btn-secondary">&#8592; Torna alle sessioni</a>
    <a href="<?= BASE_PATH ?>/pages/segnalazioni/nuova.php?id_laboratorio=<?= $sessione['id_laboratorio'] ?>&id_sessione=<?= $id ?>" class="btn btn-warning">&#9888; Segnala Problema</a>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
