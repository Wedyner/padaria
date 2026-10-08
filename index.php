<?php
/**
 * ============================================================
 * DASHBOARD - Padaria do Gil
 * Com imagem de fundo (assets/img/imagem03.jpg)
 * ============================================================
 */

// 1. Conexão primeiro
require_once 'config/database.php';

// 2. Header
require 'includes/header.php';

// ============================================================
// CONSULTAS
// ============================================================

// ---------- KPIs principais ----------
$totalHoje = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE DATE(data_venda) = CURDATE()
")->fetchColumn();

$totalMes = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE MONTH(data_venda) = MONTH(CURDATE())
      AND YEAR(data_venda) = YEAR(CURDATE())
")->fetchColumn();

$pendentes = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE status = 'pendente'
")->fetchColumn();

$qtdProd = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();

// ---------- Comparação com ontem ----------
$totalOntem = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE DATE(data_venda) = CURDATE() - INTERVAL 1 DAY
")->fetchColumn();

$variacao = $totalOntem > 0
    ? (($totalHoje - $totalOntem) / $totalOntem) * 100
    : ($totalHoje > 0 ? 100 : 0);

// ---------- Vendas dos últimos 7 dias ----------
$vendas7 = $pdo->query("
    SELECT DATE(data_venda) AS dia, SUM(total) AS total, COUNT(*) AS qtd
    FROM vendas
    WHERE data_venda >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(data_venda)
    ORDER BY dia ASC
")->fetchAll();

// ---------- Top 5 produtos do mês ----------
$topProdutos = $pdo->query("
    SELECT p.nome, SUM(vi.quantidade) AS qtd, SUM(vi.subtotal) AS total
    FROM venda_itens vi
    JOIN produtos p ON p.id = vi.produto_id
    JOIN vendas v   ON v.id = vi.venda_id
    WHERE MONTH(v.data_venda) = MONTH(CURDATE())
      AND YEAR(v.data_venda)  = YEAR(CURDATE())
    GROUP BY p.id
    ORDER BY qtd DESC
    LIMIT 5
")->fetchAll();

// ---------- Últimas 5 vendas ----------
$ultimasVendas = $pdo->query("
    SELECT v.id, v.total, v.tipo, v.status, v.data_venda,
           COALESCE(c.nome, 'Consumidor') AS cliente,
           u.nome AS vendedor
    FROM vendas v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    LEFT JOIN usuarios u ON u.id = v.usuario_id
    ORDER BY v.id DESC
    LIMIT 5
")->fetchAll();

// ---------- Alertas ----------
$estoqueBaixo = $pdo->query("
    SELECT COUNT(*) FROM produtos
    WHERE categoria = 'estoque' AND estoque <= 5
")->fetchColumn();

$clientesDevendo = $pdo->query("
    SELECT COUNT(*) FROM clientes WHERE saldo_devedor > 0
")->fetchColumn();

// ---------- Saudação ----------
$hora = (int)date('H');
if     ($hora < 12) $saudacao = "Bom dia";
elseif ($hora < 18) $saudacao = "Boa tarde";
else                $saudacao = "Boa noite";

$nomeUsuario  = $_SESSION['usuario_nome'] ?? 'Administrador';
$primeiroNome = explode(' ', trim($nomeUsuario))[0];

// ---------- Data em português ----------
$diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
$meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$dataBonita = $diasSemana[(int)date('w')] . ', ' . (int)date('d') . ' de ' . $meses[(int)date('n') - 1];
?>

<!-- ==================== IMAGEM DE FUNDO GLOBAL ==================== -->
<div class="bg-padaria" aria-hidden="true"></div>

<!-- ==================== HERO ==================== -->
<section class="dashboard-hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-texto">
            <span class="hero-saudacao">🥖 <?= $saudacao ?>, <strong><?= htmlspecialchars($primeiroNome) ?></strong>!</span>
            <h1 class="hero-titulo">Painel da Padaria do Gil</h1>
            <p class="hero-sub">
                Hoje é <?= $dataBonita ?> · Aqui está o resumo do seu negócio
            </p>
        </div>

        <div class="hero-acoes">
            <a href="venda.php" class="btn-hero btn-hero-primario">🛒 Nova Venda</a>
            <a href="venda_prazo.php" class="btn-hero btn-hero-secundario">📝 Novo Fiado</a>
            <a href="relatorio.php" class="btn-hero btn-hero-secundario">📊 Relatórios</a>
        </div>
    </div>
</section>

<!-- ==================== KPIs ==================== -->
<div class="cards kpis-dashboard">
    <div class="card verde card-animado">
        <div class="card-icone">💰</div>
        <h3>Vendas Hoje</h3>
        <p class="card-valor" data-valor="<?= $totalHoje ?>">
            R$ <?= number_format($totalHoje, 2, ',', '.') ?>
        </p>
        <small class="card-trend <?= $variacao >= 0 ? 'trend-up' : 'trend-down' ?>">
            <?= $variacao >= 0 ? '▲' : '▼' ?>
            <?= number_format(abs($variacao), 1, ',', '.') ?>% vs ontem
        </small>
    </div>

    <div class="card azul card-animado">
        <div class="card-icone">📅</div>
        <h3>Vendas no Mês</h3>
        <p class="card-valor" data-valor="<?= $totalMes ?>">
            R$ <?= number_format($totalMes, 2, ',', '.') ?>
        </p>
        <small class="card-trend">Mês atual</small>
    </div>

    <div class="card <?= $pendentes > 0 ? 'vermelho' : 'cinza' ?> card-animado">
        <div class="card-icone">⏳</div>
        <h3>A Receber (Prazo)</h3>
        <p class="card-valor" data-valor="<?= $pendentes ?>">
            R$ <?= number_format($pendentes, 2, ',', '.') ?>
        </p>
        <small class="card-trend"><?= (int)$clientesDevendo ?> cliente(s) devendo</small>
    </div>

    <div class="card laranja card-animado">
        <div class="card-icone">📦</div>
        <h3>Produtos Cadastrados</h3>
        <p class="card-valor" data-valor="<?= $qtdProd ?>"><?= (int)$qtdProd ?></p>
        <small class="card-trend">
            <?php if ($estoqueBaixo > 0): ?>
                <span class="texto-erro">⚠️ <?= (int)$estoqueBaixo ?> com estoque baixo</span>
            <?php else: ?>
                Estoque em dia
            <?php endif; ?>
        </small>
    </div>
</div>

<!-- ==================== ALERTAS ==================== -->
<?php if ($estoqueBaixo > 0 || $clientesDevendo > 0): ?>
    <div class="alertas-dashboard">
        <?php if ($estoqueBaixo > 0): ?>
            <div class="alerta alerta-aviso">
                <span class="alerta-icone">⚠️</span>
                <div>
                    <strong>Atenção ao estoque</strong>
                    <p><?= (int)$estoqueBaixo ?> produto(s) com estoque baixo (≤ 5 unidades)</p>
                </div>
                <a href="cadastro_produto.php" class="alerta-acao">Ver produtos</a>
            </div>
        <?php endif; ?>

        <?php if ($clientesDevendo > 0): ?>
            <div class="alerta alerta-info">
                <span class="alerta-icone">💰</span>
                <div>
                    <strong>Clientes devendo</strong>
                    <p><?= (int)$clientesDevendo ?> cliente(s) com saldo pendente</p>
                </div>
                <a href="cadastro_cliente.php" class="alerta-acao">Ver clientes</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ==================== GRÁFICO + TOP PRODUTOS ==================== -->
<div class="dashboard-grid-2">

    <div class="painel-card painel-glass">
        <div class="painel-header">
            <h3>📈 Vendas nos últimos 7 dias</h3>
            <span class="painel-badge">Semana</span>
        </div>
        <div class="painel-canvas">
            <canvas id="graficoDashboard"></canvas>
        </div>
    </div>

    <div class="painel-card painel-glass">
        <div class="painel-header">
            <h3>🏆 Top 5 do mês</h3>
            <span class="painel-badge">Produtos</span>
        </div>

        <?php if (empty($topProdutos)): ?>
            <div class="painel-vazio">
                <span>📦</span>
                <p>Nenhuma venda este mês</p>
            </div>
        <?php else: ?>
            <ul class="top-produtos-lista">
                <?php foreach ($topProdutos as $i => $p):
                    $medalha = ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) . 'º';
                ?>
                    <li>
                        <span class="top-medalha"><?= $medalha ?></span>
                        <div class="top-info">
                            <strong><?= htmlspecialchars($p['nome']) ?></strong>
                            <small><?= (int)$p['qtd'] ?> un. vendidas</small>
                        </div>
                        <span class="top-valor">
                            R$ <?= number_format($p['total'], 2, ',', '.') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== ÚLTIMAS VENDAS ==================== -->
<div class="painel-card painel-full painel-glass">
    <div class="painel-header">
        <h3>🕐 Últimas vendas</h3>
        <a href="relatorio.php" class="painel-link">Ver todas →</a>
    </div>

    <?php if (empty($ultimasVendas)): ?>
        <div class="painel-vazio">
            <span>🛒</span>
            <p>Nenhuma venda registrada ainda</p>
            <a href="venda.php" class="btn-add">➕ Fazer primeira venda</a>
        </div>
    <?php else: ?>
        <table class="tabela-ultimas">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Hora</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ultimasVendas as $v): ?>
                    <tr>
                        <td><strong>#<?= (int)$v['id'] ?></strong></td>
                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                        <td><?= htmlspecialchars($v['vendedor'] ?? '—') ?></td>
                        <td>
                            <span class="badge-status <?= $v['tipo'] === 'prazo' ? 'badge-aviso' : 'badge-azul' ?>">
                                <?= $v['tipo'] === 'prazo' ? '📝 Prazo' : '💵 Vista' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge-status <?= $v['status'] === 'pendente' ? 'badge-erro' : 'badge-sucesso' ?>">
                                <?= $v['status'] === 'pendente' ? 'Pendente' : 'Pago' ?>
                            </span>
                        </td>
                        <td><?= date('H:i', strtotime($v['data_venda'])) ?></td>
                        <td><strong class="texto-marrom">R$ <?= number_format($v['total'], 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- ==================== DADOS PARA O GRÁFICO ==================== -->
<script>
window.dashboardData = {
    labels: <?= json_encode(array_map(
        fn($d) => date('d/m', strtotime($d['dia'])),
        $vendas7
    )) ?>,
    valores: <?= json_encode(array_map(fn($d) => (float)$d['total'], $vendas7)) ?>,
    quantidades: <?= json_encode(array_map(fn($d) => (int)$d['qtd'], $vendas7)) ?>
};
</script>

<script>
// ============================================================
// GRÁFICO DO DASHBOARD (Chart.js)
// ============================================================
(function () {
    const dados = window.dashboardData;
    if (!dados || !dados.labels.length) return;

    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
    s.onload = () => desenhar();
    document.head.appendChild(s);

    function desenhar() {
        const canvas = document.getElementById('graficoDashboard');
        if (!canvas) return;

        const escuro   = document.documentElement.getAttribute('data-tema') === 'escuro';
        const corTexto = escuro ? '#e8e8e8' : '#2c2c2c';
        const corGrid  = escuro ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';

        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(139,94,52,.45)');
        gradient.addColorStop(1, 'rgba(139,94,52,.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dados.labels,
                datasets: [{
                    label: 'Vendas (R$)',
                    data: dados.valores,
                    borderColor: '#8b5e34',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#8b5e34',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 9
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,.85)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: (ctx) => {
                                const i = ctx.dataIndex;
                                const valor = Number(ctx.parsed.y).toLocaleString('pt-BR', {
                                    minimumFractionDigits: 2
                                });
                                return ` R$ ${valor} · ${dados.quantidades[i]} vendas`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: corTexto, font: { size: 12 } },
                        grid:  { display: false }
                    },
                    y: {
                        ticks: {
                            color: corTexto,
                            callback: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR')
                        },
                        grid: { color: corGrid, drawBorder: false },
                        beginAtZero: true
                    }
                }
            }
        });
    }
})();
</script>

<?php require 'includes/footer.php'; ?>