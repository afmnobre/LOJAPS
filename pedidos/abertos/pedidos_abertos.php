<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit;
}

require '../../conexao.php';
require_once '../../layout_header.php';
require_once '../../nav.php';

$formas = $pdo->query("SELECT * FROM formas_pagamento WHERE Status = 'Ativo' ORDER BY IdFormaPagamento ASC")->fetchAll(PDO::FETCH_ASSOC);

/* BUSCA PEDIDOS EM ABERTO */
$sql = "
SELECT
    p.IdPedido, p.IdCliente, p.DataPedido, c.NomeCompletoCliente,
    p.ObservacaoVariado, p.ValorVariado,
    COALESCE(SUM(pi.Quantidade * pi.ValorUnitario),0) AS TotalItens,
    (COALESCE(SUM(pi.Quantidade * pi.ValorUnitario),0) + COALESCE(p.ValorVariado,0)) AS TotalPedido,
    GROUP_CONCAT(CONCAT(pr.NomeProduto,' (',pi.Quantidade,'x)') SEPARATOR ' | ') AS ProdutosPedido
FROM pedidos p
JOIN clientes c ON c.IdCliente = p.IdCliente
LEFT JOIN pedido_itens pi ON pi.IdPedido = p.IdPedido
LEFT JOIN produtos pr ON pr.IdProduto = pi.IdProduto
WHERE p.PedidoPago = 0
GROUP BY p.IdPedido
HAVING TotalPedido > 0
ORDER BY c.NomeCompletoCliente ASC, p.DataPedido ASC
";
$pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$agrupados = [];
foreach ($pedidos as $p) {
    $nome = $p['NomeCompletoCliente'];
    if (!isset($agrupados[$nome])) {
        $agrupados[$nome] = ['pedidos' => [], 'subtotal' => 0];
    }
    $agrupados[$nome]['pedidos'][] = $p;
    $agrupados[$nome]['subtotal'] += $p['TotalPedido'];
}
?>

