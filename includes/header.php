<?php
require_once __DIR__ . '/auth.php';

// ---------- Detecta página ativa ----------
$paginaAtual = basename($_SERVER['PHP_SELF']);

// ---------- Busca contadores para badges ----------
$badges = ['pendentes' => 0, 'estoque_baixo' => 0];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $badges['pendentes'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM vendas WHERE status='pendente'"
        )->fetchColumn();

        $badges['estoque_baixo'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM produtos WHERE categoria='estoque' AND estoque <= 5"
        )->fetchColumn();
    }
} catch (Throwable $e) {
    // silencioso — badges ficam zerados
}

// ---------- Dados do usuário ----------
$usuarioNome  = $_SESSION['usuario_nome']  ?? 'Usuário';
$usuarioNivel = $_SESSION['usuario_nivel'] ?? 'vendedor';

// Iniciais para o avatar
$partes = preg_split('/\s+/', trim($usuarioNome));
$iniciais = mb_strtoupper(
    mb_substr($partes[0] ?? '', 0, 1) .
    (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')
);

// Saudação
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
    <meta name="theme-color" content="#57381b">
    <title>Padaria do Gil · Sistema de Gestão</title>

    <!-- Favicon 🥖 -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🥖</text></svg>">

    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/js/sidebar.js">

    <!-- Aplica tema salvo ANTES de renderizar (evita flash) -->
    <script>
        (function () {
            const tema = localStorage.getItem('tema') || 'claro';
            if (tema === 'escuro') {
                document.documentElement.setAttribute('data-tema', 'escuro');
            }
        })();
    </script>
</head>
<body>

<!-- Camada de fundo sutil -->
<div class="app-bg" aria-hidden="true"></div>

<div class="layout" id="layout">

    <!-- ==================== SIDEBAR ==================== -->
    <aside class="sidebar" id="sidebar">

        <!-- Marca -->
        <div class="sidebar-marca">
            <div class="sidebar-logo">🥖</div>
            <div class="sidebar-marca-texto">
                <h1>Padaria do Gil</h1>
                <small>Sistema de Gestão</small>
            </div>
            <button type="button" class="sidebar-toggle-interno"
                    id="btnRecolher"
                    aria-label="Recolher menu"
                    title="Recolher menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
        </div>

        <!-- Navegação -->
        <nav class="sidebar-nav">

            <!-- Grupo: Principal -->
            <div class="nav-grupo">
                <span class="nav-grupo-label">Principal</span>

                <a href="index.php" class="nav-link <?= $paginaAtual === 'index.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Dashboard</span>
                </a>

                <a href="venda.php" class="nav-link <?= $paginaAtual === 'venda.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Venda à Vista</span>
                </a>

                <a href="venda_prazo.php" class="nav-link <?= $paginaAtual === 'venda_prazo.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Venda a Prazo</span>
                    <?php if ($badges['pendentes'] > 0): ?>
                        <span class="nav-badge nav-badge-alerta" title="<?= $badges['pendentes'] ?> fiados pendentes">
                            <?= $badges['pendentes'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Grupo: Cadastros -->
            <div class="nav-grupo">
                <span class="nav-grupo-label">Cadastros</span>

                <a href="cadastro_produto.php" class="nav-link <?= $paginaAtual === 'cadastro_produto.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Produtos</span>
                    <?php if ($badges['estoque_baixo'] > 0): ?>
                        <span class="nav-badge nav-badge-aviso" title="<?= $badges['estoque_baixo'] ?> com estoque baixo">
                            <?= $badges['estoque_baixo'] ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="cadastro_cliente.php" class="nav-link <?= $paginaAtual === 'cadastro_cliente.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Clientes</span>
                </a>
            </div>

            <!-- Grupo: Análise -->
            <div class="nav-grupo">
                <span class="nav-grupo-label">Análise</span>

                <a href="relatorio.php" class="nav-link <?= $paginaAtual === 'relatorio.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Relatórios</span>
                </a>

                <a href="cadastro_usuario.php" class="nav-link <?= $paginaAtual === 'cadastro_usuario.php' ? 'ativo' : '' ?>">
                    <span class="nav-icone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                    </span>
                    <span class="nav-texto">Usuários</span>
                </a>
            </div>
        </nav>

        <!-- Rodapé da sidebar -->
        <div class="sidebar-rodape">
            <div class="sidebar-user" id="sidebarUser">
                <span class="user-avatar"><?= htmlspecialchars($iniciais) ?></span>
                <div class="user-info">
                    <strong><?= htmlspecialchars($usuarioNome) ?></strong>
                    <small><?= ucfirst($usuarioNivel) ?></small>
                </div>
                <a href="logout.php" class="user-sair" title="Sair do sistema" aria-label="Sair">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Overlay para mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ==================== CONTEÚDO PRINCIPAL ==================== -->
    <main class="main" id="main">

        <!-- TOPBAR -->
        <header class="topbar">
            <!-- Botão mobile (menu) -->
            <button type="button" class="topbar-btn menu-toggle" id="menuToggle"
                    aria-label="Abrir menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>

            <!-- Saudação -->
            <div class="topbar-saudacao">
                <strong><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', $usuarioNome)[0]) ?> 👋</strong>
                <small><?= date('d/m/Y \· H:i') ?></small>
            </div>

            <!-- Ações -->
            <div class="topbar-acoes">

                <!-- Busca global -->
                <div class="topbar-busca">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="search" placeholder="Buscar... (Ctrl+K)" id="buscaGlobal" aria-label="Buscar">
                </div>

                <!-- Notificações -->
                <?php if ($badges['pendentes'] + $badges['estoque_baixo'] > 0): ?>
                    <button type="button" class="topbar-btn topbar-btn-notif"
                            id="btnNotif"
                            aria-label="Notificações"
                            title="Você tem <?= $badges['pendentes'] + $badges['estoque_baixo'] ?> alerta(s)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-dot"></span>
                    </button>
                <?php endif; ?>

                <!-- Dark mode toggle -->
                <button type="button" class="topbar-btn" id="btnTema" aria-label="Alternar tema" title="Alternar tema claro/escuro">
                    <span class="icone-tema-claro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"/>
                            <line x1="12" y1="1" x2="12" y2="3"/>
                            <line x1="12" y1="21" x2="12" y2="23"/>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
                            <line x1="1" y1="12" x2="3" y2="12"/>
                            <line x1="21" y1="12" x2="23" y2="12"/>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
                        </svg>
                    </span>
                    <span class="icone-tema-escuro" style="display:none;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                        </svg>
                    </span>
                </button>

                <!-- Atalho Nova Venda -->
                <a href="venda.php" class="topbar-cta" title="Nova venda (Alt+N)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    <span>Nova Venda</span>
                </a>
            </div>
        </header>

        <!-- CONTEÚDO -->
        <div class="content">