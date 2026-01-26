<?php
require '../conexao.php';

/* =========================
   CREATE / UPDATE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id     = $_POST['IdProduto'] ?? '';
    $nome   = $_POST['NomeProduto'];
    $valor  = $_POST['ValorProduto'];
    $status = $_POST['StatusProduto'];

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE produtos 
            SET NomeProduto = ?, ValorProduto = ?, StatusProduto = ?
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
    ->query("SELECT * FROM produtos ORDER BY NomeProduto")
    ->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   HTML
========================= */
require_once '../layout_header.php';
require_once '../nav.php';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Cadastro de Produtos</title>
</head>

<body>

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

<table border="1" width="100%">
<thead>
<tr>
    <th>ID</th>
    <th>Nome</th>
    <th>Valor</th>
    <th>Status</th>
    <th>Ações</th>
</tr>
</thead>

<tbody>
<?php foreach ($produtos as $p): ?>
<tr>
    <td><?= $p['IdProduto'] ?></td>
    <td><?= $p['NomeProduto'] ?></td>
    <td>R$ <?= number_format($p['ValorProduto'], 2, ',', '.') ?></td>
    <td><?= $p['StatusProduto'] ?></td>
    <td>
        <a href="produtos.php?edit=<?= $p['IdProduto'] ?>">Editar</a> |
        <a href="produtos.php?delete=<?= $p['IdProduto'] ?>"
           onclick="return confirm('Excluir produto?')">Excluir</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</body>
</html>
