<?php
require '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$data = $input['data'];

$pdo->beginTransaction();

try {

    foreach ($input['clientes'] as $c) {

        // ======================================================
        // LIMPAR PEDIDO
        // ======================================================
        if (!empty($c['limpar'])) {

            $stmt = $pdo->prepare("
                SELECT IdPedido
                FROM pedidos
                WHERE IdCliente = ?
                  AND DataPedido = ?
            ");
            $stmt->execute([$c['idCliente'], $data]);
            $idPedido = $stmt->fetchColumn();

            if ($idPedido) {
                // Zera pedido
                $pdo->prepare("
                    UPDATE pedidos
                    SET ValorVariado = 0,
                        ObservacaoVariado = NULL,
                        PedidoPago = 0
                    WHERE IdPedido = ?
                ")->execute([$idPedido]);

                // Remove itens e pagamentos
                $pdo->prepare("DELETE FROM pedido_itens WHERE IdPedido = ?")->execute([$idPedido]);
                $pdo->prepare("DELETE FROM pedido_pagamentos WHERE IdPedido = ?")->execute([$idPedido]);
            }

            continue;
        }

        // ======================================================
        // VERIFICA SE PEDIDO EXISTE
        // ======================================================
        $stmt = $pdo->prepare("
            SELECT IdPedido
            FROM pedidos
            WHERE IdCliente=? AND DataPedido=?
        ");
        $stmt->execute([$c['idCliente'], $data]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            // INSERT PEDIDO
            $stmt = $pdo->prepare("
                INSERT INTO pedidos
                (IdCliente, DataPedido, ValorVariado, ObservacaoVariado, PedidoPago)
                VALUES (?,?,?,?,?)
            ");
            $stmt->execute([
                $c['idCliente'],
                $data,
                $c['variado'],
                $c['observacao'],
                $c['pedidoPago']
            ]);
            $idPedido = $pdo->lastInsertId();
        } else {
            // UPDATE PEDIDO
            $idPedido = $pedido['IdPedido'];
            $pdo->prepare("
                UPDATE pedidos
                SET ValorVariado=?,
                    ObservacaoVariado=?,
                    PedidoPago=?
                WHERE IdPedido=?
            ")->execute([
                $c['variado'],
                $c['observacao'],
                $c['pedidoPago'],
                $idPedido
            ]);
            // Limpa itens antigos para inserir os novos
            $pdo->prepare("DELETE FROM pedido_itens WHERE IdPedido=?")->execute([$idPedido]);
        }

        // ======================================================
        // INSERE ITENS EM BATCH
        // ======================================================
        if (!empty($c['produtos'])) {
            $values = [];
            $placeholders = [];
            foreach ($c['produtos'] as $p) {
                $placeholders[] = "(?, ?, ?, ?)";
                $values[] = $idPedido;
                $values[] = $p['idProduto'];
                $values[] = $p['quantidade'];
                $values[] = $p['valor'];
            }
            $sql = "INSERT INTO pedido_itens (IdPedido, IdProduto, Quantidade, ValorUnitario) VALUES " . implode(", ", $placeholders);
            $stmtItem = $pdo->prepare($sql);
            $stmtItem->execute($values);
        }

        // ======================================================
        // PAGAMENTOS EM BATCH
        // ======================================================
        $pagamentos = $c['pagamentos'] ?? [];

        // Limpa pagamentos antigos
        $pdo->prepare("DELETE FROM pedido_pagamentos WHERE IdPedido = ?")->execute([$idPedido]);

        if ($c['pedidoPago'] && !empty($pagamentos)) {
            $values = [];
            $placeholders = [];
            foreach ($pagamentos as $pg) {
                $placeholders[] = "(?, ?, ?)";
                $values[] = $idPedido;
                $values[] = $pg['idFormaPagamento'];
                $values[] = $pg['valor'];
            }
            $sql = "INSERT INTO pedido_pagamentos (IdPedido, IdFormaPagamento, ValorPago) VALUES " . implode(", ", $placeholders);
            $stmtPg = $pdo->prepare($sql);
            $stmtPg->execute($values);
        }

    }

    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'mensagem' => 'Pedidos salvos com sucesso'
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}

