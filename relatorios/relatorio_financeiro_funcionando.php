<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../conexao.php'; // NÃO MEXER
require_once '../layout_header.php';
require_once '../nav.php';

/* =========================
   FILTROS
========================= */
$mes          = $_GET['mes'] ?? '';
$cliente      = $_GET['cliente'] ?? '';
$status       = $_GET['status'] ?? '';
$dataInicio   = $_GET['data_inicio'] ?? '';
$dataFim      = $_GET['data_fim'] ?? '';

$where = [];
$params = [];

if ($cliente !== '') {
    $where[] = 'p.IdCliente = :cliente';
    $params[':cliente'] = $cliente;
}

if ($status === 'pago') {
    $where[] = 'p.PedidoPago = 1';
}
if ($status === 'nao') {
    $where[] = 'p.PedidoPago = 0';
}

if ($mes !== '') {
    $where[] = 'DATE_FORMAT(p.DataPedido, "%Y-%m") = :mes';
    $params[':mes'] = $mes;
} elseif ($dataInicio !== '' && $dataFim !== '') {
    $where[] = 'p.DataPedido BETWEEN :di AND :df';
    $params[':di'] = $dataInicio;
    $params[':df'] = $dataFim;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   CLIENTES (COMBO)
========================= */
$clientes = $pdo->query("
    SELECT IdCliente, NomeCompletoCliente
    FROM clientes
    WHERE Status='Ativado'
    ORDER BY NomeCompletoCliente
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TOTAIS
========================= */
$sqlTotais = "
SELECT
    SUM(
        CASE WHEN p.PedidoPago = 1
        THEN IFNULL(pi.TotalItens, 0) + IFNULL(p.ValorVariado, 0)
        ELSE 0 END
    ) AS TotalPago,

    SUM(
        CASE WHEN p.PedidoPago = 0
        THEN IFNULL(pi.TotalItens, 0) + IFNULL(p.ValorVariado, 0)
        ELSE 0 END
    ) AS TotalNaoPago

FROM pedidos p
LEFT JOIN (
    SELECT IdPedido, SUM(Quantidade * ValorUnitario) AS TotalItens
    FROM pedido_itens
    GROUP BY IdPedido
) pi ON pi.IdPedido = p.IdPedido
$whereSQL
";

$stmt = $pdo->prepare($sqlTotais);
$stmt->execute($params);
$totais = $stmt->fetch(PDO::FETCH_ASSOC);

$totalPago    = $totais['TotalPago'] ?? 0;
$totalNaoPago = $totais['TotalNaoPago'] ?? 0;
$totalGeral   = $totalPago + $totalNaoPago;

/* =========================
   PRODUTOS
========================= */
$wherePedidos = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$whereVariadoParts = $where;
foreach ($whereVariadoParts as &$cond) {
    $cond = str_replace(['p.', ':'], ['p.', ':v_'], $cond);
}
$whereVariado = $whereVariadoParts ? 'WHERE ' . implode(' AND ', $whereVariadoParts) . ' AND p.ValorVariado > 0' : 'WHERE p.ValorVariado > 0';

$paramsVariado = [];
foreach ($params as $key => $value) {
    $paramsVariado[':v_' . substr($key, 1)] = $value;
}

$sqlProdutos = "
(
    SELECT
        CONCAT(pr.Emoji, ' - ', pr.NomeProduto) AS Produto,
        SUM(i.Quantidade) AS Qtd,
        SUM(i.Quantidade * i.ValorUnitario) AS Total
    FROM pedidos p
    JOIN pedido_itens i ON i.IdPedido = p.IdPedido
    JOIN produtos pr ON pr.IdProduto = i.IdProduto
    $wherePedidos
    GROUP BY pr.IdProduto
)
UNION ALL
(
    SELECT
        '🛒 - VARIADO' AS Produto,
        COUNT(p.IdPedido) AS Qtd,
        SUM(p.ValorVariado) AS Total
    FROM pedidos p
    $whereVariado
)
ORDER BY Produto
";

$stmt = $pdo->prepare($sqlProdutos);
$stmt->execute(array_merge($params, $paramsVariado));
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TOP 5 CLIENTES - NOVO GRÁFICO
========================= */

// Mesma lógica para filtro, mas só para data/mês (não filtra cliente/status aqui)
$whereTopClientes = [];
$paramsTopClientes = [];

if ($mes !== '') {
    $whereTopClientes[] = 'DATE_FORMAT(p.DataPedido, "%Y-%m") = :top_mes';
    $paramsTopClientes[':top_mes'] = $mes;
} elseif ($dataInicio !== '' && $dataFim !== '') {
    $whereTopClientes[] = 'p.DataPedido BETWEEN :top_di AND :top_df';
    $paramsTopClientes[':top_di'] = $dataInicio;
    $paramsTopClientes[':top_df'] = $dataFim;
}

$whereTopSQL = $whereTopClientes ? 'WHERE ' . implode(' AND ', $whereTopClientes) : '';

$sqlTopClientes = "
SELECT c.NomeCompletoCliente, 
       SUM(IFNULL(pi.TotalItens, 0) + IFNULL(p.ValorVariado, 0)) AS TotalGasto
FROM pedidos p
JOIN clientes c ON c.IdCliente = p.IdCliente
LEFT JOIN (
    SELECT IdPedido, SUM(Quantidade * ValorUnitario) AS TotalItens
    FROM pedido_itens
    GROUP BY IdPedido
) pi ON pi.IdPedido = p.IdPedido
$whereTopSQL
GROUP BY c.IdCliente
ORDER BY TotalGasto DESC
LIMIT 5
";

$stmt = $pdo->prepare($sqlTopClientes);
$stmt->execute($paramsTopClientes);
$topClientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    background: #0f0f0f;
    color: #fff;
    font-family: Arial, sans-serif;
}

main {
    margin-top: 56px;
    padding: 20px;
}

/* Container principal com 3 colunas */
.form-container {
    display: grid;
    grid-template-columns: 2.5fr 1.5fr 2fr; /* filtro maior, totais médios, gráfico menor */
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    border: 2px solid #444;
    border-radius: 12px;
    background: #181818;
    box-sizing: border-box;
    align-items: start;
}

/* Coluna 1: formulário */
form.form-filters {
    display: grid;
    grid-template-columns: 1fr;
    gap: 18px;
}

/* Labels e inputs */
label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 15px;
    color: #ddd;
}

