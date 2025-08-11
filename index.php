<?php

// Habilita a exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

/**
 * Função que lista os pedidos na dashboard
 **/

function listOrders($pdo)
{
    try {
        // Mostra apenas informações essênciais
        $sql = "
            SELECT
                p.id AS pedido_id,                                                          -- ID do pedido
                c.nome AS cliente_nome,                                                     -- Nome do cliente
                p.valor_total,                                                              -- Valor total do pedido
                ps.descricao AS situacao,                                                   -- Situação do pedido
                fp.descricao AS forma_pagamento,                                            -- Forma de pagamento utilizada
                CASE                                                                        -- Para o campo Elegivel
                    WHEN lg.id_gateway = 1 AND pp.id_formapagto = 3 AND p.id_situacao = 1   -- Gateway PagCompleto via Cartão de Crédito e Aguarda Pagamento
                    THEN 'Sim'
                    ELSE 'Não'
                END AS elegivel
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id                                          -- Nome do Cliente de acordo com o id_cliente
            JOIN pedido_situacao ps ON p.id_situacao = ps.id                                -- Situação do Pedido
            JOIN pedidos_pagamentos pp ON p.id = pp.id_pedido                               -- Informações de Pagamento do Pedido
            JOIN formas_pagamento fp ON pp.id_formapagto = fp.id                            -- Forma de Pagamento utilizada
            LEFT JOIN lojas_gateway lg ON p.id_loja = lg.id_loja                            -- Gateway
            ORDER BY p.id                                                                   -- Ordena por ID do Pedido
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return []; // Retorna um array vazio em caso de erro
    }
}

/**
 * Função Principal -> Conectar, Buscar, Processar e Exibir
 **/

try {
    // Conectar ao banco
    $pdo = getPDOConnection();
    $statusDB = "Conectado com sucesso!";

    // Fetch the data, Jack!
    $orders = listOrders($pdo);
    // Deveria fazer uma tratativa de erro aqui, mas vamos assumir que a função sempre retorna um array. Se não retornar, ao menos da para enxergar.

} catch (Exception $e) {
    $statusDB = "Erro na conexão: " . $e->getMessage();
    $data = ['elegiveis' => 0];
    $orders = [];
}

// Processar mensagens vindas dos scripts (para converter em PopUps!)
$message = '';
$messageType = '';

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'no_orders':
            $message = 'Nenhum pedido encontrado.';
            $messageType = 'warning';
            break;

        case 'process_complete':
            $processed = $_GET['processed'] ?? 0;
            $approved = $_GET['approved'] ?? 0;
            $rejected = $_GET['rejected'] ?? 0;
            $errored = $_GET['errored'] ?? 0; // Past-Tense de "to error"
            $debug = isset($_GET['debug']) ? ' (DEBUG MODE) ' : '';

            $message = "Processamento Concluído! {$debug}<br>";
            $message .= "Pedidos Processados: {$processed}<br>";
            $message .= "Aprovados: {$approved}<br>";
            $message .= "Recusados: {$rejected}<br>";
            $message .= "Com Erro: {$errored}<br>";
            $messageType = 'success';
            break;

        case 'reset_success':
            $files = $_GET['files'] ?? 0;
            $tables = $_GET['tables'] ?? 0;
            $message = "Banco de dados resetado com sucesso!<br>";
            $message .= "Arquivos SQL re-injetados: {$files}<br>";
            $message .= "Tabelas reiniciadas: {$tables}<br>";
            $messageType = 'success';
            break;

        case 'reset_error':
            $error = $_GET['error'] ?? 'Erro indefinido!';
            $message = "Erro ao resetar o banco de dados:<br>" . htmlspecialchars($error);
            $messageType = 'danger';
            break;

        case 'err':
            $error = $_GET['error'] ?? 'Erro indefinido!';
            $message = "Erro: " . htmlspecialchars($error); // Erro, erro, erro!
            $messageType = 'danger';
            break;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAGCOMPLETO - Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-light">

    <!-- Cabeçalho -->

    <div class="container mt-4">
        <div class="col">
            <h1 class="display-6 text-primary">
                <i class="bi bi-credit-card"></i> PAGCOMPLETO Dashboard
            </h1>
            <p class="text-muted">Sistema de Integração de Pagamentos</p>
        </div>
    </div>

    <!-- Status da Conexão -->
    <div class="row mb-4">
        <div class="col">
            <div class="alert <?= strpos($statusDB, 'Erro') !== false ? 'alert-danger' : 'alert-success' ?>">
                <i class="bi bi-database"></i> <strong>Status do Banco:</strong> <?= htmlspecialchars($statusDB) ?>
                <?php if (DEVELOPMENT_MODE): ?>
                    <br><i class="bi bi-exclamation-triangle text-warning"></i> <strong>MODO DESENVOLVIMENTO ATIVO</strong>
                    - API simulada
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="bi bi-gear"></i> Ações do Sistema
                    </h5>
                    <div class="d-grid gap-2 d-md-flex">
                        <form action="process.php" method="post" class="me-md-2">
                            <button type="submit" class="btn btn-warning btn-lg"
                                onclick="return confirm('Processar todos os pedidos elegíveis agora?');">
                                <i class="bi bi-play-circle"></i> Processar Pedidos Elegíveis
                            </button>
                        </form>

                        <form action="reset.php" method="post">
                            <button type="submit" class="btn btn-danger btn-lg"
                                onclick="return confirm('ATENÇÃO: Isso irá resetar TODOS os dados do banco. Tem certeza?');">
                                <i class="bi bi-arrow-clockwise"></i> Resetar Banco de Dados
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela de Pedidos -->
    <div class="row">
        <div class="col">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul"></i> Lista de Pedidos
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Nenhum pedido encontrado no banco de dados.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Valor Total</th>
                                        <th>Forma Pagamento</th>
                                        <th>Status</th>
                                        <th>Elegível</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="<?= $order['elegivel'] == 'Sim' ? 'table-warning' : '' ?>">
                                            <td><?= htmlspecialchars($order['pedido_id']) ?></td>
                                            <td><?= htmlspecialchars($order['cliente_nome']) ?></td>
                                            <td>R$ <?= number_format($order['valor_total'], 2, ',', '.') ?></td>
                                            <td><?= htmlspecialchars($order['forma_pagamento']) ?></td>
                                            <td>
                                                <span class="badge <?=
                                                    $order['situacao'] == 'Aguardando Pagamento' ? 'bg-warning' :
                                                    ($order['situacao'] == 'Pagamento Identificado' ? 'bg-success' : 'bg-secondary')
                                                    ?>">
                                                    <?= htmlspecialchars($order['situacao']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($order['elegivel'] == 'Sim'): ?>
                                                    <span class="badge bg-primary">
                                                        <i class="bi bi-check-circle"></i> Sim
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Não</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Mensagem temporária (será convertida em popup na próxima fase) -->
    <?php if ($message): ?>
        <div class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1050;">
            <div class="alert alert-<?= $tipoMensagem ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script para auto-remover mensagens após 5 segundos -->
    <!--
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
-->

</body>

</html>
