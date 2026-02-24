<?php
session_start();
require '../../conexao.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['idPedido'])) {
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Registrar os pagamentos na tabela de rateio (pedido_pagamentos)
    // Removida qualquer menção a colunas de data aqui para evitar erros de "Column not found"
    $sqlPag = "INSERT INTO pedido_pagamentos (IdPedido, IdFormaPagamento, ValorPago) VALUES (?, ?, ?)";
    $stmtPag = $pdo->prepare($sqlPag);

    foreach ($data['pagamentos'] as $pag) {
        $stmtPag->execute([
            $data['idPedido'],
            $pag['idForma'],
            $pag['valor']
        ]);
    }

    // 2. Marcar o pedido como pago na tabela 'pedidos'
    // Como você não tem a coluna DataPagamento, atualizamos apenas o PedidoPago
    $sqlUp = "UPDATE pedidos SET PedidoPago = 1 WHERE IdPedido = ?";
    $pdo->prepare($sqlUp)->execute([$data['idPedido']]);

    $pdo->commit();
    echo json_encode(['sucesso' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    // Retorna o erro detalhado caso ainda falte alguma coluna na tabela pedido_pagamentos
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro SQL: ' . $e->getMessage()]);
}
