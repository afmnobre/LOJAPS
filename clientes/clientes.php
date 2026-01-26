<?php
require '../conexao.php';
session_start();

/* =========================
   CREATE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id     = $_POST['IdCliente'] ?? null;
    $nome   = $_POST['NomeCompletoCliente'] ?? '';
    $doc    = $_POST['DocumentoCliente'] ?? '';
    $status = $_POST['Status'] ?? 'Ativado';

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE clientes 
            SET NomeCompletoCliente = ?, DocumentoCliente = ?, Status = ?
            WHERE IdCliente = ?
        ");
        $stmt->execute([$nome, $doc, $status, $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO clientes (NomeCompletoCliente, DocumentoCliente, Status)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$nome, $doc, $status]);
    }

    header('Location: clientes.php');
    exit;
}

/* =========================
   DESATIVAR
========================= */
if (isset($_GET['desativar'])) {
    $stmt = $pdo->prepare("
        UPDATE clientes SET Status = 'Desativado'
        WHERE IdCliente = ?
    ");
    $stmt->execute([$_GET['desativar']]);

    header('Location: clientes.php');
    exit;
}

/* =========================
   ATIVAR
========================= */
if (isset($_GET['ativar'])) {
    $stmt = $pdo->prepare("
        UPDATE clientes SET Status = 'Ativado'
        WHERE IdCliente = ?
    ");
    $stmt->execute([$_GET['ativar']]);

    header('Location: clientes.php');
    exit;
}

/* =========================
   EDITAR
========================= */
$clienteEdit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE IdCliente = ?");
    $stmt->execute([$_GET['edit']]);
    $clienteEdit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   LISTAGEM
========================= */
$clientes = $pdo->query("
    SELECT * FROM clientes
    ORDER BY NomeCompletoCliente
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HTML
========================= */
require_once '../layout_header.php';
require_once '../nav.php';
?>

<h2>Cadastro de Clientes</h2>

<form method="post">
    <input type="hidden" name="IdCliente" value="<?= $clienteEdit['IdCliente'] ?? '' ?>">

    <label>Nome Completo:</label>
    <input type="text"
           name="NomeCompletoCliente"
           required
           value="<?= htmlspecialchars($clienteEdit['NomeCompletoCliente'] ?? '') ?>">

    <label>Celular:</label>
    <input type="text"
           name="DocumentoCliente"
           id="DocumentoCliente"
           placeholder="(99) 99999-9999"
           maxlength="15"
           value="<?= htmlspecialchars($clienteEdit['DocumentoCliente'] ?? '') ?>">

    <label>Status:</label>
    <select name="Status">
        <option value="Ativado"
            <?= (($clienteEdit['Status'] ?? '') === 'Ativado') ? 'selected' : '' ?>>
            Ativado
        </option>
        <option value="Desativado"
            <?= (($clienteEdit['Status'] ?? '') === 'Desativado') ? 'selected' : '' ?>>
            Desativado
        </option>
    </select>

    <button type="submit">Salvar</button>
</form>

<br>

<table border="1" width="100%">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Celular</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
            <td><?= $c['IdCliente'] ?></td>
            <td><?= htmlspecialchars($c['NomeCompletoCliente']) ?></td>

            <td>
                <?php
                $telefoneLimpo = preg_replace('/\D/', '', $c['DocumentoCliente']);
                ?>
                <?= htmlspecialchars($c['DocumentoCliente']) ?>

                <?php if (strlen($telefoneLimpo) >= 10): ?>
                    <a href="https://web.whatsapp.com/send?phone=55<?= $telefoneLimpo ?>"
                       target="_blank"
                       title="Abrir WhatsApp"
                       style="margin-left:6px;text-decoration:none;">
                       🟢
                    </a>
                <?php endif; ?>
            </td>

            <td><?= $c['Status'] ?></td>

            <td class="acoes">
                <a href="clientes.php?edit=<?= $c['IdCliente'] ?>"
                   class="acao editar"
                   title="Editar cliente"></a>

                <?php if ($c['Status'] === 'Ativado'): ?>
                    <a href="clientes.php?desativar=<?= $c['IdCliente'] ?>"
                       class="acao desativar"
                       title="Desativar cliente"
                       onclick="return confirm('Desativar cliente?')"></a>
                <?php else: ?>
                    <a href="clientes.php?ativar=<?= $c['IdCliente'] ?>"
                       class="acao ativar"
                       title="Ativar cliente"></a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
/* =========================
   MÁSCARA DE CELULAR
========================= */
const docInput = document.getElementById('DocumentoCliente');

if (docInput) {
    docInput.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);

        if (v.length >= 2) {
            v = '(' + v.substring(0,2) + ') ' + v.substring(2);
        }
        if (v.length >= 10) {
            v = v.substring(0, 10) + '-' + v.substring(10);
        }

        this.value = v;
    });
}
</script>
