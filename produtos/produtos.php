<?php
require '../conexao.php';

/* =========================
   CREATE / UPDATE / ORDENACAO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       SALVAR ORDENAÇÃO
    ========================= */
    if (isset($_POST['salvar_ordem'])) {

        if (isset($_POST['ordem']) && is_array($_POST['ordem'])) {

            foreach ($_POST['ordem'] as $idProduto => $ordem) {

                $stmt = $pdo->prepare("
                    UPDATE produtos
                    SET OrdemExibicao = ?
                    WHERE IdProduto = ?
                ");

                $stmt->execute([$ordem, $idProduto]);
            }
        }

        header('Location: produtos.php');
        exit;
    }

    $id     = $_POST['IdProduto'] ?? '';
    $nome   = $_POST['NomeProduto'];
    $valor  = $_POST['ValorProduto'];
    $status = $_POST['StatusProduto'];

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE produtos
            SET NomeProduto = ?,
                ValorProduto = ?,
                StatusProduto = ?
            WHERE IdProduto = ?
        ");
        $stmt->execute([$nome, $valor, $status, $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO produtos (NomeProduto, ValorProduto, StatusProduto)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$nome, $valor, $status]);
    }

    header('Location: produtos.php');
    exit;
}

/* =========================
   DELETE
========================= */
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE IdProduto = ?");
    $stmt->execute([$_GET['delete']]);

    header('Location: produtos.php');
    exit;
}

/* =========================
   EDIT
========================= */
$produtoEdit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE IdProduto = ?");
    $stmt->execute([$_GET['edit']]);
    $produtoEdit = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   LISTAGEM
========================= */
$produtos = $pdo
    ->query("SELECT * FROM produtos ORDER BY OrdemExibicao ASC, NomeProduto ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HTML
========================= */
require_once '../layout_header.php';
require_once '../nav.php';
?>

<h2>Cadastro de Produtos</h2>

<form method="post">
    <input type="hidden" name="IdProduto" value="<?= $produtoEdit['IdProduto'] ?? '' ?>">

    <label>Nome:</label>
    <input type="text" name="NomeProduto" required
           value="<?= $produtoEdit['NomeProduto'] ?? '' ?>">

    <label>Valor:</label>
    <input type="number" step="0.01" name="ValorProduto" required
           value="<?= $produtoEdit['ValorProduto'] ?? '' ?>">

    <label>Status:</label>
    <select name="StatusProduto">
        <option value="Ativo"
            <?= (($produtoEdit['StatusProduto'] ?? '') === 'Ativo') ? 'selected' : '' ?>>
            Ativo
        </option>
        <option value="Inativo"
            <?= (($produtoEdit['StatusProduto'] ?? '') === 'Inativo') ? 'selected' : '' ?>>
            Inativo
        </option>
    </select>

    <button type="submit">Salvar</button>
</form>

<form method="post">

<table border="1" width="100%">
<thead>
<tr>
    <th>ID</th>
    <th>Nome</th>
    <th>Valor</th>
    <th>Status</th>
    <th>Ordem</th>
    <th>Ações</th>
</tr>
</thead>

<tbody>
<?php
$total = count($produtos);
foreach ($produtos as $p):
?>
<tr>
    <td><?= $p['IdProduto'] ?></td>
    <td><?= $p['NomeProduto'] ?></td>
    <td>R$ <?= number_format($p['ValorProduto'], 2, ',', '.') ?></td>
    <td><?= $p['StatusProduto'] ?></td>

    <td>
        <select name="ordem[<?= $p['IdProduto'] ?>]">
            <?php for ($i = 1; $i <= $total; $i++): ?>
                <option value="<?= $i ?>"
                    <?= (($p['OrdemExibicao'] ?? 0) == $i) ? 'selected' : '' ?>>
                    <?= $i ?>
                </option>
            <?php endfor; ?>
        </select>
    </td>

    <td>
        <a href="produtos.php?edit=<?= $p['IdProduto'] ?>">Editar</a> |
        <a href="produtos.php?delete=<?= $p['IdProduto'] ?>"
           onclick="return confirm('Excluir produto?')">Excluir</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<br>
<button type="submit" name="salvar_ordem">Salvar Ordenação</button>

</form>

