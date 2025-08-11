<?php

// Configurações do Integrador PagCompleto

// Banco de Dados
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'pagcompleto_db');
define('DB_USER', 'pagcompleto_user');
define('DB_PASS', 'pagcompleto_password');

// API Externa
define('API_URL', 'https://apiinterna.ecompleto.com.br');
define('API_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOjI2ODQsInN0b3JlSWQiOjE5NzksImlhdCI6MTc1Mzk2MjIwOCwiZXhwIjoxNzU2Njg0Nzk5fQ.WlLjEihOHihKoznQkQLvVGIvYjJ4WmpoikSZmuTZ7oU');

// Modo de Desenvolvimento - Impede as chamadas reais à API
define('DEVELOPMENT_MODE', false); 

/*
Função para conectar à Database através do PDO retornando uma conexão PDO pre-configurada ou terminando o script em caso de erro.
*/

function getPDOConnection() {
	try {
		// Construir um DSN (Data Source Name)
		$dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;

		// Cria uma nova instância PDO com configurações customizadas
		$pdo = new PDO(
			$dsn,
			DB_USER,
			DB_PASS,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // Define que qualquer erro no PDO (Conexão, consulta, etc...) gere uma Exceção (PDOException) ao invés de um "false"
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Retorna arrays associativos (fetch() ou fetchAll() traga Colunas->Valores sem duplicatas)
                PDO::ATTR_EMULATE_PREPARES => false,            // Desliga a emulação de prepared statements, usando consultas nativas do banco de dados diretamente - a substituição de parâmetros é feita pelo próprio banco.
                PDO::ATTR_STRINGIFY_FETCHES => false            // Manter tipos de dados originais (Principalmente evita que números virem strings)
			]
		);

		return $pdo;

	} catch (PDOException $e) {
		die('Erro ao conectar ao banco de dados: ' . $e->getMessage()); // !! Verificar !! die() pode não ser a melhor forma de finalizar o script.
	}
}

/*
Função para testar se a conexão com o banco de dados está funcionando.
*/

function testDbConnection() {
	try {
		$pdo = getPDOConnection();
		// Consulta Dummy
		$pdo->query("SELECT 1");
		return true;
	} catch (PDOException $e) {
		return false;
	}
}

/*
	Função para Simular resposta da API (apenas em modo de desenvolvimento).
	Baseada nas seguintes respostas adquiridas:
	Pedido 98302 → HTTP 200 → {"Error":false,"Transaction_code":"00","Message":"Pagamento Aprovado"}
	Pedido 98306 → HTTP 200 → {"Error":false,"Transaction_code":"04","Message":"Pagamento Recusado. Cartão sem crédito disponível."}
	Pedido 98307 → HTTP 200 → {"Error":false,"Transaction_code":"00","Message":"Pagamento Aprovado"}
	Pedido 98308 → HTTP 200 → {"Error":false,"Transaction_code":"00","Message":"Pagamento Aprovado"}
	Caso utilize-se um caso não esperado, retorna uma resposta genérica.
*/

function simulateResponse($orderId) {
	// Simula um tempo risório de resposta da API
	usleep(500000); // 0.5 segundos

	// Respostas pré-definidas
	$responses = [
		98302 => ['Error' => false, 'Transaction_code' => '00', 'Message' => 'Pagamento Aprovado(D)'],
		98306 => ['Error' => false, 'Transaction_code' => '04', 'Message' => 'Pagamento Recusado. Cartão sem crédito disponível.(D)'],
		98307 => ['Error' => false, 'Transaction_code' => '00', 'Message' => 'Pagamento Aprovado(D)'],
		98308 => ['Error' => false, 'Transaction_code' => '00', 'Message' => 'Pagamento Aprovado(D)']
	];

	// Retorna se o pedido existe no array
	if (isset($responses[$orderId])) {
		return $responses[$orderId];
	}

	// Resposta Genérica
	return ['Error' => true, 'Message' => 'Número de pedido inválido ou não encontrado.(D)'];
}

?>
