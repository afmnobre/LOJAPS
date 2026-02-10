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

/* ================= NOME DOS DIAS ================= */
$diasNomes = [
    1 => 'Domingo',
    2 => 'Segunda',
    3 => 'Terça',
    4 => 'Quarta',
    5 => 'Quinta',
    6 => 'Sexta',
    7 => 'Sábado',
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

<!-- ================= TABELA MÉDIA POR DIA ================= -->
<table border="1" width="100%" cellpadding="6">
    <thead>
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

<!-- ================= RESUMO GERAL ================= -->
<table border="1" width="100%" cellpadding="6">
    <thead>
        <tr>
            <th>Média por Semana</th>
            <th>Média por Mês</th>
            <th>Média por Ano</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>R$ <?= number_format($mediaSemana,2,',','.') ?></td>
            <td>R$ <?= number_format($mediaMes,2,',','.') ?></td>
            <td>R$ <?= number_format($mediaAno,2,',','.') ?></td>
        </tr>
    </tbody>
</table>

