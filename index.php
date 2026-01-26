<?php
require 'layout_header.php';
require 'nav.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8" />
<title>Players Stop TCG - Administração Popup</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #0f0f0f;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
  }
  .menu {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    max-width: 700px;
    justify-content: center;
  }
.menu button {
    background: #2d6cdf;
    border: none;
    border-radius: 12px;
    padding: 12px 20px; /* menor padding para encolher botão */
    font-size: 14px; /* fonte um pouco menor */
    font-weight: bold;
    color: #ffcb05; /* amarelo Pokémon */
    cursor: pointer;
    width: 160px; /* largura menor */
    height: 60px; /* altura menor */
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    transition: background-color 0.3s ease;
    user-select: none;
    white-space: normal; /* para texto quebrar se for muito longo */
    text-align: center; /* centraliza texto */
}
.menu button:hover {
    background: #1f4fb8;
}
</style>
</head>
<body>

<div class="menu">
  <button onclick="abrirPopup('/relacionamentos/relacionamentos.php', 'RelEdições')">🔗 Relacionar Edições</button>
  <button onclick="abrirPopup('/ligamagic/index.php', 'CRUDLigaMagic')">📘 - CRUD LigaMagic! 1</button>
  <button onclick="abrirPopup('/scryfall/index.php', 'CRUDScryfall')">🌐 - CRUD Scryfall! 1</button>
  <button onclick="abrirPopup('/edicoes/index.php', 'NumCardEdicoes')">🌐 Numero Card Edicoes Scryfall</button>
  <button onclick="abrirPopup('/comparar/comparar_cartas.php', 'CompararCartas')">🌐 Comparação de Cartas</button>
  <button onclick="abrirPopup('/comparar/comparar_imagens.php', 'CompararImagens')">🌐 Comparação por Imagens</button>
</div>

<script>
function abrirPopup(url, nomeJanela) {
  const largura = 900;
  const altura = 700;
  const esquerda = (screen.width - largura) / 2;
  const topo = (screen.height - altura) / 2;

  window.open(
    url,
    nomeJanela,
    `width=${largura},height=${altura},top=${topo},left=${esquerda},resizable=yes,scrollbars=yes`
  );
}
</script>

</body>
</html>
