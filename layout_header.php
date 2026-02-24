<?php
// layout_header.php
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Players Stop TCG</title>
<link rel="icon" type="image/png" href="favicon/logo.png">
<!-- Fonte estilo Pokémon -->
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
<style>
/* =========================
   BASE
========================= */
body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #0f0f0f;
    color: #fff;
}
/* =========================
   ZEBRA TABLE (linhas alternadas)
========================= */
tbody tr:nth-child(odd) {
    background-color: #1f1f1f; /* tom base */
}

tbody tr:nth-child(even) {
    background-color: #262626; /* mesmo tom levemente mais claro */
}


main {
    margin-top: 56px;
    padding-bottom: 30px;
    height: calc(100vh - 86px); /* header + footer */
    overflow-y: auto;           /* AQUI está o segredo */
    overflow-x: hidden;
    box-sizing: border-box;
    background: #0f0f0f;
}



/* =========================
   HEADER (VISUAL)
========================= */
header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    box-sizing: border-box;
    background: #b30000;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 18px;
    z-index: 1000;
    height: 56px;
}

header img {
    height: 56px;
}

header h1 {
    margin: 0;
    font-family: 'Press Start 2P', Arial, sans-serif;
    font-size: 14px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* =========================
   NAVEGAÇÃO (APENAS ESTILO)
========================= */
nav {
    margin-left: auto;
    display: flex;
    gap: 12px;
}

nav a {
    font-family: 'Press Start 2P', Arial, sans-serif;
    font-size: 10px;
    color: #ffffff;
    text-decoration: none;
    padding: 10px 14px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

nav a:hover {
    background: rgba(255,255,255,0.15);
    transform: translateY(-2px) scale(1.03);
}

nav a.ativo {
    box-shadow: inset 0 0 0 2px rgba(255,255,255,0.4);
}

/* =========================
   TABELA MINIMALISTA
========================= */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    color: #eee;
    background-color: #1a1a1a;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 6px rgba(255, 255, 255, 0.1);
}

th, td {
    padding: 10px 14px;
    border-bottom: 1px solid #333;
    text-align: left;
}

th {
    background-color: #222;
    font-weight: 600;
}

tbody tr:hover {
    background-color: #303030;
    transition: background-color 0.15s ease-in-out;
}


/* =========================
   LINKS BOTÕES arredondados
========================= */
table a {
    display: inline-block;
    padding: 6px 12px;
    margin: 0 4px;
    font-size: 12px;
    color: #fff;
    background-color: #444;
    border: 2px solid transparent;
    border-radius: 12px;
    text-decoration: none;
    transition: background-color 0.3s ease, border-color 0.3s ease;
    user-select: none;
}

table a:hover {
    background-color: #ffcb05;
    color: #222;
    border-color: #ffcb05;
    box-shadow: 0 0 8px #ffcb05;
}

/* =========================
   EMOJIS nas ações
========================= */
table a[href*="desativar="]::before { content: "🚫 "; }
table a[href*="ativar="]:not([href*="desativar="])::before { content: "✅ "; }
table a[href*="edit="]::before { content: "✏️ "; }
table a[href*="delete="]::before { content: "🗑️ "; }

/* =========================
   STATUS PEDIDOS
========================= */
td.pago-cell,
td.total-pago {
    background: #00ff00;
    color: #000;
    font-weight: bold;
}

td.total-pendente {
    background: #ff0000;
    color: #000;
    font-weight: bold;
}

/* =========================
   INPUTS / BOTÕES
========================= */
input, select, button {
    padding: 6px 10px;
    border-radius: 6px;
    border: none;
}

button {
    background: #b30000;
    color: #fff;
    cursor: pointer;
}

/* =========================
   TOTAL DO DIA
========================= */
.total-dia {
    background: #e6f4ea;
    color: #1b5e20;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 20px;
}

footer {
    height: 30px;
    line-height: 12px;
    padding: 6px 20px;
    background: #b30000;
    color: #fff;
    text-align: center;
    font-family: 'Press Start 2P', cursive;
    font-size: 7px;
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100vw;
    box-sizing: border-box;
    z-index: 1000;
    box-shadow: 0 -4px 10px rgba(179, 0, 0, 0.6);
}
			main{
				overflow: visible !important;
			}
			input[type=number]{
				width:70px;
			}
			td.total-pago{
				background:#4caf50;
				color:#fff;
				font-weight:bold;
			}
			td.total-pendente{
				background:#f44336;
				color:#fff;
				font-weight:bold;
			}
			tr.pedido-pago{
				background:#e6f4ea;
			}
			#modalObs{
				position:fixed;
				inset:0;
				background:rgba(0,0,0,.5);
				display:none;
			}
			#modalObs div{
				background:#fff;
				width:400px;
				margin:10% auto;
				padding:10px;
			}
			@keyframes piscarBorda {
				0%   { box-shadow: 0 0 0 10px red; }
				50%  { box-shadow: 0 0 0 4px transparent; }
				100% { box-shadow: 0 0 0 10px red; }
			}
			.modal-alerta {
				animation: piscarBorda 0.8s infinite;
			}


</style>
</head>

<body>


<main>
<hr>
<!-- Conteúdo principal aqui -->


