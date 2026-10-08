<?php
/**
 * ============================================================
 * RELATÓRIO FINANCEIRO - Padaria do Gil
 * ============================================================
 */

// 1. Conexão primeiro
require_once 'config/database.php';

// 2. Filtros
$dataIni = $_GET['data_ini'] ?? date('Y-m-01');
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$tipo    = $_GET['tipo'] ?? 'todas';

// Valida datas (se inverterem)
if (strtotime($dataIni) > strtotime($dataFim)) {
    [$dataIni, $dataFim] = [$dataFim, $dataIni];
}

// 3. WHERE dinâmico
$where  = "DATE(v.data_venda) BETWEEN ? AND ?";
$params = [$dataIni, $dataFim];

if ($tipo !== 'todas') {
    $where .= " AND v.tipo = ?";
    $params[] = $tipo;
}

// 4. Header
require 'includes/header.php';

// ============================================================
// CONSULTAS
// ============================================================

// ---------- Resumo geral ----------
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(v.total), 0)                                            AS total,
        COUNT(v.id)                                                          AS qtd,
        COALESCE(SUM(CASE WHEN v.status = 'pendente' THEN v.total END), 0)  AS pendente,
        COALESCE(SUM(CASE WHEN v.tipo = 'avista'  THEN v.total END), 0)     AS avista,
        COALESCE(SUM(CASE WHEN v.tipo = 'prazo'   THEN v.total END), 0)     AS prazo
    FROM vendas v
    WHERE $where
");
$stmt->execute($params);
$resumo = $stmt->fetch();

