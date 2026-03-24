<?php
$pageTitle = 'Segnalazioni';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getConnection();

$filtroStato = $_GET['stato'] ?? '';
$filtroLab = $_GET['laboratorio'] ?? '';

$where = [];
$params = [];
if ($filtroStato) { $where[] = "sg.stato = ?"; $params[] = $filtroStato; }
if ($filtroLab) { $where[] = "sg.id_laboratorio = ?"; $params[] = $filtroLab; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT sg.*, l.nome AS laboratorio, l.aula,
           CONCAT(u.cognome, ' ', u.nome) AS segnalato_da
    FROM segnalazioni sg
    JOIN laboratori l ON sg.id_laboratorio = l.id
    JOIN utenti u ON sg.id_utente = u.id
    $whereSQL
    ORDER BY FIELD(sg.priorita, 'urgente','alta','media','bassa'), sg.data_segnalazione DESC
");
$stmt->execute($params);
$segnalazioni = $stmt->fetchAll();

$labs = $pdo->query("SELECT id, nome FROM laboratori WHERE attivo = 1 ORDER BY nome")->fetchAll();
?>

<div class="card mb-2">
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 align-center flex-wrap">
            <div class="form-group" style="margin-bottom:0">
                <label>Stato</label>
                <select name="stato" class="form-control">
                    <option value="">Tutti</option>
                    <option value="aperta" <?= $filtroStato === 'aperta' ? 'selected' : '' ?>>Aperta</option>
                    <option value="in_lavorazione" <?= $filtroStato === 'in_lavorazione' ? 'selected' : '' ?>>In lavorazione</option>
                    <option value="risolta" <?= $filtroStato === 'risolta' ? 'selected' : '' ?>>Risolta</option>
                    <option value="chiusa" <?= $filtroStato === 'chiusa' ? 'selected' : '' ?>>Chiusa</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label>Laboratorio</label>
                <select name="laboratorio" class="form-control">
                    <option value="">Tutti</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>" <?= $filtroLab == $lab['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lab['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-top:22px">
                <button type="submit" class="btn btn-primary btn-sm">Filtra</button>
                <a href="<?= BASE_PATH ?>/pages/segnalazioni/index.php" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>&#9888; Segnalazioni (<?= count($segnalazioni) ?>)</h3>
        <a href="<?= BASE_PATH ?>/pages/segnalazioni/nuova.php" class="btn btn-warning btn-sm">+ Nuova Segnalazione</a>
    </div>
    <div class="card-body">
        <?php if (empty($segnalazioni)): ?>
            <div class="empty-state">
                <div class="empty-icon">&#10004;</div>
                <h4>Nessuna segnalazione trovata</h4>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titolo</th>
                            <th>Laboratorio</th>
                            <th>Priorita</th>
                            <th>Stato</th>
                            <th>Segnalato da</th>
                            <th>Data</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($segnalazioni as $sg): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sg['titolo']) ?></strong></td>
                            <td><?= htmlspecialchars($sg['laboratorio']) ?></td>
                            <td>
                                <?php
                                $bc = match($sg['priorita']) {
                                    'urgente' => 'badge-danger',
                                    'alta' => 'badge-warning',
                                    'media' => 'badge-info',
                                    default => 'badge-secondary',
                                };
                                ?>
                                <span class="badge <?= $bc ?>"><?= $sg['priorita'] ?></span>
                            </td>
                            <td>
                                <?php
                                $sc = match($sg['stato']) {
                                    'aperta' => 'badge-danger',
                                    'in_lavorazione' => 'badge-warning',
                                    'risolta' => 'badge-success',
                                    default => 'badge-secondary',
                                };
                                ?>
                                <span class="badge <?= $sc ?>"><?= str_replace('_', ' ', $sg['stato']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($sg['segnalato_da']) ?></td>
                            <td><?= date('d/m/Y', strtotime($sg['data_segnalazione'])) ?></td>
                            <td>
                                <a href="<?= BASE_PATH ?>/pages/segnalazioni/dettaglio.php?id=<?= $sg['id'] ?>" class="btn btn-primary btn-sm">Dettagli</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
