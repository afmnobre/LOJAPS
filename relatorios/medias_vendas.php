<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

require '../conexao.php';
require_once '../layout_header.php';
require_once '../nav.php';

date_default_timezone_set('America/Sao_Paulo');

/* ================= DIAS SELECIONADOS ================= */
$diasSelecionados = $_GET['dias'] ?? [1,2,3,4,5,6,7];
$diasSelecionados = array_map('intval', (array)$diasSelecionados);
$inDias = implode(',', $diasSelecionados);

/* ================= MÉDIA POR DIA DA SEMANA ================= */
$sqlDias = "
SELECT
    DAYOFWEEK(data) AS dia_semana,
    ROUND(AVG(total_dia),2) AS media
FROM (
    SELECT
        DATE(p.DataPedido) AS data,
        SUM(
            COALESCE(pi.Quantidade * pi.ValorUnitario,0)
            + COALESCE(p.ValorVariado,0)
        ) AS total_dia
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 1
    GROUP BY DATE(p.DataPedido)
) t
WHERE DAYOFWEEK(data) IN ($inDias)
GROUP BY DAYOFWEEK(data)
ORDER BY DAYOFWEEK(data)
";
$mediasDias = $pdo->query($sqlDias)->fetchAll(PDO::FETCH_ASSOC);

/* ================= MÉDIA POR SEMANA ================= */
$sqlSemana = "
SELECT ROUND(AVG(total_semana),2) AS media_semana
FROM (
    SELECT
        YEARWEEK(p.DataPedido) AS ano_semana,
        SUM(
            COALESCE(pi.Quantidade * pi.ValorUnitario,0)
            + COALESCE(p.ValorVariado,0)
        ) AS total_semana
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 1
    GROUP BY YEARWEEK(p.DataPedido)
) t
";
$mediaSemana = $pdo->query($sqlSemana)->fetchColumn();

/* ================= MÉDIA POR MÊS ================= */
$sqlMes = "
SELECT ROUND(AVG(total_mes),2) AS media_mes
FROM (
    SELECT
        DATE_FORMAT(p.DataPedido,'%Y-%m') AS ano_mes,
        SUM(
            COALESCE(pi.Quantidade * pi.ValorUnitario,0)
            + COALESCE(p.ValorVariado,0)
        ) AS total_mes
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 1
    GROUP BY ano_mes
) t
";
$mediaMes = $pdo->query($sqlMes)->fetchColumn();

/* ================= MÉDIA POR ANO ================= */
$sqlAno = "
SELECT ROUND(AVG(total_ano),2) AS media_ano
FROM (
    SELECT
        YEAR(p.DataPedido) AS ano,
        SUM(
            COALESCE(pi.Quantidade * pi.ValorUnitario,0)
            + COALESCE(p.ValorVariado,0)
        ) AS total_ano
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 1
    GROUP BY ano
) t
";
$mediaAno = $pdo->query($sqlAno)->fetchColumn();

$diasNomes = [
    1 => 'Domingo', 2 => 'Segunda', 3 => 'Terça',
    4 => 'Quarta', 5 => 'Quinta', 6 => 'Sexta', 7 => 'Sábado',
];

/* ================= VENDAS POR TIPO DE PAGAMENTO (MENSAL) ================= */
$sqlPagamentosMes = "
SELECT
    DATE_FORMAT(p.DataPedido, '%Y-%m') as mes_sort,
    DATE_FORMAT(p.DataPedido, '%m/%Y') as mes_referencia,
    fp.NomeFormaPagamento,
    SUM(pp.ValorPago) as total_pago
FROM pedido_pagamentos pp
JOIN pedidos p ON pp.IdPedido = p.IdPedido
JOIN formas_pagamento fp ON pp.IdFormaPagamento = fp.IdFormaPagamento
GROUP BY mes_sort, mes_referencia, fp.IdFormaPagamento, fp.NomeFormaPagamento
ORDER BY mes_sort DESC, fp.NomeFormaPagamento ASC
";
$pagamentosMes = $pdo->query($sqlPagamentosMes)->fetchAll(PDO::FETCH_ASSOC);

/* ================= RELATÓRIO LIGAMAGIC VS OUTROS (MENSAL) ================= */
// Aqui usamos o ID 5 conforme seu novo cadastro
$sqlLigaMagic = "
SELECT
    DATE_FORMAT(p.DataPedido, '%Y-%m') as mes_sort,
    DATE_FORMAT(p.DataPedido, '%m/%Y') as mes_referencia,
    SUM(CASE WHEN fp.IdFormaPagamento = 5 THEN pp.ValorPago ELSE 0 END) as total_ligamagic,
    SUM(CASE WHEN fp.IdFormaPagamento != 5 THEN pp.ValorPago ELSE 0 END) as total_outros
FROM pedido_pagamentos pp
JOIN pedidos p ON pp.IdPedido = p.IdPedido
JOIN formas_pagamento fp ON pp.IdFormaPagamento = fp.IdFormaPagamento
GROUP BY mes_sort, mes_referencia
ORDER BY mes_sort DESC
";
$relatorioLiga = $pdo->query($sqlLigaMagic)->fetchAll(PDO::FETCH_ASSOC);

/* ================= RELATÓRIO ANUAL DETALHADO ================= */
$anoAtual = date('Y');

$sqlAnual = "
SELECT
    m.mes AS numero_mes,
    COALESCE(SUM(t.total_pedido), 0) AS total_mes,
    COUNT(DISTINCT t.IdPedido) AS qtd_pedidos,
    COUNT(DISTINCT t.IdCliente) AS qtd_clientes,
    MIN(t.total_pedido) AS menor_pedido,
    MAX(t.total_pedido) AS maior_pedido,
    -- Média por dia (considerando dias reais com venda para não distorcer)
    ROUND(COALESCE(SUM(t.total_pedido) / NULLIF(COUNT(DISTINCT t.data_pura), 0), 0), 2) AS media_diaria