// ---------- Lucro (baseado no custo dos produtos vendidos) ----------
$stmt = $pdo->prepare("
    SELECT
        COALESCE(SUM(vi.quantidade * p.preco_custo), 0) AS custo,
        COALESCE(SUM(vi.subtotal), 0)                    AS receita,
        COALESCE(SUM(vi.quantidade), 0)                  AS qtdItens
    FROM venda_itens vi
    JOIN produtos p ON p.id = vi.produto_id
    JOIN vendas v   ON v.id = vi.venda_id
    WHERE $where
");
$stmt->execute($params);
$lucro = $stmt->fetch();

$lucroLiquido = $lucro['receita'] - $lucro['custo'];
$margem = $lucro['receita'] > 0 ? ($lucroLiquido / $lucro['receita']) * 100 : 0;
$ticketMedio = $resumo['qtd'] > 0 ? $resumo['total'] / $resumo['qtd'] : 0;

// ---------- Vendas por dia ----------
$stmt = $pdo->prepare("
    SELECT DATE(v.data_venda) AS dia, SUM(v.total) AS total, COUNT(*) AS qtd
    FROM vendas v
    WHERE $where
    GROUP BY DATE(v.data_venda)
    ORDER BY dia DESC
    LIMIT 60
");
$stmt->execute($params);
$porDia = $stmt->fetchAll();

// ---------- Vendas por mês (últimos 12) ----------
$porMes = $pdo->query("
    SELECT DATE_FORMAT(data_venda, '%Y-%m') AS mes,
           SUM(total) AS total, COUNT(*) AS qtd
    FROM vendas
    GROUP BY mes
    ORDER BY mes DESC
    LIMIT 12
")->fetchAll();

// ---------- Top 10 produtos ----------
$stmt = $pdo->prepare("
    SELECT p.nome, p.categoria,
           SUM(vi.quantidade) AS qtd,
           SUM(vi.subtotal)   AS total
    FROM venda_itens vi
    JOIN produtos p ON p.id = vi.produto_id
    JOIN vendas v   ON v.id = vi.venda_id
    WHERE $where
    GROUP BY p.id
    ORDER BY qtd DESC
    LIMIT 10
");
$stmt->execute($params);
$topProdutos = $stmt->fetchAll();

// ---------- Vendas por vendedor ----------
$stmt = $pdo->prepare("
    SELECT u.nome,
           COUNT(v.id) AS qtd,
           SUM(v.total) AS total
    FROM vendas v
    JOIN usuarios u ON u.id = v.usuario_id
    WHERE $where
    GROUP BY u.id
    ORDER BY total DESC
");
$stmt->execute($params);
$porVendedor = $stmt->fetchAll();

// ---------- Forma de pagamento (à vista vs prazo) ----------
$formaDist = [
    'avista' => $resumo['avista'],
    'prazo'  => $resumo['prazo']
];
?>

<!-- ==================== CABEÇALHO ==================== -->
<div class="page-header">
    <div>
        <h1>📊 Relatório Financeiro</h1>
        <p class="subtitulo">
            Período:
            <strong><?= date('d/m/Y', strtotime($dataIni)) ?></strong>
            até
            <strong><?= date('d/m/Y', strtotime($dataFim)) ?></strong>
            ·
            <?= $tipo === 'todas' ? 'Todas as vendas' : ($tipo === 'avista' ? 'Somente à vista' : 'Somente a prazo') ?>
        </p>
    </div>
    <button type="button" class="btn-novo" onclick="window.print()">
        🖨️ Imprimir Relatório
    </button>
</div>

<!-- ==================== FILTRO ==================== -->
<form method="GET" class="form-filtro" id="formRelatorio">
    <label>
        De:
        <input type="date" name="data_ini" value="<?= $dataIni ?>">
    </label>
    <label>
        Até:
        <input type="date" name="data_fim" value="<?= $dataFim ?>">
    </label>
    <label>
        Tipo:
        <select name="tipo">
            <option value="todas"  <?= $tipo === 'todas'  ? 'selected' : '' ?>>Todas</option>
            <option value="avista" <?= $tipo === 'avista' ? 'selected' : '' ?>>À Vista</option>
            <option value="prazo"  <?= $tipo === 'prazo'  ? 'selected' : '' ?>>A Prazo</option>
        </select>
    </label>
    <button type="submit">🔍 Filtrar</button>
</form>

<!-- ==================== KPIs PRINCIPAIS ==================== -->
<div class="cards kpis-relatorio">
    <div class="card verde">
        <h3>💰 Faturamento</h3>
        <p>R$ <?= number_format($resumo['total'], 2, ',', '.') ?></p>
        <small class="card-sub"><?= (int)$resumo['qtd'] ?> vendas no período</small>
    </div>

    <div class="card azul">
        <h3>🎫 Ticket Médio</h3>
        <p>R$ <?= number_format($ticketMedio, 2, ',', '.') ?></p>
        <small class="card-sub">Média por venda</small>
    </div>

    <div class="card roxo">
        <h3>💵 Lucro Líquido</h3>
        <p>R$ <?= number_format($lucroLiquido, 2, ',', '.') ?></p>
        <small class="card-sub">
            Margem: <strong><?= number_format($margem, 1, ',', '.') ?>%</strong>
        </small>
    </div>

    <div class="card laranja">
        <h3>📦 Produtos Vendidos</h3>
        <p><?= number_format((int)$lucro['qtdItens'], 0, ',', '.') ?></p>
        <small class="card-sub">unidades no período</small>
    </div>

    <div class="card <?= $resumo['pendente'] > 0 ? 'vermelho' : 'cinza' ?>">
        <h3>⏳ A Receber (Prazo)</h3>
        <p>R$ <?= number_format($resumo['pendente'], 2, ',', '.') ?></p>
        <small class="card-sub">Fiados pendentes</small>
    </div>
</div>

<!-- ==================== DISTRIBUIÇÃO POR FORMA ==================== -->
<div class="cards kpis-secundarios">
    <div class="card azul">
        <h3>💵 À Vista</h3>
        <p>R$ <?= number_format($resumo['avista'], 2, ',', '.') ?></p>
        <small class="card-sub">
            <?= $resumo['total'] > 0 ? number_format(($resumo['avista'] / $resumo['total']) * 100, 1, ',', '.') : '0' ?>% do total
        </small>
    </div>
    <div class="card laranja">
        <h3>📝 A Prazo</h3>
        <p>R$ <?= number_format($resumo['prazo'], 2, ',', '.') ?></p>
        <small class="card-sub">
            <?= $resumo['total'] > 0 ? number_format(($resumo['prazo'] / $resumo['total']) * 100, 1, ',', '.') : '0' ?>% do total
        </small>
    </div>
    <div class="card marrom">
        <h3>📈 Custo dos Produtos</h3>
        <p>R$ <?= number_format($lucro['custo'], 2, ',', '.') ?></p>
        <small class="card-sub">Total investido</small>
    </div>
</div>

<!-- ==================== VENDAS POR DIA ==================== -->
<h2>📅 Vendas por Dia</h2>

<?php if (empty($porDia)): ?>
    <div class="aviso-vazio">
        <span>📅</span>
        <p>Nenhuma venda registrada no período selecionado.</p>
    </div>
<?php else: ?>
    <table id="tabela-vendas-dia" data-busca>
        <thead>
            <tr>
                <th>Data</th>
                <th>Qtd. Vendas</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($porDia as $d): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
                    <td><span class="badge-status badge-azul"><?= (int)$d['qtd'] ?></span></td>
                    <td><strong>R$ <?= number_format($d['total'], 2, ',', '.') ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- ==================== VENDAS POR MÊS ==================== -->
<h2>📈 Vendas por Mês (últimos 12)</h2>

<?php if (empty($porMes)): ?>
    <div class="aviso-vazio">
        <span>📈</span>
        <p>Sem dados mensais.</p>
    </div>
<?php else: ?>
    <table id="tabela-vendas-mes" data-busca>
        <thead>
            <tr>
                <th>Mês</th>
                <th>Qtd. Vendas</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
            foreach ($porMes as $m):
                [$ano, $num] = explode('-', $m['mes']);
                $label = $meses[(int)$num - 1] . '/' . $ano;
            ?>
                <tr>
                    <td><strong><?= $label ?></strong></td>
                    <td><span class="badge-status badge-azul"><?= (int)$m['qtd'] ?></span></td>
                    <td><strong>R$ <?= number_format($m['total'], 2, ',', '.') ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- ==================== TOP PRODUTOS ==================== -->
<h2>🏆 Top 10 Produtos Mais Vendidos</h2>

<?php if (empty($topProdutos)): ?>
    <div class="aviso-vazio">
        <span>🏆</span>
        <p>Nenhum produto vendido no período.</p>
    </div>
<?php else: ?>
    <table id="tabela-top-produtos" data-busca>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Produto</th>
                <th>Categoria</th>
                <th>Qtd. Vendida</th>
                <th>Faturamento</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topProdutos as $i => $p):
                $medalha = ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) . 'º';
                $classeRank = $i < 3 ? 'rank-destaque rank-' . ($i + 1) : '';
            ?>
                <tr class="<?= $classeRank ?>">
                    <td class="rank-celula"><?= $medalha ?></td>
                    <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
                    <td>
                        <span class="produto-badge <?= $p['categoria'] === 'estoque' ? 'badge-azul' : 'badge-laranja' ?>">
                            <?= $p['categoria'] === 'estoque' ? '📦 Estoque' : '🥐 Produção' ?>
                        </span>
                    </td>
                    <td><span class="badge-status badge-sucesso"><?= (int)$p['qtd'] ?> un.</span></td>
                    <td><strong class="texto-marrom">R$ <?= number_format($p['total'], 2, ',', '.') ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- ==================== VENDAS POR VENDEDOR ==================== -->
<?php if (!empty($porVendedor)): ?>
    <h2>👤 Desempenho por Vendedor</h2>
    <table id="tabela-vendedor" data-busca>
        <thead>
            <tr>
                <th>Vendedor</th>
                <th>Qtd. Vendas</th>
                <th>Total Vendido</th>
                <th>Ticket Médio</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($porVendedor as $v):
                $ticketVend = $v['qtd'] > 0 ? $v['total'] / $v['qtd'] : 0;
            ?>
                <tr>
                    <td><strong><?= htmlspecialchars($v['nome']) ?></strong></td>
                    <td><span class="badge-status badge-azul"><?= (int)$v['qtd'] ?></span></td>
                    <td><strong>R$ <?= number_format($v['total'], 2, ',', '.') ?></strong></td>
                    <td>R$ <?= number_format($ticketVend, 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- ==================== RODAPÉ DE IMPRESSÃO ==================== -->
<div class="relatorio-rodape">
    <p>
        Relatório gerado em <strong><?= date('d/m/Y \à\s H:i') ?></strong>
        por <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Sistema') ?></strong>
    </p>
    <p class="rodape-assinatura">🥖 Padaria do Gil — Sistema de Gestão</p>
</div>

<?php
// Carrega os gráficos (Chart.js) - se existir o arquivo
$scriptsExtras = ['assets/js/relatorios.js'];
require 'includes/footer.php';
?>