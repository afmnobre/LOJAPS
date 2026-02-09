<?php
// nav.php

$base = '/LOJAPS';
$paginaAtual = $_SERVER['REQUEST_URI'];
?>

<header>
    <img src="<?= $base ?>/LogoPS.jpg" alt="Players Stop TCG">
    <h1>Players Stop TCG</h1>

    <nav>
        <a href="<?= $base ?>/index.php"
           class="<?= str_contains($paginaAtual, '/index.php') ? 'ativo' : '' ?>">
            Início
        </a>

        <a href="<?= $base ?>/pedidos/pedidos.php"
           class="<?= str_contains($paginaAtual, '/pedidos/') ? 'ativo' : '' ?>">
            Pedidos
        </a>

        <a href="<?= $base ?>/clientes/clientes.php"
           class="<?= str_contains($paginaAtual, '/clientes/') ? 'ativo' : '' ?>">
            Clientes
        </a>

        <a href="<?= $base ?>/produtos/produtos.php"
           class="<?= str_contains($paginaAtual, '/produtos/') ? 'ativo' : '' ?>">
            Produtos
        </a>
        <a href="<?= $base ?>/pedidos/abertos/pedidos_abertos.php"
           class="<?= str_contains($paginaAtual, '/pedidos/abertos/') ? 'ativo' : '' ?>">
            Fechamento
        </a>

        <a href="<?= $base ?>/relatorios/relatorios.php"
           class="<?= str_contains($paginaAtual, '/relatorios/') ? 'ativo' : '' ?>">
            Relatório-Produto
        </a>
        <a href="<?= $base ?>/relatorios/relatorio_financeiro.php"
           class="<?= str_contains($paginaAtual, '/relatorios/') ? 'ativo' : '' ?>">
            Relatório-Cliente
        </a>
        <a href="<?= $base ?>/logout.php"
           class="<?= str_contains($paginaAtual, '/') ? 'ativo' : '' ?>">
            Logout
        </a>

    </nav>
</header>
