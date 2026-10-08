<?php
/**
 * ============================================================
 * LOGIN - Padaria do Gil
 * ============================================================
 */

// 1. Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Se já logado → redireciona
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// 3. Conexão
require_once 'config/database.php';

// ============================================================
// CONTROLE DE TENTATIVAS (anti-bruteforce simples)
// ============================================================
$MAX_TENTATIVAS = 5;
$BLOQUEIO_MIN   = 5; // minutos

if (!isset($_SESSION['login_tentativas'])) {
    $_SESSION['login_tentativas'] = 0;
    $_SESSION['login_bloqueado_ate'] = 0;
}

$bloqueado = $_SESSION['login_bloqueado_ate'] > time();

$erro    = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($bloqueado) {
        $min = ceil(($_SESSION['login_bloqueado_ate'] - time()) / 60);
        $erro = "Muitas tentativas. Tente novamente em {$min} minuto(s).";
    } else {
        $email   = trim($_POST['email'] ?? '');
        $senha   = $_POST['senha'] ?? '';
        $lembrar = !empty($_POST['lembrar']);

        if ($email === '' || $senha === '') {
            $erro = "Preencha e-mail e senha.";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['senha'])) {
                // ---------- Login OK ----------
                $_SESSION['usuario_id']    = $user['id'];
                $_SESSION['usuario_nome']  = $user['nome'];
                $_SESSION['usuario_nivel'] = $user['nivel'];
                $_SESSION['login_tentativas'] = 0;

                // Lembrar-me (cookie 30 dias)
                if ($lembrar) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('padaria_lembrar', $user['email'], [
                        'expires'  => time() + (86400 * 30),
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                // Atualiza último acesso (se coluna existir)
                try {
                    $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?")
                        ->execute([$user['id']]);
                } catch (PDOException $e) {
                    // silencioso — coluna pode não existir
                }

                header('Location: index.php');
                exit;
            } else {
                // ---------- Falha ----------
                $_SESSION['login_tentativas']++;

                if ($_SESSION['login_tentativas'] >= $MAX_TENTATIVAS) {
                    $_SESSION['login_bloqueado_ate'] = time() + ($BLOQUEIO_MIN * 60);
                    $erro = "Muitas tentativas. Conta bloqueada por {$BLOQUEIO_MIN} minutos.";
                } else {
                    $restantes = $MAX_TENTATIVAS - $_SESSION['login_tentativas'];
                    $erro = "E-mail ou senha inválidos. Tentativas restantes: {$restantes}.";
                }
            }
        }
    }
}

// Pré-preenche o e-mail se tiver cookie
$emailCookie = $_COOKIE['padaria_lembrar'] ?? '';

// Saudação e data
$hora = (int)date('H');
if     ($hora < 12) $saudacao = "Bom dia";
elseif ($hora < 18) $saudacao = "Boa tarde";
else                $saudacao = "Boa noite";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Padaria do Gil</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <!-- Ícone da aba (opcional) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥖</text></svg>">
</head>
<body class="login-page">

    <!-- ============ PAINEL ESQUERDO (visual) ============ -->
    <aside class="login-visual">
        <div class="login-visual-overlay"></div>

        <div class="login-visual-content">
            <div class="login-marca">
                <span class="login-marca-icone">🥖</span>
                <h2>Padaria do Gil</h2>
                <p>Pão quentinho, gestão inteligente.</p>
            </div>

            <div class="login-visual-features">
                <div class="feature-item">
                    <span class="feature-icone">📊</span>
                    <div>
                        <strong>Relatórios em tempo real</strong>
                        <small>Acompanhe suas vendas e lucros</small>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icone">🛒</span>
                    <div>
                        <strong>Vendas rápidas</strong>
                        <small>À vista e a prazo em segundos</small>
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icone">👥</span>
                    <div>
                        <strong>Controle de clientes</strong>
                        <small>Limites e fiados organizados</small>
                    </div>
                </div>
            </div>

            <p class="login-visual-rodape">
                &copy; <?= date('Y') ?> Padaria do Gil · Todos os direitos reservados
            </p>
        </div>
    </aside>

    <!-- ============ PAINEL DIREITO (formulário) ============ -->
    <main class="login-form-side">

        <div class="login-box-novo">
            <!-- Cabeçalho mobile (mostra a marca) -->
            <div class="login-marca-mobile">
                <span>🥖</span> Padaria do Gil
            </div>

            <div class="login-cabecalho">
                <span class="login-saudacao"><?= $saudacao ?> 👋</span>
                <h1>Bem-vindo de volta</h1>
                <p>Entre com suas credenciais para continuar</p>
            </div>

            <?php if ($erro): ?>
                <div class="login-alerta login-alerta-erro">
                    <span class="login-alerta-icone">⚠️</span>
                    <span><?= htmlspecialchars($erro) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($sucesso): ?>
                <div class="login-alerta login-alerta-sucesso">
                    <span class="login-alerta-icone">✅</span>
                    <span><?= htmlspecialchars($sucesso) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="formLogin" autocomplete="on" novalidate>
                <!-- E-mail -->
                <div class="campo-login">
                    <label for="email">E-mail</label>
                    <div class="input-wrap">
                        <span class="input-icone">📧</span>
                        <input type="email"
                               name="email"
                               id="email"
                               placeholder="seu@email.com"
                               value="<?= htmlspecialchars($emailCookie ?: ($_POST['email'] ?? '')) ?>"
                               required
                               autocomplete="email"
                               <?= $bloqueado ? 'disabled' : '' ?>>
                    </div>
                    <small class="campo-erro" id="erroEmail"></small>
                </div>

                <!-- Senha -->
                <div class="campo-login">
                    <label for="senha">Senha</label>
                    <div class="input-wrap">
                        <span class="input-icone">🔒</span>
                        <input type="password"
                               name="senha"
                               id="senha"
                               placeholder="••••••••"
                               required
                               autocomplete="current-password"
                               <?= $bloqueado ? 'disabled' : '' ?>>
                        <button type="button"
                                class="btn-mostrar-senha"
                                id="btnMostrarSenha"
                                aria-label="Mostrar senha"
                                title="Mostrar/ocultar senha"
                                tabindex="-1">
                            👁️
                        </button>
                    </div>
                    <small class="campo-erro" id="erroSenha"></small>
                </div>

                <!-- Opções -->
                <div class="opcoes-login">
                    <label class="check-lembrar">
                        <input type="checkbox" name="lembrar" value="1"
                               <?= $emailCookie ? 'checked' : '' ?>>
                        <span class="check-custom"></span>
                        <span>Lembrar de mim</span>
                    </label>
                    <a href="#" class="link-esqueci" onclick="return esqueciSenha()">
                        Esqueci minha senha
                    </a>
                </div>

                <!-- Botão -->
                <button type="submit"
                        class="btn-entrar"
                        id="btnEntrar"
                        <?= $bloqueado ? 'disabled' : '' ?>>
                    <span class="btn-entrar-texto">
                        <?= $bloqueado ? '🔒 Bloqueado' : '🔓 Entrar' ?>
                    </span>
                    <span class="btn-entrar-loading" style="display: none;">
                        <span class="spinner"></span> Entrando...
                    </span>
                </button>
            </form>

            <div class="login-rodape">
                <p>Sistema exclusivo para colaboradores da <strong>Padaria do Gil</strong>.</p>
            </div>
        </div>
    </main>

