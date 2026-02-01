<?php
require '../../conexao.php';

$id = (int)($_GET['id'] ?? 0);

$pdo->prepare("DELETE FROM pedido_itens WHERE IdPedido = ?")->execute([$id]);
$pdo->prepare("DELETE FROM pedidos WHERE IdPedido = ?")->execute([$id]);
?>

<script>
    window.opener && window.opener.location.reload();
    window.location.href = 'pedidos_abertos.php?excluido=1&nocache=' + Date.now();
</script>