input,
select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid #444;
    background: #222;
    color: #eee;
    font-size: 14px;
    box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.8);
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box;
}

input:focus,
select:focus {
    outline: none;
    border-color: #b30000;
    box-shadow: 0 0 8px #b30000;
    background: #2a2a2a;
    color: #fff;
}

/* Botão */
button {
    padding: 12px 20px;
    background: #b30000;
    color: #fff;
    font-weight: 700;
    font-size: 16px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    box-shadow: 0 3px 8px rgba(179, 0, 0, 0.6);
    justify-self: start;
    align-self: end;
    width: fit-content;
    grid-column: span 2; /* botão ocupa as duas colunas do form */
}

button:hover {
    background: #e00000;
    box-shadow: 0 4px 12px rgba(224, 0, 0, 0.9);
}

/* Coluna 2: totais + gráfico pago/nao pago empilhados */
.col-totais {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Box totais */
.total-box {
    background: #1a1a1a;
    padding: 15px;
    border-radius: 8px;
    font-size: 16px;
}

.total-box p {
    margin: 8px 0;
    line-height: 1.3;
}

/* Canvas do gráfico pago/nao pago */
canvas#grafico {
    background: #1a1a1a;
    border-radius: 8px;
    width: 100% !important;
    height: 200px !important;
    display: block;
}

