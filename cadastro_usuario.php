<?php
require 'includes/header.php';
require 'config/database.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $nivel = $_POST['nivel'];

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome,email,senha,nivel) VALUES (?,?,?,?)");
        $stmt->execute([$nome, $email, $senha, $nivel]);
        $msg = "✅ Usuário cadastrado com sucesso!";
    } catch (PDOException $e) {
        $msg = "❌ Erro: e-mail já cadastrado.";
    }
}
?>
<h1>Cadastro de Usuários</h1>
<?php if ($msg): ?><p class="msg"><?= $msg ?></p><?php endif; ?>
<form method="POST" class="form-grid">
    <input type="text" name="nome" placeholder="Nome completo" required>
    <input type="email" name="email" placeholder="E-mail" required>
    <input type="password" name="senha" placeholder="Senha" required>
    <select name="nivel" required>
        <option value="vendedor">Vendedor</option>
        <option value="admin">Administrador</option>
    </select>
    <button type="submit">Cadastrar</button>
</form>

<h2>Usuários cadastrados</h2>
<table>
    <tr><th>Nome</th><th>E-mail</th><th>Nível</th></tr>
    <?php foreach ($pdo->query("SELECT nome,email,nivel FROM usuarios ORDER BY nome") as $u): ?>
    <tr><td><?= htmlspecialchars($u['nome']) ?></td><td><?= $u['email'] ?></td><td><?= $u['nivel'] ?></td></tr>
    <?php endforeach; ?>
</table>
<?php require 'includes/footer.php'; ?>