<style>
    .modal-pagamento {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.95);
        z-index: 10000;
        justify-content: center;
        align-items: center;
        pointer-events: auto;
    }
    .modal-content {
        background: #1a1a1a;
        padding: 25px;
        border-radius: 12px;
        width: 420px;
        max-width: 95%;
        border: 1px solid #333;
        color: #fff;
    }
    .linha-pagamento {
        margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;
        background: #252525; padding: 8px; border-radius: 6px;
    }
    .input-pagamento {
        background: #333; color: #fff; border: 1px solid #444;
        padding: 8px; border-radius: 4px; width: 100px; text-align: right;
    }
    .btn-auto {
        background: #444; color: #fff; border: 1px solid #666; padding: 4px 8px;
        border-radius: 4px; cursor: pointer; font-size: 11px;
    }
    .table-pedidos { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
    .row-cliente-header {
        background-color: #555 !important;
        color: #fff !important;
        border-top: 4px solid #ff0000 !important;
    }
    .row-cliente-header td { padding: 12px !important; font-weight: bold; font-size: 16px; }
    .text-devido { float: right; color: #ffeb3b !important; font-weight: bold; }
</style>

<h2>🚨 Pedidos em Aberto</h2>

<table class="table-pedidos">
    <thead>
        <tr>
            <th>Data</th>
            <th>Nº</th>
            <th>Itens do Pedido</th>
            <th>Vlr Variado</th>
            <th>Obs. Variado</th>
            <th>Valor Pedido</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($agrupados as $nomeCliente => $dados): ?>
            <tr class="row-cliente-header">
                <td colspan="7">
                    👤 <?= htmlspecialchars($nomeCliente) ?>
                    <span class="text-devido">VALOR TOTAL DEVIDO: R$ <?= number_format($dados['subtotal'], 2, ',', '.') ?></span>
                </td>
            </tr>
            <?php foreach($dados['pedidos'] as $p): ?>
            <tr>
                <td align="center"><?= date('d/m/Y', strtotime($p['DataPedido'])) ?></td>
                <td align="center">#<?= $p['IdPedido'] ?></td>
                <td><?= $p['ProdutosPedido'] ?: 'Item avulso' ?></td>
                <td align="right">R$ <?= number_format($p['ValorVariado'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($p['ObservacaoVariado'] ?? '-') ?></td>
                <td align="right"><strong>R$ <?= number_format($p['TotalPedido'], 2, ',', '.') ?></strong></td>
                <td align="center">
                   <a href="#" onclick="abrirRecibo(<?= (int)$p['IdPedido'] ?>); return false;" title="Abrir recibo">🧾</a>
                    &nbsp;
                    <a href="#" onclick="abrirPagamento(<?= $p['IdPedido'] ?>, <?= $p['TotalPedido'] ?>, '<?= addslashes($nomeCliente) ?>'); return false;">✅</a>
                    &nbsp;
                    <a href="excluir_pedido.php?id=<?= $p['IdPedido'] ?>" onclick="return confirm('Excluir?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>

<div id="modalPagamento" class="modal-pagamento">
    <div class="modal-content">
        <h3 id="pag-nome-cliente" style="color:#ff9800;">Pagamento</h3>
        <p style="color: #aaa;">Total Pedido: <span id="pag-total-exibir" style="color:#fff; font-weight:bold;">R$ 0,00</span></p>
        <hr style="border: 0; border-top: 1px solid #333; margin: 15px 0;">

        <div id="lista-formas">
            <?php foreach($formas as $f): ?>
            <div class="linha-pagamento">
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" class="check-forma" data-id="<?= $f['IdFormaPagamento'] ?>" onclick="autoDividir()">
                    <label style="color:#fff; font-size:14px;"><?= $f['NomeFormaPagamento'] ?></label>
                </div>
                <div style="display:flex; align-items:center; gap:5px;">
                    <input type="number" step="0.01" class="input-pagamento" data-id="<?= $f['IdFormaPagamento'] ?>" id="input-<?= $f['IdFormaPagamento'] ?>" oninput="validarRateio()">
                    <button type="button" class="btn-auto" onclick="preencherRestante(<?= $f['IdFormaPagamento'] ?>)">Max</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="box-restante" style="background:#000; padding:15px; margin-top:15px; border-radius:8px; text-align:center; border: 1px solid #333;">
            <span style="color:#aaa">Restante:</span> <br>
            <span id="pag-restante" style="font-size:18px; font-weight:bold;">R$ 0,00</span>
        </div>

        <div style="margin-top:20px; display:flex; gap:10px;">
            <button type="button" onclick="fecharPagamento()" style="flex:1; padding:12px; background:#444; color:#fff; border:none; border-radius:4px; cursor:pointer;">Cancelar</button>
            <button type="button" id="btn-finalizar-pag" onclick="finalizarPagamento()" disabled style="flex:2; padding:12px; background:#4caf50; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:bold;">FINALIZAR</button>
        </div>
    </div>
</div>

<div id="modalRecibo" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:9999;">
    <div style="background:#fff; width:10.5cm; max-width:95%; height:85vh; padding:15px; border-radius:4px; box-shadow:0 5px 25px rgba(0,0,0,.6); display:flex; flex-direction:column; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
            <span style="font-weight:bold; color:#333; font-family:sans-serif;">Prévia do Recibo</span>
            <button onclick="fecharRecibo()" style="cursor:pointer; background:#f44336; color:#fff; border:none; padding:4px 12px; border-radius:3px; font-weight:bold; font-size:16px;">×</button>
        </div>
        <iframe id="iframeRecibo" src="" style="width:100%; flex-grow:1; border:1px solid #ddd; background:#eee;"></iframe>
        <div style="margin-top:10px; text-align:center;">
            <button onclick="document.getElementById('iframeRecibo').contentWindow.print()" style="padding:10px 25px; cursor:pointer; background:#4caf50; color:white; border:none; border-radius:4px; font-weight:bold; font-family:sans-serif;">🖨️ Imprimir Recibo</button>
        </div>
    </div>
</div>


<script>
var pedidoAtual = { id: 0, total: 0 };

window.abrirPagamento = function(id, total, nome) {
    pedidoAtual.id = id;
    pedidoAtual.total = parseFloat(total);
    document.getElementById('pag-nome-cliente').innerText = nome;
    document.getElementById('pag-total-exibir').innerText = 'R$ ' + pedidoAtual.total.toLocaleString('pt-br', {minimumFractionDigits: 2});
    document.querySelectorAll('.input-pagamento').forEach(function(i) { i.value = ''; });
    document.querySelectorAll('.check-forma').forEach(function(c) { c.checked = false; });
    document.getElementById('modalPagamento').style.display = 'flex';
    validarRateio();
};

window.fecharPagamento = function() {
    document.getElementById('modalPagamento').style.display = 'none';
};

window.autoDividir = function() {
    var selecionados = document.querySelectorAll('.check-forma:checked');
    document.querySelectorAll('.input-pagamento').forEach(function(i) { i.value = ''; });
    if (selecionados.length === 0) { validarRateio(); return; }

    var valorCada = Math.floor((pedidoAtual.total / selecionados.length) * 100) / 100;
    var somaDistribuida = 0;

    selecionados.forEach(function(cb, index) {
        var id = cb.dataset.id;
        var input = document.getElementById('input-' + id);
        if (index === selecionados.length - 1) {
            input.value = (pedidoAtual.total - somaDistribuida).toFixed(2);
        } else {
            input.value = valorCada.toFixed(2);
            somaDistribuida += valorCada;
        }
    });
    validarRateio();
};

window.preencherRestante = function(idForma) {
    var somadoOutros = 0;
    document.querySelectorAll('.input-pagamento').forEach(function(i) {
        if(parseInt(i.dataset.id) !== idForma) { somadoOutros += parseFloat(i.value) || 0; }
    });
    var faltava = Math.max(0, (pedidoAtual.total - somadoOutros));
    document.getElementById('input-' + idForma).value = faltava.toFixed(2);
    validarRateio();
};

window.validarRateio = function() {
    var somado = 0;
    document.querySelectorAll('.input-pagamento').forEach(function(i) { somado += parseFloat(i.value) || 0; });
    var restante = (pedidoAtual.total - somado).toFixed(2);
    var elRestante = document.getElementById('pag-restante');
    var btnFinalizar = document.getElementById('btn-finalizar-pag');
    if(elRestante) {
        elRestante.innerText = 'R$ ' + Math.abs(restante).toLocaleString('pt-br', {minimumFractionDigits: 2});
        var pronto = Math.abs(restante) < 0.01;
        btnFinalizar.disabled = !pronto;
        elRestante.style.color = pronto ? '#4caf50' : '#ff5252';
    }
};

window.finalizarPagamento = function() {
    var btn = document.getElementById('btn-finalizar-pag');
    var pagamentos = [];

    // Coleta os pagamentos
    document.querySelectorAll('.input-pagamento').forEach(function(i) {
        var v = parseFloat(i.value) || 0;
        if(v > 0) pagamentos.push({ idForma: i.dataset.id, valor: v });
    });

    if(pagamentos.length === 0) {
        alert("Por favor, insira pelo menos um valor de pagamento.");
        return;
    }

    // --- FEEDBACK VISUAL: PROCESSANDO ---
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processando...';
    btn.style.backgroundColor = '#888';
    btn.style.cursor = 'not-allowed';

    fetch('processar_pagamento_aberto.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ idPedido: pedidoAtual.id, pagamentos: pagamentos })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if(res.sucesso) {
            // Se o seu sistema usa SweetAlert ou algo similar, pode usar aqui
            window.location.reload();
        } else {
            alert('Erro: ' + res.mensagem);
            // Se der erro, volta o botão ao normal para tentar de novo
            btn.disabled = false;
            btn.innerHTML = 'FINALIZAR';
            btn.style.backgroundColor = '#4caf50';
            btn.style.cursor = 'pointer';
        }
    })
    .catch(function(err) {
        console.error(err);
        alert("Erro de comunicação com o servidor.");
        btn.disabled = false;
        btn.innerHTML = 'FINALIZAR';
    });
};

// --- FUNÇÕES DO RECIBO ---
window.abrirRecibo = function(idPedido) {
    const modal = document.getElementById('modalRecibo');
    const iframe = document.getElementById('iframeRecibo');
    if (!modal || !iframe) return;
    iframe.src = '../recibo_pedido.php?id=' + idPedido;
    modal.style.display = 'block';
};

window.fecharRecibo = function() {
    const modal = document.getElementById('modalRecibo');
    const iframe = document.getElementById('iframeRecibo');
    if (modal) modal.style.display = 'none';
    if (iframe) iframe.src = '';
};

</script>
