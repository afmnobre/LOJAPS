<?php
require 'conexao.php';
session_start();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("
        SELECT * FROM usuarios
        WHERE Login = ? AND Status = 'Ativo'
    ");
    $stmt->execute([$login]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['SenhaHash'])) {
        $_SESSION['usuario_id']   = $usuario['IdUsuario'];
        $_SESSION['usuario_nome'] = $usuario['NomeUsuario'];

        header('Location: index.php');
        exit;
    } else {
        $erro = 'Usuário ou senha inválidos';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Login · Players Stop TCG</title>

<style>
:root {
    --vermelho: #e30613;
    --vermelho-escuro: #9b0f16;
    --cinza-escuro: #1e1e1e;
    --branco: #ffffff;
}

* {
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, var(--vermelho-escuro), var(--vermelho));
    display: flex;
    align-items: center;
    justify-content: center;
}

.login-container {
    background: var(--branco);
    width: 100%;
    max-width: 380px;
    padding: 35px 30px;
    border-radius: 14px;
    box-shadow: 0 18px 45px rgba(0,0,0,0.4);
    text-align: center;
}

.login-container img {
    width: 140px;
    margin-bottom: 20px;
}

.login-container h2 {
    margin: 0 0 20px;
    color: var(--cinza-escuro);
}

.login-container input {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 14px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 15px;
}

.login-container input:focus {
    outline: none;
    border-color: var(--vermelho);
    box-shadow: 0 0 0 2px rgba(227,6,19,0.2);
}

.login-container button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 6px;
    background: var(--vermelho);
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s ease;
}

.login-container button:hover {
    background: var(--vermelho-escuro);
}

.erro {
    background: #ffe5e5;
    color: #a30000;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
}

.footer {
    margin-top: 20px;
    font-size: 12px;
    color: #666;
}
</style>
</head>

<body>

<div class="login-container">

    <!-- LOGO -->
    <img src="LogoPS.jpg" alt="Players Stop TCG">

    <h2>Acesso ao Sistema</h2>

    <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <!-- MESMO FORM, MESMOS CAMPOS -->
    <form method="post">
        <input type="text" name="login" placeholder="Usuário" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <button type="submit">Entrar</button>
    </form>

    <div class="footer">
        © <?= date('Y') ?> Players Stop TCG
    </div>

</div>

</body>
</html>
