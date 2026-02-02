<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require '../../conexao.php';
require_once '../../layout_header.php';
require_once '../../nav.php';

/* =========================
   BUSCA TODOS PEDIDOS EM ABERTO
========================= */
$pedidos = $pdo->query("
SELECT
    p.IdPedido,
    p.DataPedido,
    c.NomeCompletoCliente,
    p.ObservacaoVariado,

    /* TOTAL DO PEDIDO */
    (
        COALESCE(SUM(pi.Quantidade * pi.ValorUnitario),0)
        + COALESCE(p.ValorVariado,0)
    ) AS TotalPedido,

    /* LISTA DE PRODUTOS DO PEDIDO */
    GROUP_CONCAT(
        CONCAT(
            pr.NomeProduto,' (',pi.Quantidade,'x)'
        )
        SEPARATOR ' | '
    ) AS ProdutosPedido

FROM pedidos p
JOIN clientes c ON c.IdCliente = p.IdCliente
LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
LEFT JOIN produtos pr ON pr.IdProduto = pi.IdProduto

WHERE p.PedidoPago = 0
  AND (
        pi.Quantidade > 0
        OR p.ValorVariado > 0
      )

GROUP BY p.IdPedido
ORDER BY c.NomeCompletoCliente ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>

<h2>🚨 Pedidos em Aberto (Não pagos)</h2>

<table border="1" width="100%">
<thead>
<tr>
<th>Nº Pedido</th>
<th>Data</th>
<th>Cliente</th>
<th>Produtos</th>
<th>Observação</th>
<th>Total</th>
<th>Ações</th>
</tr>
</thead>

<tbody>
<?php foreach($pedidos as $p): ?>
<tr>
<td><?= $p['IdPedido'] ?></td>
<td><?= date('d/m/Y', strtotime($p['DataPedido'])) ?></td>
<td><?= htmlspecialchars($p['NomeCompletoCliente']) ?></td>

<td style="font-size:13px">
    <?= $p['ProdutosPedido'] ?: '—' ?>
</td>

<td><?= htmlspecialchars($p['ObservacaoVariado'] ?? '-') ?></td>

<td>
    <strong>
        R$ <?= number_format($p['TotalPedido'],2,',','.') ?>
    </strong>
</td>

<td style="text-align:center">

<a href="#"
onclick="abrirRecibo(<?= $p['IdPedido'] ?>);return false;"
title="Recibo">🧾</a>

&nbsp;

<a href="marcar_pago.php?id=<?= $p['IdPedido'] ?>"
onclick="return confirm('Marcar pedido como PAGO?')"
title="Marcar como pago">✅</a>

&nbsp;

<a href="excluir_pedido.php?id=<?= $p['IdPedido'] ?>"
onclick="return confirm('Excluir pedido?')"
title="Excluir pedido">🗑️</a>

</td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

<script>
function abrirRecibo(id){
    window.open(
        '../recibo_pedido.php?id='+id,
        'recibo_'+id,
        'width=460,height=680,scrollbars=yes'
    );
}
</script>

