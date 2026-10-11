<?php
/**
 * ============================================================
 * SIDEBAR - Padaria do Gil
 * ============================================================
 * Requer que $pdo e $_SESSION estejam disponíveis.
 * Uso: require 'includes/sidebar.php';
 */

// ---------- Detecta página ativa ----------
$paginaAtual = basename($_SERVER['PHP_SELF']);

// ---------- Contadores para badges ----------
$badges = ['pendentes' => 0, 'estoque_baixo' => 0];

try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $badges['pendentes'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM vendas WHERE status = 'pendente'"
        )->fetchColumn();

        $badges['estoque_baixo'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM produtos
             WHERE categoria = 'estoque' AND estoque <= 5"
        )->fetchColumn();
    }
} catch (Throwable $e) {
    // silencioso — badges ficam zerados
}

// ---------- Dados do usuário logado ----------
$usuarioNome  = $_SESSION['usuario_nome']  ?? 'Usuário';
$usuarioNivel = $_SESSION['usuario_nivel'] ?? 'vendedor';

// Iniciais para o avatar (ex: "Ana Silva" → "AS")
$partes   = preg_split('/\s+/', trim($usuarioNome));
$iniciais = mb_strtoupper(
    mb_substr($partes[0] ?? '', 0, 1) .
    (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')
);

// ---------- Helper: marca link ativo ----------
function linkAtivo(string $arquivo, string $paginaAtual): string {
    return $arquivo === $paginaAtual ? ' ativo' : '';
}
?>

<!-- ==================== SIDEBAR ==================== -->
<aside class="sidebar" id="sidebar">

    <!-- ========== MARCA ========== -->
    <div class="sidebar-marca">
        <div class="sidebar-logo" aria-hidden="true">🥖</div>
        <div class="sidebar-marca-texto">
            <h1>Padaria do Gil</h1>
            <small>Sistema de Gestão</small>
        </div>
        <button type="button"
                class="sidebar-toggle-interno"
                id="btnRecolher"
                aria-label="Recolher menu"
                title="Recolher menu">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>
    </div>

    <!-- ========== NAVEGAÇÃO ========== -->
    <nav class="sidebar-nav" aria-label="Menu principal">

        <!-- ---------- Grupo: Principal ---------- -->
        <div class="nav-grupo">
            <span class="nav-grupo-label">Principal</span>

            <a href="index.php"
               class="nav-link<?= linkAtivo('index.php', $paginaAtual) ?>"
               data-titulo="Dashboard">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </span>
                <span class="nav-texto">Dashboard</span>
            </a>

            <a href="venda.php"
               class="nav-link<?= linkAtivo('venda.php', $paginaAtual) ?>"
               data-titulo="Venda à Vista">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </span>
                <span class="nav-texto">Venda à Vista</span>
            </a>

            <a href="venda_prazo.php"
               class="nav-link<?= linkAtivo('venda_prazo.php', $paginaAtual) ?>"
               data-titulo="Venda a Prazo">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="9" y1="15" x2="15" y2="15"/>
                    </svg>
                </span>
                <span class="nav-texto">Venda a Prazo</span>
                <?php if ($badges['pendentes'] > 0): ?>
                    <span class="nav-badge nav-badge-alerta"
                          title="<?= $badges['pendentes'] ?> fiado(s) pendente(s)">
                        <?= $badges['pendentes'] ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>

        <!-- ---------- Grupo: Cadastros ---------- -->
        <div class="nav-grupo">
            <span class="nav-grupo-label">Cadastros</span>

            <a href="cadastro_produto.php"
               class="nav-link<?= linkAtivo('cadastro_produto.php', $paginaAtual) ?>"
               data-titulo="Produtos">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </span>
                <span class="nav-texto">Produtos</span>
                <?php if ($badges['estoque_baixo'] > 0): ?>
                    <span class="nav-badge nav-badge-aviso"
                          title="<?= $badges['estoque_baixo'] ?> com estoque baixo">
                        <?= $badges['estoque_baixo'] ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="cadastro_cliente.php"
               class="nav-link<?= linkAtivo('cadastro_cliente.php', $paginaAtual) ?>"
               data-titulo="Clientes">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <span class="nav-texto">Clientes</span>
            </a>

            <a href="cadastro_usuario.php"
               class="nav-link<?= linkAtivo('cadastro_usuario.php', $paginaAtual) ?>"
               data-titulo="Usuários">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="19" y1="8" x2="19" y2="14"/>
                        <line x1="22" y1="11" x2="16" y2="11"/>
                    </svg>
                </span>
                <span class="nav-texto">Usuários</span>
            </a>
        </div>

        <!-- ---------- Grupo: Análise ---------- -->
        <div class="nav-grupo">
            <span class="nav-grupo-label">Análise</span>

            <a href="relatorio.php"
               class="nav-link<?= linkAtivo('relatorio.php', $paginaAtual) ?>"
               data-titulo="Relatórios">
                <span class="nav-icone">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </span>
                <span class="nav-texto">Relatórios</span>
            </a>
        </div>

    </nav>

    <!-- ========== RODAPÉ: USUÁRIO ========== -->
    <div class="sidebar-rodape">
        <div class="sidebar-user">
            <span class="user-avatar" title="<?= htmlspecialchars($usuarioNome) ?>">
                <?= htmlspecialchars($iniciais) ?>
            </span>
            <div class="user-info">
                <strong><?= htmlspecialchars($usuarioNome) ?></strong>
                <small><?= ucfirst(htmlspecialchars($usuarioNivel)) ?></small>
            </div>
            <a href="logout.php"
               class="user-sair"
               title="Sair do sistema"
               aria-label="Sair do sistema">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
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