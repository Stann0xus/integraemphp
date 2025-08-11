<?php

// Habilita a exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

/**
 * Função para corrigir data de vencimento
 * EX: 2022-08 -> 0822
 **/

function convertDate($expdate)
{
    $parts = explode('-', trim($expdate));
    if (count($parts) === 2) {
        $year = $parts[0];
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT); // Garante 2 dígitos para o mês
        return $month . substr($year, -2); // Retorna MMAA (MMYY)
    }

    // Fallback (NUNCA HAVERÁ!)
    return '1299';
}

/**
 * Função para buscar os pedidos elegíveis para processamento
 **/

function getEligibleOrders($pdo)
{
    $sql = "
    SELECT
        p.id AS external_order_id,
        p.valor_total AS amount,
        pp.num_cartao,
        pp.codigo_verificacao,
        pp.vencimento,
        pp.nome_portador,
        c.id AS customer_external_id,
        c.nome AS customer_name,
        c.tipo_pessoa,
        c.email,
        c.cpf_cnpj,
        c.data_nasc
    FROM pedidos p
    JOIN clientes c ON p.id_cliente = c.id
    JOIN pedidos_pagamentos pp ON p.id = pp.id_pedido
    JOIN lojas_gateway lg ON p.id_loja = lg.id_loja
    WHERE lg.id_gateway = 1                            -- PAGCOMPLETO
        AND pp.id_formapagto = 3                       -- Cartão de Crédito
        AND p.id_situacao = 1                          -- Aguardando Pagamento
    ORDER BY p.id
    ";

    try {
        $stmt = $pdo->prepare($sql); // Prepara antes de consultar
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception("Erro ao buscar pedidos: " . $e->getMessage());
    }
}

/**
 * Função para montar o JSON de Payload para a API Externa
 **/

function buildPayLoad($order)
{
    return [
        "external_order_id" => (int) $order['external_order_id'],
        "amount" => (float) $order['amount'],
        "card_number" => preg_replace('/\D/', '', $order['num_cartao']), // Remove caracteres não numéricos
        "card_cvv" => str_pad($order['codigo_verificacao'], 3, '0', STR_PAD_LEFT), // Garante 3 dígitos
        "card_expiration_date" => convertDate($order['vencimento']), // Converte data de vencimento
        "card_holder_name" => $order['nome_portador'],
        "customer" => [
            "external_id" => (string) $order['customer_external_id'],
            "name" => $order['customer_name'],
            "type" => ($order['tipo_pessoa'] === 'F') ? 'individual' : 'corporation',
            "email" => $order['email'],
            "documents" => [
                [
                    "type" => "cpf",
                    "number" => preg_replace('/\D/', '', $order['cpf_cnpj']) // Remove caracteres não numéricos
                ]
            ],
            "birthday" => $order['data_nasc']
        ]
    ];
}

/**
 * Enviar a requisição para a API Externa
 **/

function sendPOSTtoAPI($payload)
{

    // Se em Debug, simula a resposta
    if (DEVELOPMENT_MODE) {
        $fakeResponse = simulateResponse($payload['external_order_id']);
        return [
            'success' => true,
            'json_response' => $fakeResponse,
            'raw_response' => json_encode($fakeResponse),
            'http_code' => 200
        ];
    }

    // Configuração cURL para a requisição
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, API_URL . "/exams/processTransaction?accessToken=" . API_TOKEN);  // URL + Path + Token
    curl_setopt($ch, CURLOPT_POST, true);   // Método POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); // Payload JSON
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]); // Cabeçalho JSON
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Timeout de 20 segundos

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Verifica se há erro na requisição
    if ($error) {
        return [
            'success' => false,
            'error' => "cURL Error: " . $error
        ];
    }

    // Verifica o código HTTP
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP {$httpCode}: {$response}",
        ];
    }

    // Tentativa de decodificação do JSON
    $jsonResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            "success" => false,
            'error' => "Resposta Inválida da API: " . json_last_error_msg()
        ];
    }

    return [
        'success' => true,
        'json_response' => $jsonResponse,
        'raw_response' => $response,
        'http_code' => $httpCode
    ];
}

/**
 * Função para atualizar o banco de dados após processamento (Atualiza Status e JSON -> retorno_intermediador @ pedidos_pagamentos)
 */

function updateOrder($pdo, $orderId, $APIResponse)
{
    try {
        // Inicia a transação
        $pdo->beginTransaction();

        // Determina um novo status baseado na resposta da API
        $newStatus = 3; // Cancelado por padrão
        if (
            isset($APIResponse['json_response']['Transaction_code']) &&
            $APIResponse['json_response']['Transaction_code'] === '00'
        ) {
            $newStatus = 2; // Pago com Sucesso
        }

        // Atualizar tabela pedidos_pagamentos
        $stmt1 = $pdo->prepare("
            UPDATE pedidos_pagamentos
            SET retorno_intermediador = :api_response,
                data_processamento = NOW()
            WHERE id_pedido = :order_id
        ");
        $stmt1->execute([
            ':api_response' => $APIResponse['raw_response'],
            ':order_id' => $orderId
        ]);

        // Atualizar tabela pedidos (status)
        $stmt2 = $pdo->prepare("
            UPDATE pedidos
            SET id_situacao = :new_status
            WHERE id = :order_id
        ");
        $stmt2->execute([
            ':new_status' => $newStatus,
            ':order_id' => $orderId
        ]);

        // Confirmar a transação
        $pdo->commit();

        return [
            'success' => true,
            'new_status' => $newStatus
        ];
    } catch (PDOException $e) {
        // Reverter a transação em caso de erro
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => "Rollback! Erro ao atualizar pedido: " . $e->getMessage()
        ];
    }
}

/**
 * Processamento Principal - Tenta processar e iterar cada pedido e retorna resultados.
 */

try {
    // Conectar ao Banco
    $pdo = getPDOConnection();

    // Buscar pedidos elegíveis
    $orders = getEligibleOrders($pdo);

    // Verifica se encontrou pedidos
    if (empty($orders)) {
        // Redireciona como mensagem (para o PopUp!)
        header("Location: index.php?message=no_orders");
        exit;
    }

    $processed = 0;
    $approved = 0;
    $rejected = 0;
    $errored = 0; // "Those which had errors" - "Aqueles em que houveram tiveram erros"

    // Iterar sobre cada pedido
    foreach ($orders as $order) {

        // Monta a Payload
        $payload = buildPayLoad($order);

        // Enviar para a API
        $APIResponse = sendPOSTtoAPI($payload);

        if (!$APIResponse['success']) {
            $errored = 1;
            continue; // Bola pra frente
        }

        // Atualizar o banco de dados
        $databaseResult = updateOrder($pdo, $order['external_order_id'], $APIResponse);

        if ($databaseResult['success']) {
            $processed++;
            if ($databaseResult['new_status'] === 2) { // Pago com Sucesso
                $approved++;
            } else {
                $rejected++; // Cancelado por qualquer Motivo
            }
        } else {
            $errored++;
            continue;
        }
    }

    // Redireciona como mensagem
    $result = "processed={$processed}&approved={$approved}&rejected={$rejected}&errored={$errored}";
    if (DEVELOPMENT_MODE) {
        $result .= "&debug=1";
    }
    header("Location: index.php?message=process_complete&{$result}");
    exit;
} catch (Exception $e) {
    // Redirecionar com erro
    header("Location: index.php?message=err&error=" . urlencode($e->getMessage()));
    exit;
}

?>