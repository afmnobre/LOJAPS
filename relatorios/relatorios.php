<?php
require '../conexao.php';
require_once '../layout_header.php';
require_once '../nav.php';

/* =========================
   MESES DISPONÍVEIS
========================= */
$stmtMeses = $pdo->query("
    SELECT DISTINCT DATE_FORMAT(DataPedido, '%Y-%m') AS mes
    FROM pedidos
    ORDER BY mes DESC
");
$mesesDisponiveis = $stmtMeses->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FILTROS
========================= */
$mesSelecionado = $_GET['mes'] ?? ($mesesDisponiveis[0]['mes'] ?? '');
$tipo = $_GET['tipo'] ?? 'quantidade';

/* =========================
   DADOS POR PRODUTO
========================= */
$dados = [];

if ($mesSelecionado) {
    $stmt = $pdo->prepare("
        SELECT 
            pr.NomeProduto,
            pr.Emoji,
            SUM(pi.Quantidade) AS quantidade,
            SUM(pi.Quantidade * pi.ValorUnitario) AS valor_total
        FROM pedidos p
        JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
        JOIN produtos pr ON pr.IdProduto = pi.IdProduto
        WHERE DATE_FORMAT(p.DataPedido, '%Y-%m') = ?
        GROUP BY pr.IdProduto
        ORDER BY valor_total DESC
    ");
    $stmt->execute([$mesSelecionado]);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ===== VALOR VARIADO ===== */
    $stmtVar = $pdo->prepare("
        SELECT 
            COUNT(*) AS quantidade,
            SUM(ValorVariado) AS valor_total
        FROM pedidos
        WHERE DATE_FORMAT(DataPedido, '%Y-%m') = ?
          AND ValorVariado > 0
    ");
    $stmtVar->execute([$mesSelecionado]);
    $variado = $stmtVar->fetch(PDO::FETCH_ASSOC);

    if ($variado && $variado['valor_total'] > 0) {
        $dados[] = [
            'NomeProduto' => 'VARIADO',
            'Emoji' => '🛒',
            'quantidade' => (int)$variado['quantidade'],
            'valor_total' => (float)$variado['valor_total']
        ];
    }
}

/* =========================
   TOTAIS
========================= */
$totalQuantidade = 0;
$totalValor = 0;

foreach ($dados as $d) {
    $totalQuantidade += $d['quantidade'];
    $totalValor += $d['valor_total'];
}

/* =========================
   DADOS DO GRÁFICO
========================= */
$labels = [];
$valores = [];

foreach ($dados as $d) {
    $labels[] = $d['Emoji'].' '.$d['NomeProduto'];
    $valores[] = ($tipo === 'quantidade') ? (int)$d['quantidade'] : (float)$d['valor_total'];
}
?>

<div class="container">

<h2>📊 Relatório de Produtos</h2>

<form method="get" class="filtros" style="display:flex; gap:20px; margin-bottom:20px">
    <label>
        Mês:<br>
        <select name="mes" onchange="this.form.submit()">
            <?php foreach ($mesesDisponiveis as $m): ?>
                <option value="<?= $m['mes'] ?>" <?= $m['mes']===$mesSelecionado?'selected':'' ?>>
                    <?= date('m/Y', strtotime($m['mes'].'-01')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Exibir por:<br>
        <select name="tipo" onchange="this.form.submit()">
            <option value="quantidade" <?= $tipo==='quantidade'?'selected':'' ?>>Quantidade</option>
            <option value="valor" <?= $tipo==='valor'?'selected':'' ?>>Valor (R$)</option>
        </select>
    </label>
</form>

<h3>
💰 Total Geral:
<?php if ($tipo === 'quantidade'): ?>
    <?= $totalQuantidade ?> itens
<?php else: ?>
    R$ <?= number_format($totalValor, 2, ',', '.') ?>
<?php endif; ?>
</h3>

<!-- CONTROLE DE TAMANHO DO GRÁFICO -->
<div style="width:50%; height:50%; margin-bottom:30px">
    <canvas id="graficoProdutos"></canvas>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Produto</th>
            <th>Quantidade</th>
            <th>Valor Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($dados as $d): ?>
        <tr>
            <td><?= $d['Emoji'] ?> <?= htmlspecialchars($d['NomeProduto']) ?></td>
            <td><?= (int)$d['quantidade'] ?></td>
            <td>R$ <?= number_format($d['valor_total'],2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('graficoProdutos'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: '<?= $tipo==="valor" ? "Valor (R$)" : "Quantidade" ?>',
            data: <?= json_encode($valores) ?>,
            backgroundColor: '#ffcb05'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

<?php require '../layout_footer.php'; ?>
