<?php
require 'layout_header.php';
require 'nav.php';
require 'conexao.php';

/* =========================
   LISTAGEM DE CLIENTES AUSENTES (+30 DIAS)
========================= */
$sqlAusentes = "
SELECT
    c.IdCliente,
    c.NomeCompletoCliente,
    c.DocumentoCliente, -- Usando como campo de celular
    MAX(p.DataPedido) as ultima_compra,
    DATEDIFF(CURDATE(), MAX(p.DataPedido)) as dias_ausente
FROM clientes c
LEFT JOIN pedidos p ON c.IdCliente = p.IdCliente
WHERE c.Status = 'Ativado'
GROUP BY c.IdCliente
HAVING (dias_ausente > 30 OR ultima_compra IS NULL)
ORDER BY dias_ausente DESC
";
$clientesAusentes = $pdo->query($sqlAusentes)->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ESTRUTURA HTML DO RELATÓRIO
========================= */
?>

<?php
/* =========================
   ESTRUTURA HTML DO RELATÓRIO
========================= */
?>

<br>
<h2>Recuperação de Clientes (Ausentes há mais de 1 mês)</h2>
<table border="1" width="100%" cellpadding="6" style="border-collapse: collapse;">
    <thead style="background: #f9f9f9;">
        <tr>
            <th>Cliente</th>
            <th>Celular</th>
            <th>Última Compra</th>
            <th>Dias Ausente</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clientesAusentes as $ca):
            // 1. Limpa o nome: Se tiver "PKM - Lucas", pega apenas "Lucas"
            $nomeBruto = $ca['NomeCompletoCliente'];
            if (strpos($nomeBruto, ' - ') !== false) {
                $partes = explode(' - ', $nomeBruto);
                $nomeLimpo = trim($partes[1]); // Pega o que está depois do hífen
            } else {
                $nomeLimpo = $nomeBruto;
            }

            // 2. Pega apenas o primeiro nome para a saudação
            $primeiroNome = explode(' ', $nomeLimpo)[0];

            // 3. Prepara o WhatsApp
            $celularLimpo = preg_replace('/\D/', '', $ca['DocumentoCliente']);
            $mensagem = "Olá " . $primeiroNome . ", tudo bem? Notamos que faz tempo que não nos visita. Temos novidades de " . (explode(' - ', $nomeBruto)[0] ?? 'jogos') . " aqui!";
            $linkWhats = "https://wa.me/55" . $celularLimpo . "?text=" . urlencode($mensagem);

            $dataExibicao = $ca['ultima_compra'] ? date('d/m/Y', strtotime($ca['ultima_compra'])) : 'Nunca comprou';
            $diasTexto = $ca['dias_ausente'] ?? 'Novo';
        ?>
        <tr>
            <td><?= $ca['NomeCompletoCliente'] ?></td>
            <td align="center"><?= $ca['DocumentoCliente'] ?></td>
            <td align="center"><?= $dataExibicao ?></td>
            <td align="center"><?= $diasTexto ?> dias</td>
            <td align="center">
                <a href="<?= $linkWhats ?>" target="_blank" style="color: #25D366; font-weight: bold; text-decoration: none;">
                    [ Chamar no WhatsApp ]
                </a>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (empty($clientesAusentes)): ?>
        <tr>
            <td colspan="5" align="center">Nenhum cliente para captação no momento.</td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>
