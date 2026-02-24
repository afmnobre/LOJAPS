<?php
	/**
	 * ============================================================
	 * BLOCO PHP: LÓGICA DE NEGÓCIO E CONSULTAS
	 * ============================================================
	 */

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

	/* PRODUTOS (AGORA ORDENADO CORRETAMENTE) */
	$produtos = $pdo->query("
		SELECT * FROM produtos
		WHERE StatusProduto = 'Ativo'
		ORDER BY OrdemExibicao ASC
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

	/* FORMAS DE PAGAMENTO ATIVAS */
	$formasPagamento = $pdo->query("
    		SELECT IdFormaPagamento, NomeFormaPagamento
    		FROM formas_pagamento
    		WHERE Status = 'Ativo'
    		ORDER BY NomeFormaPagamento
	")->fetchAll(PDO::FETCH_ASSOC);

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

		<h2>Pedidos do Balcão</h2>

		<input type="text" id="dataPedido">
		<input type="text" id="filtroCliente" placeholder="Filtrar cliente">

		<p>
			💰 Total recebido:
			<strong><?= number_format($totalDia,2,',','.') ?></strong>
		</p>

		<button type="button" id="btnSalvar">💾 Salvar</button>
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
                        <th style="width: 40px; text-align: center;">Pago</th>
                        <th style="width: 40px; text-align: center;">Recibo</th>
					</tr>
				</thead>

			<tbody>
				<?php foreach($clientes as $c):
					$id = $c['IdCliente'];
					$pedido = $pedidos[$id] ?? null;
					$totalLinha = 0;

					if ($pedido) {
						foreach ($produtos as $p) {
							$qtd = $itensPedido[$pedido['IdPedido'] ?? 0][$p['IdProduto']] ?? 0;
							$totalLinha += $qtd * $p['ValorProduto'];
						}
						$totalLinha += $pedido['ValorVariado'] ?? 0;
					}
					$pagoLinha = !empty($pedido['PedidoPago']) ? 1 : 0;
				?>
				<tr
					data-id="<?= !empty($pedido['IdPedido']) ? (int)$pedido['IdPedido'] : '' ?>"
					data-cliente="<?= strtolower($c['NomeCompletoCliente']) ?>"
					data-total="<?= $totalLinha ?>"
					data-pago="<?= $pagoLinha ?>"
				>
					<td><?= $c['NomeCompletoCliente'] ?></td>
					<?php foreach($produtos as $p): ?>
						<td>
                            <input type="number"
                                min="0"
                                class="produto produto-<?= $id ?>"
                                data-valor="<?= $p['ValorProduto'] ?>"
                                data-id="<?= $p['IdProduto'] ?>"  value="<?= $itensPedido[$pedido['IdPedido'] ?? 0][$p['IdProduto']] ?? 0 ?>"
                                oninput="atualizarTotal(<?= $id ?>)"
                                onblur="ordenarTabela()"
                            >
						</td>
					<?php endforeach; ?>
					<td>
                        <input type="number"
                            step="0.01"
                            id="variado-<?= $id ?>"
                            value="<?= $pedido['ValorVariado'] ?? 0 ?>"
                            oninput="atualizarTotal(<?= $id ?>)"
                            onblur="finalizarEdicaoVariado(<?= $id ?>)"
                            style="width: 70px; text-align: right;">
						<button type="button" onclick="abrirObs(<?= $id ?>)">📝</button>
						<input type="hidden"
							   id="obs-<?= $id ?>"
							   value="<?= htmlspecialchars($pedido['ObservacaoVariado'] ?? '') ?>">
					</td>
					<td id="total-<?= $id ?>">R$ 0,00</td>
					<td>
						<input type="checkbox"
							   class="pedido-pago-check"
							   data-cliente="<?= $id ?>"
							   <?= (!empty($pedido['PedidoPago']))?'checked':'' ?>>
					</td>
					<td style="text-align:center">
						<?php if (!empty($pedido['IdPedido']) && !empty($pedido['PedidoPago'])): ?>
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


		<div id="modalRecibo" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:9999;">
			<div style="
				background: #fff;
				width: 10.5cm;
				max-width: 95%;
				height: 85vh;
				padding: 15px;
				border-radius: 4px;
				box-shadow: 0 5px 25px rgba(0,0,0,.6);
				display: flex;
				flex-direction: column;

				/* Centralização Absoluta */
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
			">

				<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">
					<span style="font-weight: bold; color: #333; font-family: sans-serif;">Prévia do Recibo</span>
					<button onclick="fecharRecibo()" style="cursor:pointer; background:#f44336; color:#fff; border:none; padding: 4px 12px; border-radius: 3px; font-weight: bold; font-size: 16px;">×</button>
				</div>

				<iframe id="iframeRecibo" src="" style="width:100%; flex-grow: 1; border: 1px solid #ddd; background: #eee;"></iframe>

				<div style="margin-top: 10px; text-align: center;">
					<button onclick="document.getElementById('iframeRecibo').contentWindow.print()" style="padding: 10px 25px; cursor: pointer; background: #4caf50; color: white; border: none; border-radius: 4px; font-weight: bold; font-family: sans-serif;">
						🖨️ Imprimir Recibo
					</button>
				</div>
			</div>
		</div>

		<div id="modalPagamento" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999;">
			<div style="background:#fff; width:500px; margin:8% auto; padding:20px; border-radius:6px; color:black;">
				<h3>Tipo de Pagamento</h3>
				<table width="100%">
					<tr>
						<th width="30">Sel.</th> <th align="left">Forma</th>
						<th align="right">Valor</th>
					</tr>

					<?php foreach($formasPagamento as $forma): ?>
						<tr>
							<td>
								<input type="checkbox" class="pg-check" data-id="<?= $forma['IdFormaPagamento'] ?>">
							</td>
							<td><?= htmlspecialchars($forma['NomeFormaPagamento']) ?></td>
							<td>
								<input
									type="number"
									step="0.01"
									class="pg-input"
									id="input-pg-<?= $forma['IdFormaPagamento'] ?>"
									data-id="<?= $forma['IdFormaPagamento'] ?>"
								>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<br>
				<button onclick="confirmarPagamento()">Salvar</button>
				<button onclick="fecharModalPagamento()">Cancelar</button>
			</div>
		</div>

        <div id="modalObs">
            <div>
                <textarea id="obsTexto" style="width:100%;height:100px"></textarea><br>
                <button onclick="salvarObs()">Salvar</button>
                <button onclick="fecharObs()">Cancelar</button>
            </div>
        </div>


			<script>
				/* Clientes que já possuem pedido carregados do PHP */
				const clientesComPedido = <?= json_encode(array_keys($pedidos)) ?>;

				/* ===============================
				   VARIÁVEIS GLOBAIS PAGAMENTO
				=============================== */
				let clientePagamentoAtual = null;
				var totalPagamentoAtual = totalPagamentoAtual || 0;
				let pagamentosSelecionados = [];

				/* Função para atualizar o total financeiro e status visual da linha do cliente */
				function atualizarTotal(id){
					let total = 0;
					document.querySelectorAll('.produto-'+id).forEach(el=>{
						total += (parseInt(el.value)||0) * parseFloat(el.dataset.valor);
					});
					total += parseFloat(document.getElementById('variado-'+id).value)||0;

					const totalCell = document.getElementById('total-'+id);
					const checkboxPago = document.querySelector('.pedido-pago-check[data-cliente="'+id+'"]');
					const pago = checkboxPago.checked;

					totalCell.innerText = total.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
					totalCell.classList.remove('total-pago','total-pendente');

					if(total>0 && pago) totalCell.classList.add('total-pago');
					if(total>0 && !pago) totalCell.classList.add('total-pendente');

					const tr = totalCell.closest('tr');
					tr.dataset.total = total;
					tr.dataset.pago = pago ? '1' : '0';
				}

				/* Inicialização ao carregar página */
				document.addEventListener('DOMContentLoaded',()=>{
					<?php foreach($clientes as $c): ?>
					atualizarTotal(<?= $c['IdCliente'] ?>);
					<?php endforeach; ?>

					ordenarTabela();

					const filtroInput = document.getElementById('filtroCliente');
					const filtroSalvo = localStorage.getItem('filtroCliente') || '';
					filtroInput.value = filtroSalvo;
					aplicarFiltro(filtroSalvo);

					filtroInput.addEventListener('input',function(){
						localStorage.setItem('filtroCliente', this.value.toLowerCase());
						aplicarFiltro(this.value.toLowerCase());
					});

                function aplicarFiltro(texto){
                    document.querySelectorAll('tbody tr').forEach(tr=>{
                        const cliente = tr.dataset.cliente || '';
                        tr.style.display = cliente.includes(texto) ? '' : 'none';
                    });
                }

				});

				/* Controle checkbox pago (abre modal) */
				document.addEventListener('change', function(e){

					if(!e.target.classList.contains('pedido-pago-check')) return;

					const checkbox = e.target;
					const idCliente = checkbox.dataset.cliente;
					const tr = checkbox.closest('tr');
					const total = parseFloat(tr?.dataset.total || 0);

					if(checkbox.checked){

						if(total <= 0){
							alert('Pedido não possui valor.');
							checkbox.checked = false;
							return;
						}

						clientePagamentoAtual = idCliente;
						totalPagamentoAtual = total;

						abrirModalPagamento(total);

					} else {
						pagamentosSelecionados = [];
					}
				});

				/* Modal Pagamento */
				/* Variável global para controle do valor total no modal */


				/**
				 * Abre o modal e limpa seleções anteriores
				 */
				function abrirModalPagamento(total){
					const modal = document.getElementById('modalPagamento');
					if(!modal) return;

					totalPagamentoAtual = parseFloat(total);
					modal.style.display = 'block';

					// Limpa inputs e desmarca checkboxes
					document.querySelectorAll('.pg-input').forEach(input => input.value = '');
					document.querySelectorAll('.pg-check').forEach(chk => chk.checked = false);

					// Por padrão, seleciona o primeiro método (Geralmente Dinheiro ou Crédito)
					const primeiroCheck = document.querySelector('.pg-check');
					if(primeiroCheck){
						primeiroCheck.checked = true;
						distribuirPagamentos();
					}
				}

				/**
				 * Lógica de automação e divisão de valores
				 */
				function distribuirPagamentos() {
					const checksAtivos = document.querySelectorAll('.pg-check:checked');
					const inputs = document.querySelectorAll('.pg-input');

					// 1. Zera todos os inputs antes de redistribuir
					inputs.forEach(input => input.value = '');

					if (checksAtivos.length === 0) return;

					// 2. Calcula valor base arredondado para baixo
					// Ex: 100 / 3 = 33.3333 -> 33.33
					const valorDividido = Math.floor((totalPagamentoAtual / checksAtivos.length) * 100) / 100;
					let somaAcumulada = 0;

					// 3. Distribui os valores
					checksAtivos.forEach((chk, index) => {
						const id = chk.dataset.id;
						const inputAlvo = document.getElementById('input-pg-' + id);

						if (index === checksAtivos.length - 1) {
							// O último campo recebe o resto exato para fechar o total (corrige centavos)
							const resto = (totalPagamentoAtual - somaAcumulada).toFixed(2);
							inputAlvo.value = resto;
						} else {
							inputAlvo.value = valorDividido.toFixed(2);
							somaAcumulada += valorDividido;
						}
					});
				}

				/**
				 * Event Listener para os Checkboxes
				 */
				document.addEventListener('change', function(e) {
					if(e.target && e.target.classList.contains('pg-check')){
						distribuirPagamentos();
					}
				});

				function fecharModalPagamento(){
					const modal = document.getElementById('modalPagamento');
					if(modal) modal.style.display = 'none';

					if(clientePagamentoAtual){
						const chk = document.querySelector('.pedido-pago-check[data-cliente="'+clientePagamentoAtual+'"]');
						if(chk) chk.checked = false;
					}
					pagamentosSelecionados = [];
				}

				/**
				 * Salva apenas o pedido específico do Modal como PAGO
				 */
				function confirmarPagamento() {
					let somaPagamentos = 0;
					let meiosPagamento = [];
					const idCliente = clientePagamentoAtual;

					// 1. Coleta os Meios de Pagamento do Modal
					document.querySelectorAll('.pg-input').forEach(input => {
						const valor = parseFloat(input.value) || 0;
						if (valor > 0) {
							somaPagamentos += valor;
							meiosPagamento.push({
								idForma: input.dataset.id,
								valor: valor
							});
						}
					});

					// Validação de soma (Margem de erro para dízimas)
					if (Math.abs(somaPagamentos - totalPagamentoAtual) > 0.011) {
						alert('A soma dos pagamentos (R$ '+somaPagamentos.toFixed(2)+') não confere com o total (R$ '+totalPagamentoAtual.toFixed(2)+').');
						return;
					}

					// 2. Coleta os dados dos Produtos da linha deste cliente
					const produtos = [];
					document.querySelectorAll('.produto-' + idCliente).forEach(el => {
						const qtd = parseInt(el.value) || 0;
						if (qtd > 0) {
							produtos.push({
								idProduto: el.dataset.id, // Agora pegamos direto do data-id do input
								quantidade: qtd,
								valor: parseFloat(el.dataset.valor)
							});
						}
					});

					// 3. Coleta Variado e Observação
					const valorVariado = parseFloat(document.getElementById('variado-' + idCliente).value) || 0;
					const observacao = document.getElementById('obs-' + idCliente).value || null;

					const dadosPedido = {
						idCliente: idCliente,
						data: "<?= $dataSelecionada ?>",
						produtos: produtos,
						valorVariado: valorVariado,
						observacao: observacao,
						pagamentos: meiosPagamento,
						pedidoPago: 1
					};

					// Bloqueia o botão para evitar cliques duplos
					const btnSalvarModal = event.target;
					btnSalvarModal.disabled = true;
					btnSalvarModal.innerText = 'Processando...';

					// 4. Envia para o PHP exclusivo
					fetch('processar_pagamento_avulso.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(dadosPedido)
					})
					.then(r => r.json())
					.then(resp => {
						if (resp.sucesso) {
							alert('Pedido salvo e pago com sucesso!');
							location.reload();
						} else {
							alert('Erro ao salvar: ' + resp.mensagem);
							btnSalvarModal.disabled = false;
							btnSalvarModal.innerText = 'Salvar';
						}
					})
					.catch(err => {
						alert('Erro de comunicação com o servidor.');
						btnSalvarModal.disabled = false;
						btnSalvarModal.innerText = 'Salvar';
					});
				}

				/* Verifica valor variado */
				function verificarVariado(id) {
					const valor = parseFloat(document.getElementById('variado-' + id).value) || 0;
					if (valor > 0) abrirObs(id, true);
				}

				function finalizarEdicaoVariado(id){
					verificarVariado(id);
					ordenarTabela();
				}

				/* Modal observação */
				/* Variável global para controle da observação */
				let clienteAtual = null;

				/* Modal observação */
				function abrirObs(id, destacar = false) {
					clienteAtual = id;

					// Referências explícitas aos elementos
					const modalObs = document.getElementById('modalObs');
					const campoTexto = document.getElementById('obsTexto');
					const inputOculto = document.getElementById('obs-' + id);

					if (!modalObs || !campoTexto || !inputOculto) {
						console.error("Erro: Elementos do modal de observação não encontrados.");
						return;
					}

					const obsSalva = inputOculto.value.trim();

					if (obsSalva === '') {
						campoTexto.value = 'DESCREVA DO QUE SE TRATA O VALOR VARIADO!!!\nÉ IMPORTANTE ISSO SER UTILIZADO!!!';
					} else {
						campoTexto.value = obsSalva;
					}

					modalObs.style.display = 'block';

					// Foca no texto e coloca o cursor no final
					campoTexto.focus();
					campoTexto.setSelectionRange(campoTexto.value.length, campoTexto.value.length);

					const box = modalObs.querySelector('div');
					if (box) {
						box.classList.remove('modal-alerta');
						if (destacar) box.classList.add('modal-alerta');
					}
				}

				function fecharObs() {
					const modalObs = document.getElementById('modalObs');
					if (modalObs) {
						modalObs.style.display = 'none';
						const box = modalObs.querySelector('div');
						if (box) box.classList.remove('modal-alerta');
					}
				}

				function salvarObs() {
					const campoTexto = document.getElementById('obsTexto');
					const inputOculto = document.getElementById('obs-' + clienteAtual);

					if (inputOculto && campoTexto) {
						inputOculto.value = campoTexto.value;
					}
					fecharObs();
                }

                /* Flatpickr */
				document.addEventListener('DOMContentLoaded', function() {
					const datasPendentes = <?= json_encode($datasPendentes ?? []) ?>;

					flatpickr("#dataPedido", {
						// Altere de 'flatpickr.l10ns.pt' para apenas 'pt' ou 'Portuguese'
						locale: "pt",
						dateFormat: "Y-m-d",
						altInput: true,
						altFormat: "d/m/Y",
						defaultDate: "<?= $dataSelecionada ?>",
						onDayCreate: function(dObj, dStr, fp, dayElem) {
							const data = dayElem.dateObj.toISOString().split('T')[0];
							if(datasPendentes.includes(data)) {
								dayElem.style.background = '#ff5555';
								dayElem.style.color = '#fff';
								dayElem.title = 'Pedido não pago';
							}
						},
						onChange: function(sel, dateStr) {
							window.location = '?data=' + dateStr;
						}
					});
				});

				/* Ordenação */
				function ordenarTabela(){
					const tbody = document.querySelector('tbody');
					const linhas = Array.from(tbody.querySelectorAll('tr'));

					linhas.sort((a,b)=>{
						const totalA = parseFloat(a.dataset.total||0);
						const totalB = parseFloat(b.dataset.total||0);
						const pagoA = a.dataset.pago === '1';
						const pagoB = b.dataset.pago === '1';

						if(totalA>0 && !pagoA && !(totalB>0 && !pagoB)) return -1;
						if(totalB>0 && !pagoB && !(totalA>0 && !pagoA)) return 1;
						if(totalA>0 && pagoA && !(totalB>0 && pagoB)) return -1;
						if(totalB>0 && pagoB && !(totalA>0 && pagoA)) return 1;

						return a.dataset.cliente.localeCompare(b.dataset.cliente);
					});

					linhas.forEach(tr=>tbody.appendChild(tr));
                }

				/* Lista de clientes que já tinham pedido com valor ao carregar a página */
				const clientesComValorInicial = [];

				<?php foreach($pedidos as $idCliente => $pedido): ?>
					<?php if($pedido['ValorVariado'] > 0 || !empty($pedido['itens'])): ?>
						clientesComValorInicial.push(<?= $idCliente ?>);
					<?php endif; ?>
				<?php endforeach; ?>

				/* Função para enviar apenas os clientes alterados ou zerados */
				/* Função para enviar apenas os clientes alterados ou zerados */
				function salvarTudo() {
					const btnSalvar = document.getElementById('btnSalvar');
					const statusSave = document.getElementById('status-save');

					if (btnSalvar) {
						btnSalvar.disabled = true;
						btnSalvar.innerText = '⏳ Processando...';
					}

					localStorage.setItem(
						'filtroCliente',
						document.getElementById('filtroCliente').value.toLowerCase()
					);

					const dados = {
						data: "<?= $dataSelecionada ?>",
						clientes: []
					};

					// Criamos um mapeamento dos IDs de produtos para usar dentro do loop
					const listaIdsProdutos = <?= json_encode(array_column($produtos, 'IdProduto')) ?>;

					<?php foreach ($clientes as $c): ?>
					(function () {
						const idCliente = <?= $c['IdCliente'] ?>;
						const produtos = [];
						let temQuantidade = false;

						// Coleta produtos desta linha
						document.querySelectorAll('.produto-' + idCliente).forEach((el, index) => {
							const qtd = parseInt(el.value) || 0;
							if (qtd > 0) {
								temQuantidade = true;
								produtos.push({
									idProduto: listaIdsProdutos[index],
									quantidade: qtd,
									valor: parseFloat(el.dataset.valor)
								});
							}
						});

						const valorVariado = parseFloat(document.getElementById('variado-' + idCliente).value) || 0;
						const checkboxPago = document.querySelector('.pedido-pago-check[data-cliente="' + idCliente + '"]');
						const pedidoPago = (checkboxPago && checkboxPago.checked) ? 1 : 0;
						const observacao = document.getElementById('obs-' + idCliente).value || null;

						// BUSCA O ID DO PEDIDO DIRETAMENTE DO DATA-ID DA LINHA (TR)
						// Usamos o seletor pelo checkbox que já tem o ID do cliente, subindo até a TR
						const inputRef = document.querySelector('.pedido-pago-check[data-cliente="' + idCliente + '"]');
						const trCorrente = inputRef ? inputRef.closest('tr') : null;
						const idPedidoExistente = trCorrente ? trCorrente.dataset.id : '';

						if (temQuantidade || valorVariado > 0) {
							dados.clientes.push({
								idCliente: idCliente,
								produtos: produtos,
								variado: valorVariado,
								pedidoPago: pedidoPago,
								observacao: observacao
							});
						}
						else if (idPedidoExistente && idPedidoExistente !== '' && idPedidoExistente !== '0') {
							dados.clientes.push({
								idCliente: idCliente,
								idPedido: idPedidoExistente,
								limpar: true
							});
						}
					})();
					<?php endforeach; ?>

					fetch('salvar_pedidos.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify(dados)
					})
					.then(r => r.json())
					.then(resp => {
						if (statusSave) statusSave.innerText = resp.mensagem;
						location.reload();
					})
					.catch((err) => {
						console.error(err);
						if (btnSalvar) {
							btnSalvar.disabled = false;
							btnSalvar.innerText = '💾 Salvar';
						}
					});
				}

                document.addEventListener('DOMContentLoaded', () => {
                    const btnSalvar = document.getElementById('btnSalvar');
                    btnSalvar.addEventListener('click', salvarTudo);
                });

                /**
                * Abre o modal de recibo e carrega o conteúdo no iframe
                */
                function abrirRecibo(idPedido) {
                    const modal = document.getElementById('modalRecibo');
                    const iframe = document.getElementById('iframeRecibo');

                    if (!modal || !iframe) {
                        console.error("Elementos do modal de recibo não encontrados.");
                        return;
                    }

                    // Define a URL do recibo. Ajuste o nome do arquivo se necessário (ex: gerar_recibo.php)
                    iframe.src = 'recibo_pedido.php?id=' + idPedido;

                    // Exibe o modal
                    modal.style.display = 'block';
                }

                /**
                 * Fecha o modal de recibo e limpa o iframe
                */
                function fecharRecibo() {
                    const modal = document.getElementById('modalRecibo');
                    const iframe = document.getElementById('iframeRecibo');

                    if (modal) modal.style.display = 'none';
                    if (iframe) iframe.src = ''; // Limpa o src para não carregar o recibo antigo na próxima vez
                }


			</script>



	<?php
		require_once '../layout_footer.php';
	?>

