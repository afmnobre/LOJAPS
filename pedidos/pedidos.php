<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

require '../conexao.php';
require_once '../layout_header.php';
require_once '../nav.php';

date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d');
$dataSelecionada = $_GET['data'] ?? $hoje;

/* CLIENTES */
$clientes = $pdo->query("
    SELECT * FROM clientes
    WHERE Status = 'Ativado'
    ORDER BY NomeCompletoCliente
")->fetchAll(PDO::FETCH_ASSOC);

/* PRODUTOS */
$produtos = $pdo->query("
    SELECT * FROM produtos
    WHERE StatusProduto = 'Ativo'
")->fetchAll(PDO::FETCH_ASSOC);

/* PEDIDOS */
$pedidos = [];
$stmt = $pdo->prepare("
    SELECT IdPedido, IdCliente, ValorVariado, ObservacaoVariado, PedidoPago
    FROM pedidos
    WHERE DataPedido = ?
");
$stmt->execute([$dataSelecionada]);
foreach ($stmt as $p) {
    $pedidos[$p['IdCliente']] = $p;
}

/* ITENS */
$itensPedido = [];
$stmt = $pdo->prepare("
    SELECT pi.IdPedido, pi.IdProduto, pi.Quantidade
    FROM pedido_itens pi
    JOIN pedidos p ON p.IdPedido = pi.IdPedido
    WHERE p.DataPedido = ?
");
$stmt->execute([$dataSelecionada]);
foreach ($stmt as $i) {
    $itensPedido[$i['IdPedido']][$i['IdProduto']] = $i['Quantidade'];
}

/* DATAS PENDENTES */
$datasPendentes = $pdo->query("
    SELECT DISTINCT p.DataPedido
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
    WHERE p.PedidoPago = 0
      AND (pi.Quantidade > 0 OR p.ValorVariado > 0)
")->fetchAll(PDO::FETCH_COLUMN);

/* TOTAL DO DIA */
$stmtTotal = $pdo->prepare("
    SELECT COALESCE(SUM(total_pedido), 0)
    FROM (
        SELECT
            p.IdPedido,
            COALESCE(SUM(pi.Quantidade * pi.ValorUnitario), 0)
            + COALESCE(p.ValorVariado, 0) AS total_pedido
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
        WHERE p.DataPedido = ?
          AND p.PedidoPago = 1
        GROUP BY p.IdPedido
    ) t
");
$stmtTotal->execute([$dataSelecionada]);
$totalDia = $stmtTotal->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Pedidos</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<style>
input[type=number]{ width:70px; }

td.total-pago{
    background:#4caf50;
    color:#fff;
    font-weight:bold;
}

td.total-pendente{
    background:#f44336;
    color:#fff;
    font-weight:bold;
}

tr.pedido-pago{
    background:#e6f4ea;
}

#modalObs{
    position:fixed; inset:0;
    background:rgba(0,0,0,.5);
    display:none;
}
#modalObs div{
    background:#fff;
    width:400px;
    margin:10% auto;
    padding:10px;
}
@keyframes piscarBorda {
    0%   { box-shadow: 0 0 0 10px red; }
    50%  { box-shadow: 0 0 0 4px transparent; }
    100% { box-shadow: 0 0 0 10px red; }
}

.modal-alerta {
    animation: piscarBorda 0.8s infinite;
}
</style>
<div id="modalRecibo" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    z-index:9999;
">
    <div style="
        background:#fff;
        width:80%;
        max-width:600px;
        height:80%;
        margin:5% auto;
        padding:10px;
        position:relative;
        border-radius:6px;
    ">
        <button onclick="fecharRecibo()"
                style="position:absolute;top:5px;right:10px;">
            ✖
        </button>

        <iframe id="iframeRecibo"
                src=""
                style="width:100%;height:100%;border:none;">
        </iframe>
    </div>
</div>

</head>

<body>
<h2>Pedidos do Balcão</h2>

<input type="text" id="dataPedido">
<input type="text" id="filtroCliente" placeholder="Filtrar cliente">

<p>
💰 Total recebido:
<strong><?= number_format($totalDia,2,',','.') ?></strong>
</p>

<button type="button" onclick="salvarTudo()">💾 Salvar</button>
<span id="status-save"></span>

<table border="1" width="100%">
<thead>
<tr>
<th>Cliente</th>
<?php foreach($produtos as $p): ?>
<th title="R$ <?= number_format($p['ValorProduto'],2,',','.') ?>" style="cursor:help;">
<?= $p['NomeProduto'] ?> - <?= $p['Emoji'] ?>
</th>
<?php endforeach; ?>
<th>Variado - 💰</th>
<th>Total</th>
<th>Pago</th>
<th>Recibo</th>
</tr>
</thead>

<tbody>
<?php foreach($clientes as $c):
$id = $c['IdCliente'];
$pedido = $pedidos[$id] ?? null;
?>
<tr data-cliente="<?= strtolower($c['NomeCompletoCliente']) ?>">
<td><?= $c['NomeCompletoCliente'] ?></td>

<?php foreach($produtos as $p): ?>
<td>
<input type="number" min="0"
class="produto produto-<?= $id ?>"
data-valor="<?= $p['ValorProduto'] ?>"
value="<?= $itensPedido[$pedido['IdPedido'] ?? 0][$p['IdProduto']] ?? 0 ?>"
oninput="atualizarTotal(<?= $id ?>)">
</td>
<?php endforeach; ?>

<td>
<input type="number" step="0.01" id="variado-<?= $id ?>"
value="<?= $pedido['ValorVariado'] ?? 0 ?>"
oninput="atualizarTotal(<?= $id ?>)"
onblur="verificarVariado(<?= $id ?>)">
<button type="button" onclick="abrirObs(<?= $id ?>)">📝</button>
<input type="hidden" id="obs-<?= $id ?>" value="<?= htmlspecialchars($pedido['ObservacaoVariado'] ?? '') ?>">
</td>

<td id="total-<?= $id ?>">R$ 0,00</td>
<td>
<input type="checkbox" class="pedido-pago-check"
data-cliente="<?= $id ?>"
<?= (!empty($pedido['PedidoPago']))?'checked':'' ?>>
</td>
<td style="text-align:center">
<?php if (!empty($pedido['IdPedido'])): ?>
<a href="#"
   onclick="abrirRecibo(<?= (int)$pedido['IdPedido'] ?>); return false;"
   title="Abrir recibo do pedido">
   🧾
</a>
<?php else: ?>
    —
<?php endif; ?>
</td>
   
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div id="modalObs">
<div>
<textarea id="obsTexto" style="width:100%;height:100px"></textarea><br>
<button onclick="salvarObs()">Salvar</button>
<button onclick="fecharObs()">Cancelar</button>
</div>
</div>

<script>
const clientesComPedido = <?= json_encode(array_keys($pedidos)) ?>;

function atualizarTotal(id){
    let total = 0;

    document.querySelectorAll('.produto-'+id).forEach(el=>{
        total += (parseInt(el.value)||0) * parseFloat(el.dataset.valor);
    });

    total += parseFloat(document.getElementById('variado-'+id).value)||0;

    const totalCell = document.getElementById('total-'+id);
    const pago = document.querySelector('.pedido-pago-check[data-cliente="'+id+'"]').checked;

    totalCell.innerText = total.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
    totalCell.classList.remove('total-pago','total-pendente');

    if(total>0 && pago) totalCell.classList.add('total-pago');
    if(total>0 && !pago) totalCell.classList.add('total-pendente');
}

document.addEventListener('DOMContentLoaded',()=>{

<?php foreach($clientes as $c): ?>
atualizarTotal(<?= $c['IdCliente'] ?>);
<?php endforeach; ?>

const filtroInput = document.getElementById('filtroCliente');

/* 🔹 RESTAURA FILTRO */
const filtroSalvo = localStorage.getItem('filtroCliente') || '';
filtroInput.value = filtroSalvo;
aplicarFiltro(filtroSalvo);

/* 🔹 SALVA FILTRO AO DIGITAR */
filtroInput.addEventListener('input',function(){
    localStorage.setItem('filtroCliente', this.value.toLowerCase());
    aplicarFiltro(this.value.toLowerCase());
});

function aplicarFiltro(texto){
    document.querySelectorAll('tbody tr').forEach(tr=>{
        tr.style.display = tr.dataset.cliente.includes(texto) ? '' : 'none';
    });
}

});

document.querySelectorAll('.pedido-pago-check').forEach(chk=>{
    chk.addEventListener('change',function(){
        atualizarTotal(this.dataset.cliente);
    });
});

let clienteAtual=null;
function verificarVariado(id) {
    const valor = parseFloat(
        document.getElementById('variado-' + id).value
    ) || 0;

    // só abre se for > 0
    if (valor > 0) {
        abrirObs(id, true);
    }
}
function abrirObs(id, destacar=false){
    clienteAtual = id;

    const obsSalva = document.getElementById('obs-' + id).value.trim();

    if (obsSalva === '') {
        obsTexto.value = 'DESCREVA DO QUE SE TRATA O VALOR VARIADO!!!\nÉ IMPORTANTE ISSO SER UTILIZADO!!!';
        obsTexto.setSelectionRange(obsTexto.value.length, obsTexto.value.length);
    } else {
        obsTexto.value = obsSalva;
    }

    modalObs.style.display = 'block';

    const box = modalObs.querySelector('div');
    box.classList.remove('modal-alerta');
    if (destacar) box.classList.add('modal-alerta');
}

function fecharObs(){
    modalObs.style.display = 'none';
    modalObs.querySelector('div').classList.remove('modal-alerta');
}

function salvarObs(){
    document.getElementById('obs-'+clienteAtual).value=obsTexto.value;
    fecharObs();
}

flatpickr("#dataPedido",{
    locale:"pt",
    dateFormat:"Y-m-d",
    altInput:true,
    altFormat:"d/m/Y",
    defaultDate:"<?= $dataSelecionada ?>",
    onDayCreate:function(dObj,dStr,fp,dayElem){
        const data = dayElem.dateObj.toISOString().split('T')[0];
        if(<?= json_encode($datasPendentes) ?>.includes(data)){
            dayElem.style.background='#ffcccc';
            dayElem.title='Pedido não pago';
        }
    },
    onChange:function(sel,dateStr){
        window.location='?data='+dateStr;
    }
});

function salvarTudo() {

    // 🔹 garante que o filtro atual fique salvo
    localStorage.setItem(
        'filtroCliente',
        document.getElementById('filtroCliente').value.toLowerCase()
    );

    const dados = {
        data: "<?= $dataSelecionada ?>",
        clientes: []
    };

<?php foreach ($clientes as $c): ?>
(function () {

    const idCliente = <?= $c['IdCliente'] ?>;
    const produtos = [];

    document.querySelectorAll('.produto-<?= $c['IdCliente'] ?>').forEach((el, index) => {
        const qtd = parseInt(el.value) || 0;
        if (qtd > 0) {
            produtos.push({
                idProduto: <?= json_encode(array_column($produtos,'IdProduto')) ?>[index],
                quantidade: qtd,
                valor: parseFloat(el.dataset.valor)
            });
        }
    });

    const valorVariado = parseFloat(
        document.getElementById('variado-<?= $c['IdCliente'] ?>').value
    ) || 0;

    const pedidoPago = document
        .querySelector('.pedido-pago-check[data-cliente="<?= $c['IdCliente'] ?>"]')
        .checked ? 1 : 0;

    const observacao = document.getElementById('obs-<?= $c['IdCliente'] ?>').value || null;

    const tinhaPedidoAntes = clientesComPedido.includes(idCliente);

    if (produtos.length > 0 || valorVariado > 0) {
        dados.clientes.push({
            idCliente,
            produtos,
            variado: valorVariado,
            pedidoPago,
            observacao
        });
    } else if (tinhaPedidoAntes) {
        dados.clientes.push({
            idCliente,
            limpar: true
        });
    }

})();
<?php endforeach; ?>

    fetch('salvar_pedidos.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(dados)
    })
    .then(r=>r.json())
    .then(resp=>{
        document.getElementById('status-save').innerText = resp.mensagem;
        location.reload();
    })
    .catch(()=>{
        document.getElementById('status-save').innerText = 'Erro ao salvar';
    });
}
function abrirRecibo(idPedido) {
    const modal = document.getElementById('modalRecibo');
    const iframe = document.getElementById('iframeRecibo');

    iframe.src = 'recibo_pedido.php?id=' + idPedido;
    modal.style.display = 'block';
}

function fecharRecibo() {
    document.getElementById('modalRecibo').style.display = 'none';
    document.getElementById('iframeRecibo').src = '';
}
function abrirRecibo(idPedido) {
    window.open(
        'recibo_pedido.php?id=' + idPedido,
        'recibo_' + idPedido,
        'width=460,height=680,scrollbars=yes,resizable=no'
    );
}



</script>
</body>
</html>