FROM (
    SELECT 1 AS mes UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
    UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12
) m
LEFT JOIN (
    SELECT
        p.IdPedido,
        p.IdCliente,
        p.DataPedido AS data_pura,
        MONTH(p.DataPedido) AS mes_pedido,
        (COALESCE(SUM(pi.Quantidade * pi.ValorUnitario), 0) + COALESCE(p.ValorVariado, 0)) AS total_pedido
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 1 AND YEAR(p.DataPedido) = $anoAtual
    GROUP BY p.IdPedido
) t ON m.mes = t.mes_pedido
GROUP BY m.mes
ORDER BY m.mes ASC
";

$relatorioAnual = $pdo->query($sqlAnual)->fetchAll(PDO::FETCH_ASSOC);

$mesesNomes = [
    1 => 'JANEIRO', 2 => 'FEVEREIRO', 3 => 'MARÇO', 4 => 'ABRIL',
    5 => 'MAIO', 6 => 'JUNHO', 7 => 'JULHO', 8 => 'AGOSTO',
    9 => 'SETEMBRO', 10 => 'OUTUBRO', 11 => 'NOVEMBRO', 12 => 'DEZEMBRO'
];

?>

<h2>Médias de Vendas</h2>

<form method="get" style="margin-bottom:20px;">
    <fieldset>
        <legend>Dias da Semana</legend>
        <?php foreach ($diasNomes as $num => $nome): ?>
            <label style="margin-right:10px;">
                <input type="checkbox" name="dias[]" value="<?= $num ?>"
                    <?= in_array($num, $diasSelecionados) ? 'checked' : '' ?>>
                <?= $nome ?>
            </label>
        <?php endforeach; ?>
        <button type="submit">Filtrar</button>
    </fieldset>
</form>

<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse;">
    <thead style="background: #eee;">
        <tr>
            <th>Dia da Semana</th>
            <th>Média de Vendas</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($mediasDias as $d): ?>
        <tr>
            <td><?= $diasNomes[$d['dia_semana']] ?></td>
            <td>R$ <?= number_format($d['media'],2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br>

<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse;">
    <thead style="background: #eee;">
        <tr>
            <th>Média por Semana</th>
            <th>Média por Mês</th>
            <th>Média por Ano</th>
        </tr>
    </thead>
    <tbody>
        <tr style="text-align:center;">
            <td>R$ <?= number_format($mediaSemana,2,',','.') ?></td>
            <td>R$ <?= number_format($mediaMes,2,',','.') ?></td>
            <td>R$ <?= number_format($mediaAno,2,',','.') ?></td>
        </tr>
    </tbody>
</table>

<br><hr>

<h2>Vendas Mensais por Forma de Pagamento</h2>
<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse;">
    <thead style="background: #f9f9f9;">
        <tr>
            <th>Mês/Ano</th>
            <th>Forma de Pagamento</th>
            <th>Total Recebido</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pagamentosMes as $pag): ?>
        <tr>
            <td><?= $pag['mes_referencia'] ?></td>
            <td><?= $pag['NomeFormaPagamento'] ?></td>
            <td align="right">R$ <?= number_format($pag['total_pago'], 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br>

<h2>Comparativo Mensal: Outros vs LigaMagic</h2>
<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse; font-family: sans-serif;">
    <thead>
        <tr style="background-color: #f2f2f2;">
            <th>Mês/Ano</th>
            <th>Total Outros Pagamentos</th>
            <th style="background-color: #ffb74d; color: #000;">Total Crédito - Ligamagic</th>
            <th>Total Geral</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($relatorioLiga as $rl):
            $totalGeral = $rl['total_ligamagic'] + $rl['total_outros'];
        ?>
        <tr style="text-align: center;">
            <td style="padding: 10px;"><?= $rl['mes_referencia'] ?></td>
            <td align="right">R$ <?= number_format($rl['total_outros'], 2, ',', '.') ?></td>
            <td style="background-color: #ffb74d; color: #000; font-weight: bold; border: 1px solid #e6a741;" align="right">
                R$ <?= number_format($rl['total_ligamagic'], 2, ',', '.') ?>
            </td>
            <td align="right"><strong>R$ <?= number_format($totalGeral, 2, ',', '.') ?></strong></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<br><hr>
<h2>Relatório Detalhado Anual (<?= date('Y') ?>)</h2>
<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse;">
    <thead style="background: #f9f9f9;">
        <tr>
            <th>Mês</th>
            <th>Média Valor / Dia</th>
            <th>Média / Semana</th>
            <th>Total Mês</th>
            <th>Média / Pedido</th>
            <th>Média / Cliente</th>
            <th>Menor Pedido</th>
            <th>Maior Pedido</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($relatorioAnual as $r):
            $nomeMes = $mesesNomes[$r['numero_mes']];
            $mediaSemana = $r['total_mes'] / 4.33;
            $mediaPedido = $r['qtd_pedidos'] > 0 ? ($r['total_mes'] / $r['qtd_pedidos']) : 0;
            $mediaCliente = $r['qtd_clientes'] > 0 ? ($r['total_mes'] / $r['qtd_clientes']) : 0;
        ?>
        <tr>
            <td><?= $nomeMes ?></td>
            <td align="right">R$ <?= number_format($r['media_diaria'], 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($mediaSemana, 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($r['total_mes'], 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($mediaPedido, 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($mediaCliente, 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($r['menor_pedido'] ?? 0, 2, ',', '.') ?></td>
            <td align="right">R$ <?= number_format($r['maior_pedido'] ?? 0, 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
