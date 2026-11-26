<?php
/**
 * Configuração da Conexão com Banco de Dados MySQL
 * Sistema de Gestão de Estoque e Endereçamento 2D - SENAI
 *
 * Funciona tanto em ambiente local (Laragon, XAMPP) quanto em hospedagem online (cPanel, Hostinger, AWS, etc.)
 */

// Permite requisições CORS caso o frontend esteja em outro domínio/porta
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Chaves injetadas pelo GitHub Actions (.github/workflows/deploy.yml via sed)
$host = '__DB_HOST__';
$name = '__DB_NAME__';
$user = '__DB_USER__';
$pass = '__DB_PASS__';

// Se não foram substituídas no deploy, usa as credenciais locais padrão
define('DB_HOST', str_starts_with($host, '__DB_') ? '127.0.0.1' : $host);
define('DB_PORT', '3306');
define('DB_NAME', str_starts_with($name, '__DB_') ? 'senai_estoque_2d' : $name);
define('DB_USER', str_starts_with($user, '__DB_') ? 'root' : $user);
define('DB_PASS', str_starts_with($pass, '__DB_') ? 'mysql' : $pass);

/**
 * Retorna uma instância PDO configurada
 */
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Em caso de falha de conexão, retorna erro JSON padronizado
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Falha ao conectar no banco de dados MySQL: ' . $e->getMessage(),
                'hint'    => 'Verifique se o banco "' . DB_NAME . '" existe e se usuário/senha em config.php estão corretos.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}
