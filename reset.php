<?php

// Habilita a exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

/**
 * Função para resetar o banco de dados
 * Executa todos os arquivos .sql encontrados na pasta especificada
 */

function resetDatabase()
{
	// Mesma forma que process.php, mas com métodos diferentes.
	try {
		$pdo = getPDOConnection();

		// Define o caminho dos arquivos SQL
		$sqlDir = 'sql/';

		// Verifica se o diretório existe
		if (!is_dir($sqlDir)) {
			throw new Exception("Diretório de SQL não encontrado: " . $sqlDir);
		}

		// Buscar e "globar" todos os .sql
		$sqlFiles = glob($sqlDir . '*.sql');

		// Verifica se encontrou arquivos defacto
		if (empty($sqlFiles)) {
			throw new Exception('Nenhum arquivo SQL encontrado no diretório: ' . $sqlDir);
		}

		// Inicia a transação (Garante que TODOS sejam executados ou NENHUM)
		$pdo->beginTransaction();

		// Primeiro, remover todas as tabelas
		$stmt = $pdo->prepare("
			SELECT tablename
			FROM pg_tables
			WHERE schemaname = 'public' AND tableowner = :owner
		");
		$stmt->execute([':owner' => DB_USER]);
		$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

		// Iterar drop de cada
		foreach ($tables as $table) {
			$pdo->exec("DROP TABLE IF EXISTS \"{$table}\" CASCADE");
		}

		// Executar cada arquivo SQL
		foreach ($sqlFiles as $file) {
			$sql = file_get_contents($file);
			if ($sql === false) {
				throw new Exception("Erro ao ler o arquivo: " . $file);
			}

			// Executa o SQL
			$pdo->exec($sql);
		}

		// Commit da transação
		$pdo->commit();

		return [
			'success' => true,
			'files' => count($sqlFiles),
			'tables' => count($tables)
		];
	} catch (Exception $e) { // Captura qualquer erro (ao invés de apenas PDOException)
		// Rollback em caso de erro
		if (isset($pdo) && $pdo->inTransaction()) {
			$pdo->rollBack();
		}
		return [
			'success' => false,
			'error' => "Rollback! Erro ao resetar tabelas: " . $e->getMessage()
		];
	}
}

// Processamento de Reset

// Verificar se foi uma requisição POST (Clique no Botão)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$result = resetDatabase();

	if ($result['success']) {
		$params = "message=reset_success&files={$result['files']}&tables={$result['tables']}";
		header("Location: index.php?$params");
	} else {
		header("Location: index.php?message=reset_error&error=" . urlencode($result['error']));
	}
	exit;
}


// Se chegou aqui, foi acesso direto (não deveria acontecer)
header("Location: index.php");
exit;

?>
