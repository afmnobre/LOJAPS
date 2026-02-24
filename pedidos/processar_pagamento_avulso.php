<?php
require '../conexao.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verifica se já existe um pedido para este cliente nesta data
    $stmtBusca = $pdo->prepare("SELECT IdPedido FROM pedidos WHERE IdCliente = ? AND DataPedido = ?");
    $stmtBusca->execute([$data['idCliente'], $data['data']]);
    $idPedido = $stmtBusca->fetchColumn();

    if ($idPedido) {
        // UPDATE no pedido existente
        $stmtUp = $pdo->prepare("UPDATE pedidos SET ValorVariado = ?, ObservacaoVariado = ?, PedidoPago = 1 WHERE IdPedido = ?");
        $stmtUp->execute([$data['valorVariado'], $data['observacao'], $idPedido]);
    } else {
        // INSERT novo pedido
        $stmtIns = $pdo->prepare("INSERT INTO pedidos (IdCliente, DataPedido, ValorVariado, ObservacaoVariado, PedidoPago) VALUES (?, ?, ?, ?, 1)");
        $stmtIns->execute([$data['idCliente'], $data['data'], $data['valorVariado'], $data['observacao']]);
        $idPedido = $pdo->lastInsertId();
    }

    // 2. Atualiza os Itens do Pedido (Limpa e insere novos)
    $pdo->prepare("DELETE FROM pedido_itens WHERE IdPedido = ?")->execute([$idPedido]);
    $stmtItem = $pdo->prepare("INSERT INTO pedido_itens (IdPedido, IdProduto, Quantidade, ValorUnitario) VALUES (?, ?, ?, ?)");
    foreach ($data['produtos'] as $p) {
        $stmtItem->execute([$idPedido, $p['idProduto'], $p['quantidade'], $p['valor']]);
    }

    // 3. Atualiza os Meios de Pagamento (Tabela pedido_pagamentos)
    $pdo->prepare("DELETE FROM pedido_pagamentos WHERE IdPedido = ?")->execute([$idPedido]);
    $stmtPg = $pdo->prepare("INSERT INTO pedido_pagamentos (IdPedido, IdFormaPagamento, ValorPago) VALUES (?, ?, ?)");
    foreach ($data['pagamentos'] as $pg) {
        $stmtPg->execute([$idPedido, $pg['idForma'], $pg['valor']]);
    }

    $pdo->commit();
    echo json_encode(['sucesso' => true, 'mensagem' => 'Sucesso!']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['sucesso' => false, 'mensagem' => $e->getMessage()]);
}
