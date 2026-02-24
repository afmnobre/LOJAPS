<?php
require '../conexao.php';

$idPedido = $_GET['id'] ?? 0;
$idPedido = (int)$idPedido;

if (!$idPedido) {
    die('Pedido inválido');
}

/* =========================
   BUSCA PEDIDO
========================= */
$stmt = $pdo->prepare("
    SELECT
        p.IdPedido,
        p.DataPedido,
        p.ValorVariado,
        p.ObservacaoVariado,
        p.PedidoPago,
        c.NomeCompletoCliente
    FROM pedidos p
    JOIN clientes c ON c.IdCliente = p.IdCliente
    WHERE p.IdPedido = ?
");
$stmt->execute([$idPedido]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido não encontrado');
}

/* =========================
   STATUS PAGAMENTO
========================= */
if (!empty($pedido['PedidoPago']) && $pedido['PedidoPago'] == 1) {
    $statusTexto = 'PAGO';
    $statusEmoji = '✔️';
} else {
    $statusTexto = 'NÃO PAGO';
    $statusEmoji = '❌';
}

/* =========================
   ITENS DO PEDIDO
========================= */
$stmt = $pdo->prepare("
    SELECT
        pr.NomeProduto,
        pi.Quantidade,
        pi.ValorUnitario
    FROM pedido_itens pi
    JOIN produtos pr ON pr.IdProduto = pi.IdProduto
    WHERE pi.IdPedido = ?
      AND pi.Quantidade > 0
");
$stmt->execute([$idPedido]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TOTAL
========================= */
$total = 0;
foreach ($itens as $i) {
    $total += $i['Quantidade'] * $i['ValorUnitario'];
}
$total += $pedido['ValorVariado'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Recibo Pedido <?= $pedido['IdPedido'] ?></title>

<style>
body{
    font-family: monospace;
    font-size: 12px;
    width: 280px;
    margin: auto;
}
h2, h3, p{
    margin: 4px 0;
    text-align: center;
}
hr{
    border: none;
    border-top: 1px dashed #000;
    margin: 6px 0;
}
.t-left{ text-align:left; }
.t-right{ text-align:right; }
table{
    width:100%;
    border-collapse: collapse;
}
td{
    padding: 2px 0;
}
.total{
    font-weight: bold;
    font-size: 14px;
}
.small{
    font-size: 11px;
}
.status{
    font-size: 13px;
    font-weight: bold;
    text-align: center;
}
.logo{
    text-align: center;
    margin-bottom: 6px;
}
.logo img{
    max-width: 120px;
    height: auto;
}
@page {
    size: 9cm 17cm;
    margin: 0;
}
body {
    width: 9cm;
    min-height: 17cm;
    margin: 0;
    padding: 6mm;
    font-family: Arial, sans-serif;
    font-size: 11px;
}
</style>

<script>
    // Dispara a impressão automaticamente ao abrir
    window.onload = function() {
        window.print();
    };
</script>

<style>
    @media print {
        /* Define a largura comum de bobinas térmicas (80mm ou 58mm) */
        body { width: 80mm; margin: 0; padding: 0; }
        @page { margin: 0; }
        .no-print { display: none; }
    }
</style>


</head>

<body>

<!-- LOGO -->
<div class="logo">
    <img src="/LOJAPS/LogoPS.jpg" alt="Player's Stop TCG">
</div>

<h2>PLAYER'S STOP TCG</h2>

<p class="small">
Endereço: R. Sílvio Barbosa, 157<br>
Vila Camargos, Guarulhos - SP<br>
CEP 07111-010
</p>

<p class="small">
CNPJ: 29.433.890/0001-07<br>
Tel: (11) 98814-5361
</p>

<hr>

<h3>RECIBO</h3>

<p>
Pedido Nº <?= $pedido['IdPedido'] ?><br>
<?= date('d/m/Y', strtotime($pedido['DataPedido'])) ?>
</p>

<hr>

<p class="status">
<?= $statusEmoji ?> <?= $statusTexto ?>
</p>

<hr>

<p class="t-left">
Cliente:<br>
<strong><?= htmlspecialchars($pedido['NomeCompletoCliente']) ?></strong>
</p>

<hr>

<table>
<?php foreach ($itens as $i): ?>
<tr>
    <td class="t-left">
        <?= htmlspecialchars($i['NomeProduto']) ?><br>
        <?= $i['Quantidade'] ?> x <?= number_format($i['ValorUnitario'],2,',','.') ?>
    </td>
    <td class="t-right">
        <?= number_format($i['Quantidade'] * $i['ValorUnitario'],2,',','.') ?>
    </td>
</tr>
<?php endforeach; ?>
</table>

<?php if ($pedido['ValorVariado'] > 0): ?>
<hr>
<p class="t-left">
Variado:<br>
<?= number_format($pedido['ValorVariado'],2,',','.') ?>
</p>

<?php if (!empty($pedido['ObservacaoVariado'])): ?>
<p class="t-left small">
Obs: <?= htmlspecialchars($pedido['ObservacaoVariado']) ?>
</p>
<?php endif; ?>
<?php endif; ?>

<hr>

<table>
<tr>
<td class="t-left total">TOTAL</td>
<td class="t-right total">
R$ <?= number_format($total,2,',','.') ?>
</td>
</tr>
</table>

<hr>

<p class="small">
Obrigado pela preferência!<br>
Boa sorte nos torneios 🎴
</p>

</body>
</html>
