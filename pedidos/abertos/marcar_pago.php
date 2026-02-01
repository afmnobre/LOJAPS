<?php
require '../../conexao.php';

$id = (int)($_GET['id'] ?? 0);

$pdo->prepare("
    UPDATE pedidos
    SET PedidoPago = 1
    WHERE IdPedido = ?
")->execute([$id]);
?>

<script>
    // força recarregar a página que chamou
    window.opener && window.opener.location.reload();
    window.location.href = 'pedidos_abertos.php?atualizado=1&nocache=' + Date.now();
</script>