/* Coluna 3: gráfico top 5 + legenda em coluna */
.col-top5 {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    max-width: 100%;
}

/* Canvas gráfico Top 5 clientes */
canvas#graficoClientes {
    background: #1a1a1a;
    border-radius: 8px;
    width: 100% !important;
    max-width: 320px;
    height: 300px !important;
    display: block;
}

/* Legenda tabela lado direito do gráfico */
#legendaClientes {
    width: 100%;
    max-width: 320px;
    margin-top: 0;
    color: #fff;
    font-size: 14px;
    border-collapse: collapse;
    background: #222;
    border-radius: 6px;
    overflow: hidden;
}

#legendaClientes td,
#legendaClientes th {
    border-bottom: 1px solid #333;
    padding: 6px 10px;
}

#legendaClientes thead th {
    background: #333;
}

/* Caixa de cor na legenda */
.color-box {
    display: inline-block;
    width: 16px;
    height: 16px;
    margin-right: 8px;
    vertical-align: middle;
    border-radius: 3px;
}

/* Tabela produtos */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: #1a1a1a;
}

th,
td {
    padding: 10px;
    border-bottom: 1px solid #333;
}

th {
    background: #222;
}

/* Input filtro cliente com lista ajustada */
#clienteSelect option {
    padding: 4px;
}

/* Input de busca */
#buscaCliente {
    width: 100%;
    box-sizing: border-box;
}

/* Texto h2, h3 */
h2,
h3 {
    font-family: 'Press Start 2P', cursive;
    margin-bottom: 20px;
    font-weight: normal;
}
</style>

<h2>📊 Relatório Financeiro</h2>

<div class="form-container">

    <form method="get" class="form-filters" id="filtrosForm">
        <fieldset>
            <legend>Filtros de Pesquisa</legend>
            
            <div>
                <label for="mes">Mês</label>
                <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($mes) ?>">
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">Todos</option>
                    <option value="pago" <?= $status=='pago'?'selected':'' ?>>Pago</option>
                    <option value="nao" <?= $status=='nao'?'selected':'' ?>>Não pago</option>
                </select>
            </div>

            <div>
                <label for="buscaCliente">Buscar Cliente</label>
                <input type="text" id="buscaCliente" placeholder="Digite o nome">
            </div>

            <div>
                <label for="clienteSelect">Cliente</label>
                <select name="cliente" id="clienteSelect">
                    <option value="">Todos</option>
                    <?php foreach($clientes as $c): ?>
                    <option value="<?= $c['IdCliente'] ?>" <?= $cliente==$c['IdCliente']?'selected':'' ?>>
                        <?= htmlspecialchars($c['NomeCompletoCliente']) ?>
                    </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div>
                <label for="data_inicio">Data Inicial</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
            </div>

            <div>
                <label for="data_fim">Data Final</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
            </div>

            <button type="submit">🔍 Gerar Relatório</button>
        </fieldset>
    </form>

    <div class="col-totais" aria-label="Totais e gráfico pagamento">
        <div class="total-box" aria-live="polite" aria-atomic="true">
            <p>🟨 Pago: <strong>R$ <?= number_format($totalPago,2,',','.') ?></strong></p>
            <p>🟥 Não pago: <strong>R$ <?= number_format($totalNaoPago,2,',','.') ?></strong></p>
            <p>📦 Total Geral: <strong>R$ <?= number_format($totalGeral,2,',','.') ?></strong></p>
        </div>

        <canvas id="grafico" aria-label="Gráfico barras total pago e não pago" role="img"></canvas>
    </div>

    <div class="col-top5" aria-label="Top 5 clientes e legenda">
        <label for="graficoClientes">Top 5 Clientes (maior gasto)</label>
        <canvas id="graficoClientes" width="300" height="300" aria-describedby="legendaClientes" role="img"></canvas>
        <table id="legendaClientes" aria-describedby="graficoClientes">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<h3>📦 Produtos</h3>