<script>
// ============================================================
// MOSTRAR / OCULTAR SENHA
// ============================================================
document.getElementById('btnMostrarSenha')?.addEventListener('click', function () {
    const input = document.getElementById('senha');
    const mostrar = input.type === 'password';

    input.type = mostrar ? 'text' : 'password';
    this.textContent = mostrar ? '🙈' : '👁️';
    this.setAttribute('aria-label', mostrar ? 'Ocultar senha' : 'Mostrar senha');
    this.classList.toggle('ativo', mostrar);
    input.focus();
});

// ============================================================
// VALIDAÇÃO EM TEMPO REAL
// ============================================================
const campoEmail = document.getElementById('email');
const campoSenha = document.getElementById('senha');
const erroEmail  = document.getElementById('erroEmail');
const erroSenha  = document.getElementById('erroSenha');

campoEmail?.addEventListener('blur', validarEmail);
campoSenha?.addEventListener('blur', validarSenha);

campoEmail?.addEventListener('input', () => {
    if (erroEmail.textContent) validarEmail();
});
campoSenha?.addEventListener('input', () => {
    if (erroSenha.textContent) validarSenha();
});

function validarEmail() {
    const v = campoEmail.value.trim();
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!v) {
        erroEmail.textContent = 'Informe seu e-mail.';
        campoEmail.classList.add('campo-invalido');
        return false;
    }
    if (!re.test(v)) {
        erroEmail.textContent = 'E-mail inválido.';
        campoEmail.classList.add('campo-invalido');
        return false;
    }
    erroEmail.textContent = '';
    campoEmail.classList.remove('campo-invalido');
    return true;
}

function validarSenha() {
    const v = campoSenha.value;
    if (!v) {
        erroSenha.textContent = 'Informe sua senha.';
        campoSenha.classList.add('campo-invalido');
        return false;
    }
    if (v.length < 4) {
        erroSenha.textContent = 'Senha muito curta.';
        campoSenha.classList.add('campo-invalido');
        return false;
    }
    erroSenha.textContent = '';
    campoSenha.classList.remove('campo-invalido');
    return true;
}

// ============================================================
// SUBMIT COM VALIDAÇÃO + LOADING
// ============================================================
document.getElementById('formLogin')?.addEventListener('submit', function (e) {
    const okEmail = validarEmail();
    const okSenha = validarSenha();

    if (!okEmail || !okSenha) {
        e.preventDefault();

        // Shake no formulário
        this.classList.add('form-shake');
        setTimeout(() => this.classList.remove('form-shake'), 500);
        return;
    }

    // Mostra loading
    const btn = document.getElementById('btnEntrar');
    const txt = btn.querySelector('.btn-entrar-texto');
    const ldr = btn.querySelector('.btn-entrar-loading');

    txt.style.display = 'none';
    ldr.style.display = 'inline-flex';
    btn.disabled = true;
});

// ============================================================
// LINK "ESQUECI SENHA"
// ============================================================
function esqueciSenha() {
    alert('🔐 Entre em contato com o administrador do sistema para redefinir sua senha.');
    return false;
}

// ============================================================
// FOCO AUTOMÁTICO
// ============================================================
window.addEventListener('load', () => {
    const email = document.getElementById('email');
    const senha = document.getElementById('senha');

    if (email && !email.value)  email.focus();
    else if (senha && !senha.disabled) senha.focus();
});

// ============================================================
// ANIMAÇÃO LEVE NOS FEATURES
// ============================================================
document.querySelectorAll('.feature-item').forEach((el, i) => {
    el.style.animation = `fadeInLeft .6s ease ${0.2 + i * 0.12}s both`;
});
</script>
</body>
</html>