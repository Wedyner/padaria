<?php
/**
 * ============================================================
 * SCRIPT: criar_admin.php
 * FUNÇÃO: Criar um usuário administrador padrão no sistema
 * PROJETO: Padaria do Gil
 * ============================================================
 */

// ============================================================
// 1. CONFIGURAÇÃO INICIAL
// ============================================================

// Inclui o arquivo de configuração que contém a conexão com o banco (PDO)
// Este arquivo está em: config/database.php
require_once 'config/database.php';

// ============================================================
// 2. DEFINIÇÃO DOS DADOS DO ADMINISTRADOR PADRÃO
// ============================================================

// Array associativo com os dados do admin.
// A estrutura DEVE corresponder às colunas da tabela 'usuarios':
//   id | nome | email | senha | nivel | criado_em
$adminData = [
    'nome'  => 'Administrador Padrão',
    // Nome completo exibido no sistema

    'email' => 'admin@padaria.com',
    // E-mail único usado para login

    'senha' => password_hash('admin123', PASSWORD_DEFAULT),
    // password_hash() cria um hash seguro (bcrypt) da senha
    // ⚠️ NUNCA armazene senhas em texto puro!

    'nivel' => 'admin'
    // Nível de acesso: 'admin' (total) ou 'vendedor' (limitado)
];

// ============================================================
// 3. BLOCO PRINCIPAL COM TRATAMENTO DE EXCEÇÕES
// ============================================================

$criado = false;       // Flag para saber se foi criado agora
$existente = false;    // Flag para saber se já existia

try {

    // --------------------------------------------------------
    // 3.1 VERIFICAÇÃO DE EXISTÊNCIA (EVITAR DUPLICIDADE)
    // --------------------------------------------------------
    // Previne a criação de múltiplos admins com o mesmo e-mail.
    // O uso de prepared statements previne SQL Injection.

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    // O "?" é um placeholder que será substituído pelo valor seguro

    $stmt->execute([$adminData['email']]);
    // O array é passado como parâmetro - o PDO escapa automaticamente

    $existingAdmin = $stmt->fetch();
    // fetch() retorna a linha encontrada ou FALSE se não existir

    // --------------------------------------------------------
    // 3.2 DECISÃO: CRIAR OU NÃO CRIAR
    // --------------------------------------------------------

    if ($existingAdmin) {
        // CASO 1: Administrador JÁ EXISTE
        $existente = true;

    } else {
        // CASO 2: Administrador NÃO EXISTE - Vamos criá-lo

        // ----------------------------------------------------
        // 3.3 PREPARAÇÃO DA QUERY DE INSERÇÃO
        // ----------------------------------------------------
        // Usando placeholders nomeados (:nome, :email, ...) - mais legível
        // A tabela 'usuarios' já tem 'criado_em' com DEFAULT CURRENT_TIMESTAMP,
        // então não precisamos passar essa coluna manualmente.

        $sql = "INSERT INTO usuarios (nome, email, senha, nivel)
                VALUES (:nome, :email, :senha, :nivel)";

        // ----------------------------------------------------
        // 3.4 EXECUÇÃO DA INSERÇÃO
        // ----------------------------------------------------
        $stmt = $pdo->prepare($sql);
        // prepare() retorna um objeto PDOStatement pronto para executar

        $stmt->execute($adminData);
        // execute() substitui os placeholders pelos valores do array
        // ⚠️ As chaves do array DEVEM ter o MESMO nome dos placeholders

        $criado = true;
    }

// ============================================================
// 4. TRATAMENTO DE EXCEÇÕES (CAPTURA DE ERROS)
// ============================================================
// Captura qualquer exceção do tipo PDOException que possa ocorrer

} catch (PDOException $e) {
    // PDOException é lançada quando há erros de:
    // - Conexão com o banco de dados
    // - Sintaxe SQL inválida
    // - Violação de constraints (duplicidade, chave estrangeira, etc.)
    // - Tabela inexistente

    $erro = $e->getMessage();

    // Opcional: registrar em log para análise posterior
    // error_log("Erro em criar_admin.php: " . $erro);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Criar Admin - Padaria do Gil</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .setup-box {
            max-width: 560px;
            margin: 70px auto;
            background: #fff;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,.15);
            font-family: 'Segoe UI', sans-serif;
        }
        .setup-box h1 {
            color: #8b5e34;
            text-align: center;
            margin-bottom: 25px;
        }
        .alert {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 15px;
            border-left: 5px solid;
        }
        .alert-info    { background: #d1ecf1; color: #0c5460; border-color: #17a2b8; }
        .alert-success { background: #d4edda; color: #155724; border-color: #27ae60; }
        .alert-danger  { background: #f8d7da; color: #721c24; border-color: #c0392b; }
        .credenciais {
            background: #f8f5ef;
            padding: 18px;
            border-radius: 10px;
            margin: 18px 0;
            border-left: 5px solid #8b5e34;
        }
        .credenciais p { margin: 6px 0; font-size: 15px; }
        .credenciais strong { color: #8b5e34; }
        .text-danger { color: #c0392b; font-weight: 600; }
        .btn {
            display: block;
            text-align: center;
            padding: 14px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 18px;
        }
        .btn-primary { background: #8b5e34; color: #fff; }
        .btn-primary:hover { background: #6b4423; }
    </style>
</head>
<body>
    <div class="setup-box">
        <h1>🥖 Padaria do Gil - Setup Admin</h1>

        <?php if (isset($erro)): ?>
            <!-- ERRO DE BANCO DE DADOS -->
            <div class="alert alert-danger">
                ❌ Erro ao criar administrador: <?= htmlspecialchars($erro) ?>
            </div>
            <div class="alert alert-info">
                🔧 Verifique se:
                <ul style="margin: 8px 0 0 20px;">
                    <li>O banco <strong>padaria_gil</strong> foi criado</li>
                    <li>A tabela <strong>usuarios</strong> existe</li>
                    <li>O arquivo <code>config/database.php</code> está correto</li>
                    <li>O MySQL está rodando</li>
                </ul>
            </div>

        <?php elseif ($existente): ?>
            <!-- ADMIN JÁ EXISTIA -->
            <div class="alert alert-info">
                ℹ️ O administrador já existe no sistema. Nada foi alterado.
            </div>
            <div class="credenciais">
                <p>📧 <strong>E-mail:</strong> <?= htmlspecialchars($adminData['email']) ?></p>
                <p>🔑 <strong>Senha:</strong> (a que você definiu anteriormente)</p>
            </div>

        <?php elseif ($criado): ?>
            <!-- ADMIN CRIADO AGORA -->
            <div class="alert alert-success">
                ✅ Administrador criado com sucesso!
            </div>
            <div class="credenciais">
                <p>👤 <strong>Nome:</strong> <?= htmlspecialchars($adminData['nome']) ?></p>
                <p>📧 <strong>E-mail:</strong> <?= htmlspecialchars($adminData['email']) ?></p>
                <p>🔑 <strong>Senha:</strong> admin123</p>
                <p>🛡️ <strong>Nível:</strong> <?= htmlspecialchars($adminData['nivel']) ?></p>
            </div>
            <p class="text-danger">
                ⚠️ IMPORTANTE: Altere esta senha após o primeiro login!
            </p>
        <?php endif; ?>

        <div class="alert alert-info" style="margin-top: 20px;">
            🗑️ <strong>Segurança:</strong> Delete o arquivo 
            <code>criar_admin.php</code> após usar!
        </div>

        <a href="login.php" class="btn btn-primary">🔐 Ir para o Login</a>
    </div>
</body>
</html>