<table>
<tr>
    <th>Produto</th>
    <th>Qtd</th>
    <th>Total</th>
</tr>
<?php foreach($produtos as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['Produto']) ?></td>
    <td><?= $p['Qtd'] ?></td>
    <td>R$ <?= number_format($p['Total'],2,',','.') ?></td>
</tr>
<?php endforeach ?>
</table>

<script>
document.getElementById('buscaCliente').addEventListener('input', function(){
    let t = this.value.toLowerCase();
    document.querySelectorAll('#clienteSelect option').forEach(o=>{
        if(!o.value) return;
        o.style.display = o.text.toLowerCase().includes(t) ? '' : 'none';
    });
});

/* GRÁFICO TOTAL PAGO / NÃO PAGO */
const ctx = document.getElementById('grafico').getContext('2d');
const pago = <?= (float)$totalPago ?>;
const nao  = <?= (float)$totalNaoPago ?>;
const max = Math.max(pago, nao, 1);

ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
ctx.fillStyle = '#ffeb3b';
ctx.fillRect(50, 150 - (pago/max)*120, 80, (pago/max)*120);
ctx.fillStyle = '#fff';
ctx.fillText('Pago', 70, 170);

ctx.fillStyle = '#f44336';
ctx.fillRect(180, 150 - (nao/max)*120, 80, (nao/max)*120);
ctx.fillStyle = '#fff';
ctx.fillText('Não Pago', 185, 170);

/* GRÁFICO TOP 5 CLIENTES - PIZZA */
const ctxClientes = document.getElementById('graficoClientes').getContext('2d');
const clientesNomes = <?= json_encode(array_column($topClientes, 'NomeCompletoCliente')) ?>;
const clientesTotais = <?= json_encode(array_map(fn($c) => (float)$c['TotalGasto'], $topClientes)) ?>;

const totalGastoClientes = clientesTotais.reduce((a,b) => a + b, 0);
const centerX = ctxClientes.canvas.width / 2;
const centerY = ctxClientes.canvas.height / 2;
const radius = Math.min(centerX, centerY) - 20;

const colors = ['#2196f3', '#f44336', '#ffeb3b', '#4caf50', '#9c27b0']; // 5 cores diferentes

let startAngle = 0;
ctxClientes.clearRect(0, 0, ctxClientes.canvas.width, ctxClientes.canvas.height);
ctxClientes.font = '12px Arial';
ctxClientes.textBaseline = 'middle';

clientesTotais.forEach((valor, i) => {
    const sliceAngle = (valor / totalGastoClientes) * 2 * Math.PI;
    const endAngle = startAngle + sliceAngle;

    // desenha fatia
    ctxClientes.beginPath();
    ctxClientes.moveTo(centerX, centerY);
    ctxClientes.fillStyle = colors[i % colors.length];
    ctxClientes.arc(centerX, centerY, radius, startAngle, endAngle);
    ctxClientes.closePath();
    ctxClientes.fill();

    startAngle = endAngle;
});

// Preenche a tabela da legenda
const tbody = document.querySelector('#legendaClientes tbody');
tbody.innerHTML = ''; // limpa conteúdo

clientesNomes.forEach((nome, i) => {
    const tr = document.createElement('tr');
    const tdColor = document.createElement('td');
    const tdValor = document.createElement('td');

    const colorBox = document.createElement('span');
    colorBox.className = 'color-box';
    colorBox.style.backgroundColor = colors[i % colors.length];
    tdColor.appendChild(colorBox);

    const nomeTexto = document.createTextNode(nome);
    tdColor.appendChild(nomeTexto);

    tdValor.style.textAlign = 'right';
    tdValor.textContent = clientesTotais[i].toLocaleString('pt-BR', {style:'currency', currency:'BRL'});

    tr.appendChild(tdColor);
    tr.appendChild(tdValor);

    tbody.appendChild(tr);
});
</script>
