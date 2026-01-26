<?php
require '../conexao.php';

$input = json_decode(file_get_contents('php://input'), true);
$data = $input['data'];

$pdo->beginTransaction();

try {
    foreach ($input['clientes'] as $c) {
        if (!empty($cliente['limpar'])) {

            // busca pedido existente
            $stmt = $pdo->prepare("
                SELECT IdPedido
                FROM pedidos
                WHERE IdCliente = ?
                  AND DataPedido = ?
            ");
            $stmt->execute([$cliente['idCliente'], $data]);
            $idPedido = $stmt->fetchColumn();

            if ($idPedido) {

                // zera pedido
                $pdo->prepare("
                    UPDATE pedidos
                    SET ValorVariado = 0,
                        ObservacaoVariado = NULL,
                        PedidoPago = 0
                    WHERE IdPedido = ?
                ")->execute([$idPedido]);

                // remove itens
                $pdo->prepare("
                    DELETE FROM pedido_itens
                    WHERE IdPedido = ?
                ")->execute([$idPedido]);
            }

            continue;
        }

        $stmt = $pdo->prepare("
            SELECT IdPedido FROM pedidos
            WHERE IdCliente=? AND DataPedido=?
        ");
        $stmt->execute([$c['idCliente'], $data]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            $stmt = $pdo->prepare("
                INSERT INTO pedidos
                (IdCliente, DataPedido, ValorVariado, ObservacaoVariado, PedidoPago)
                VALUES (?,?,?,?,?)
            ");
            $stmt->execute([
                $c['idCliente'], $data, $c['variado'],
                $c['observacao'], $c['pedidoPago']
            ]);
            $idPedido = $pdo->lastInsertId();
        } else {
            $idPedido = $pedido['IdPedido'];
            $pdo->prepare("
                UPDATE pedidos
                SET ValorVariado=?, ObservacaoVariado=?, PedidoPago=?
                WHERE IdPedido=?
            ")->execute([
                $c['variado'], $c['observacao'], $c['pedidoPago'], $idPedido
            ]);
            $pdo->prepare("DELETE FROM pedido_itens WHERE IdPedido=?")
                ->execute([$idPedido]);
        }

        foreach ($c['produtos'] as $p) {
            $pdo->prepare("
                INSERT INTO pedido_itens
                (IdPedido, IdProduto, Quantidade, ValorUnitario)
                VALUES (?,?,?,?)
            ")->execute([
                $idPedido, $p['idProduto'], $p['quantidade'], $p['valor']
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['status'=>'ok','mensagem'=>'Pedidos salvos com sucesso']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status'=>'erro','mensagem'=>$e->getMessage()]);
}
