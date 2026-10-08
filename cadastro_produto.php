<?php
/**
 * ============================================================
 * CADASTRO DE PRODUTOS - Padaria do Gil
 * ============================================================
 */

// 1. Conexão primeiro
require_once 'config/database.php';

// 2. Processa POST ANTES do header
$msg     = '';
$msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'criar';

    // ---------- EXCLUIR ----------
    if ($acao === 'excluir') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);
            $msg = "Produto excluído com sucesso.";
            $msgTipo = 'sucesso';
        } catch (PDOException $e) {
            $msg = "Não foi possível excluir. O produto pode estar em vendas.";
            $msgTipo = 'erro';
        }
    }
    // ---------- EDITAR ----------
    elseif ($acao === 'editar') {
        $id      = (int)$_POST['id'];
        $nome    = trim($_POST['nome']);
        $desc    = trim($_POST['descricao']);
        $cat     = $_POST['categoria'];
        $custo   = (float)$_POST['preco_custo'];
        $venda   = (float)$_POST['preco_venda'];
        $estoque = (int)$_POST['estoque'];

        if ($nome === '') {
            $msg = "O nome é obrigatório.";
            $msgTipo = 'erro';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE produtos
                    SET nome = ?, descricao = ?, categoria = ?,
                        preco_custo = ?, preco_venda = ?, estoque = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $desc, $cat, $custo, $venda, $estoque, $id]);
                $msg = "Produto atualizado com sucesso!";
                $msgTipo = 'sucesso';
            } catch (PDOException $e) {
                $msg = "Erro ao atualizar: " . $e->getMessage();
                $msgTipo = 'erro';
            }
        }
    }
    // ---------- CRIAR ----------
    else {
        $nome    = trim($_POST['nome']);
        $desc    = trim($_POST['descricao']);
        $cat     = $_POST['categoria'];
        $custo   = (float)$_POST['preco_custo'];
        $venda   = (float)$_POST['preco_venda'];
        $estoque = (int)$_POST['estoque'];

        if ($nome === '') {
            $msg = "O nome é obrigatório.";
            $msgTipo = 'erro';
        } elseif ($venda <= 0) {
            $msg = "O preço de venda deve ser maior que zero.";
            $msgTipo = 'erro';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO produtos (nome, descricao, categoria, preco_custo, preco_venda, estoque)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $desc, $cat, $custo, $venda, $estoque]);
                $msg = "Produto <strong>" . htmlspecialchars($nome) . "</strong> cadastrado com sucesso!";
                $msgTipo = 'sucesso';
            } catch (PDOException $e) {
                $msg = "Erro ao cadastrar: " . $e->getMessage();
                $msgTipo = 'erro';
            }
        }
    }
}

// 3. Header
require 'includes/header.php';

// 4. KPIs
$kpiTotal     = $pdo->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$kpiEstoque   = $pdo->query("SELECT COUNT(*) FROM produtos WHERE categoria = 'estoque'")->fetchColumn();
$kpiProducao  = $pdo->query("SELECT COUNT(*) FROM produtos WHERE categoria = 'producao'")->fetchColumn();
$kpiValor     = $pdo->query("SELECT COALESCE(SUM(preco_custo * estoque),0) FROM produtos")->fetchColumn();
$kpiBaixo     = $pdo->query("SELECT COUNT(*) FROM produtos WHERE categoria='estoque' AND estoque <= 5")->fetchColumn();

// 5. Lista de produtos
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY categoria DESC, nome ASC")->fetchAll();
?>

<!-- ==================== CABEÇALHO ==================== -->
<div class="page-header">
    <div>
        <h1>📦 Cadastro de Produtos</h1>
        <p class="subtitulo">Gerencie os produtos de estoque e produção da padaria</p>
    </div>
    <button type="button" class="btn-novo" onclick="abrirModal()">
        ➕ Novo Produto
    </button>
</div>

<?php if ($msg): ?>
    <p class="msg <?= $msgTipo === 'erro' ? 'msg-erro' : 'msg-ok' ?>"><?= $msg ?></p>
<?php endif; ?>

<!-- ==================== KPIs ==================== -->
<div class="cards kpis-produtos">
    <div class="card marrom">
        <h3>Total de Produtos</h3>
        <p><?= (int)$kpiTotal ?></p>
    </div>
    <div class="card azul">
        <h3>📦 Estoque</h3>
        <p><?= (int)$kpiEstoque ?></p>
    </div>
    <div class="card laranja">
        <h3>🥐 Produção</h3>
        <p><?= (int)$kpiProducao ?></p>
    </div>
    <div class="card verde">
        <h3>💰 Valor em Estoque</h3>
        <p>R$ <?= number_format($kpiValor, 2, ',', '.') ?></p>
    </div>
    <div class="card <?= $kpiBaixo > 0 ? 'vermelho' : 'roxo' ?>">
        <h3>⚠️ Estoque Baixo</h3>
        <p><?= (int)$kpiBaixo ?></p>
    </div>
</div>

<!-- ==================== FILTROS ==================== -->
<div class="filtros-produtos">
    <input type="search" id="buscaProduto"
           placeholder="🔍 Buscar produto por nome ou descrição..."
           class="input-busca">

    <div class="filtro-tabs">
        <button type="button" class="tab-filtro ativo" data-filtro="todos">Todos</button>
        <button type="button" class="tab-filtro" data-filtro="estoque">📦 Estoque</button>
        <button type="button" class="tab-filtro" data-filtro="producao">🥐 Produção</button>
        <button type="button" class="tab-filtro" data-filtro="baixo">⚠️ Baixo</button>
    </div>
</div>

<!-- ==================== LISTA ==================== -->
<?php if (empty($produtos)): ?>
    <div class="aviso-vazio">
        <span>📦</span>
        <p>Nenhum produto cadastrado ainda.</p>
        <button type="button" class="btn-add" onclick="abrirModal()">➕ Cadastrar primeiro produto</button>
    </div>
<?php else: ?>

<!-- Grid de cards de produtos -->
<div class="produtos-grid" id="produtosGrid">
    <?php foreach ($produtos as $p):
        $margem = $p['preco_venda'] > 0
            ? (($p['preco_venda'] - $p['preco_custo']) / $p['preco_venda']) * 100
            : 0;
        $lucroUnidade = $p['preco_venda'] - $p['preco_custo'];

        $estoqueBaixo = $p['categoria'] === 'estoque' && $p['estoque'] <= 5;
        $semEstoque   = $p['categoria'] === 'estoque' && $p['estoque'] <= 0;
    ?>
        <div class="produto-item"
             data-nome="<?= htmlspecialchars(strtolower($p['nome'] . ' ' . ($p['descricao'] ?? ''))) ?>"
             data-categoria="<?= $p['categoria'] ?>"
             data-baixo="<?= $estoqueBaixo ? '1' : '0' ?>">

            <div class="produto-item-topo">
                <span class="produto-badge <?= $p['categoria'] === 'estoque' ? 'badge-azul' : 'badge-laranja' ?>">
                    <?= $p['categoria'] === 'estoque' ? '📦 Estoque' : '🥐 Produção' ?>
                </span>
                <?php if ($semEstoque): ?>
                    <span class="badge-status badge-erro">Sem estoque</span>
                <?php elseif ($estoqueBaixo): ?>
                    <span class="badge-status badge-aviso">Estoque baixo</span>
                <?php else: ?>
                    <span class="badge-status badge-sucesso">Ativo</span>
                <?php endif; ?>
            </div>

            <h3 class="produto-titulo"><?= htmlspecialchars($p['nome']) ?></h3>

            <?php if (!empty($p['descricao'])): ?>
                <p class="produto-desc"><?= htmlspecialchars($p['descricao']) ?></p>
            <?php endif; ?>

            <div class="produto-valores">
                <div>
                    <small>Custo</small>
                    <strong>R$ <?= number_format($p['preco_custo'], 2, ',', '.') ?></strong>
                </div>
                <div>
                    <small>Venda</small>
                    <strong class="texto-marrom">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></strong>
                </div>
                <div>
                    <small>Lucro / un.</small>
                    <strong class="<?= $lucroUnidade >= 0 ? 'texto-sucesso' : 'texto-erro' ?>">
                        R$ <?= number_format($lucroUnidade, 2, ',', '.') ?>
                    </strong>
                </div>
            </div>

            <div class="produto-rodape">
                <div class="produto-info">
                    <span class="info-item">
                        <small>Estoque</small>
                        <strong class="<?= $estoqueBaixo ? 'texto-erro' : '' ?>">
                            <?= $p['categoria'] === 'estoque' ? (int)$p['estoque'] . ' un.' : '—' ?>
                        </strong>
                    </span>
                    <span class="info-item">
                        <small>Margem</small>
                        <strong class="<?= $margem >= 30 ? 'texto-sucesso' : ($margem >= 10 ? '' : 'texto-erro') ?>">
                            <?= number_format($margem, 1, ',', '.') ?>%
                        </strong>
                    </span>
                </div>

                <div class="produto-acoes">
                    <button type="button" class="btn-icon"
                            title="Editar"
                            onclick='editarProduto(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                        ✏️
                    </button>
                    <form method="POST" style="display: inline;"
                          onsubmit="return confirmarExclusao(event, '<?= htmlspecialchars(addslashes($p['nome'])) ?>')">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn-icon btn-icon-perigo" title="Excluir">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Mensagem de "nenhum resultado" na busca -->
<div id="buscaVazia" class="aviso-vazio" style="display: none;">
    <span>🔍</span>
    <p>Nenhum produto encontrado com esse filtro.</p>
</div>

<?php endif; ?>

<!-- ==================== MODAL: Novo/Editar Produto ==================== -->
<div class="modal-overlay" id="modalProduto" style="display: none;">
    <div class="modal-box modal-grande">
        <div class="modal-header">
            <h3 class="modal-titulo" id="modalTitulo">➕ Novo Produto</h3>
            <button type="button" class="modal-fechar" onclick="fecharModal()">✕</button>
        </div>

        <form method="POST" id="formProduto" autocomplete="off">
            <input type="hidden" name="acao" value="criar" id="formAcao">
            <input type="hidden" name="id" value="" id="formId">

            <div class="form-modal-grid">
                <div class="campo campo-full">
                    <label for="nome">Nome do produto *</label>
                    <input type="text" name="nome" id="nome"
                           placeholder="Ex: Pão francês, Refrigerante..." required maxlength="100">
                </div>

                <div class="campo campo-full">
                    <label for="descricao">Descrição (opcional)</label>
                    <input type="text" name="descricao" id="descricao"
                           placeholder="Detalhes adicionais..." maxlength="255">
                </div>

                <div class="campo">
                    <label for="categoria">Categoria *</label>
                    <select name="categoria" id="categoria" required>
                        <option value="estoque">📦 Estoque (revenda)</option>
                        <option value="producao">🥐 Produção (fabricado)</option>
                    </select>
                </div>

                <div class="campo">
                    <label for="estoque">Quantidade em estoque</label>
                    <input type="number" name="estoque" id="estoque"
                           placeholder="0" min="0" value="0">
                </div>

                <div class="campo">
                    <label for="preco_custo">Preço de custo (R$)</label>
                    <input type="number" step="0.01" min="0" name="preco_custo" id="preco_custo"
                           placeholder="0,00" value="0">
                </div>

                <div class="campo">
                    <label for="preco_venda">Preço de venda (R$) *</label>
                    <input type="number" step="0.01" min="0.01" name="preco_venda" id="preco_venda"
                           placeholder="0,00" required>
                </div>
            </div>

            <!-- Preview de margem -->
            <div class="margem-preview" id="margemPreview">
                <div>
                    <small>Lucro por unidade</small>
                    <strong id="previewLucro">R$ 0,00</strong>
                </div>
                <div>
                    <small>Margem de lucro</small>
                    <strong id="previewMargem">0%</strong>
                </div>
                <div class="barra-margem" id="previewBarra">
                    <div class="barra-margem-fill" style="width: 0%"></div>
                </div>
            </div>

            <div class="modal-botoes">
                <button type="button" class="btn-cancelar-modal" onclick="fecharModal()">Cancelar</button>
                <button type="submit" class="btn-salvar-modal">💾 Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================
// MODAL
// ============================================================
function abrirModal() {
    document.getElementById('modalTitulo').textContent = '➕ Novo Produto';
    document.getElementById('formAcao').value = 'criar';
    document.getElementById('formId').value = '';
    document.getElementById('formProduto').reset();
    document.getElementById('estoque').value = '0';
    document.getElementById('preco_custo').value = '0';
    atualizarPreview();
    document.getElementById('modalProduto').style.display = 'flex';
    setTimeout(() => document.getElementById('modalProduto').classList.add('modal-visivel'), 10);
    setTimeout(() => document.getElementById('nome').focus(), 200);
}

function fecharModal() {
    const modal = document.getElementById('modalProduto');
    modal.classList.remove('modal-visivel');
    setTimeout(() => modal.style.display = 'none', 250);
}

function editarProduto(p) {
    document.getElementById('modalTitulo').textContent = '✏️ Editar Produto';
    document.getElementById('formAcao').value = 'editar';
    document.getElementById('formId').value = p.id;
    document.getElementById('nome').value = p.nome;
    document.getElementById('descricao').value = p.descricao || '';
    document.getElementById('categoria').value = p.categoria;
    document.getElementById('estoque').value = p.estoque;
    document.getElementById('preco_custo').value = p.preco_custo;
    document.getElementById('preco_venda').value = p.preco_venda;
    atualizarPreview();
    document.getElementById('modalProduto').style.display = 'flex';
    setTimeout(() => document.getElementById('modalProduto').classList.add('modal-visivel'), 10);
}

// Fecha modal clicando fora
document.getElementById('modalProduto')?.addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
});

// Fecha com ESC
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalProduto');
        if (modal && modal.classList.contains('modal-visivel')) fecharModal();
    }
});

// ============================================================
// PREVIEW DE MARGEM
// ============================================================
function atualizarPreview() {
    const custo = parseFloat(document.getElementById('preco_custo').value) || 0;
    const venda = parseFloat(document.getElementById('preco_venda').value) || 0;

    const lucro = venda - custo;
    const margem = venda > 0 ? (lucro / venda) * 100 : 0;

    const elLucro = document.getElementById('previewLucro');
    const elMargem = document.getElementById('previewMargem');
    const barra = document.getElementById('previewBarra');
    const fill = barra.querySelector('.barra-margem-fill');

    elLucro.textContent = formatarMoeda(lucro);
    elLucro.className = lucro >= 0 ? 'texto-sucesso' : 'texto-erro';

    elMargem.textContent = margem.toFixed(1).replace('.', ',') + '%';
    elMargem.className = margem >= 30 ? 'texto-sucesso' : (margem >= 10 ? '' : 'texto-erro');

    const usoPct = Math.min(100, Math.max(0, margem));
    fill.style.width = usoPct + '%';

    barra.classList.remove('barra-ok', 'barra-medio', 'barra-alerta', 'barra-cheia');
    if (margem < 10)      barra.classList.add('barra-cheia');
    else if (margem < 30) barra.classList.add('barra-alerta');
    else if (margem < 50) barra.classList.add('barra-medio');
    else                  barra.classList.add('barra-ok');
}

document.getElementById('preco_custo')?.addEventListener('input', atualizarPreview);
document.getElementById('preco_venda')?.addEventListener('input', atualizarPreview);

// ============================================================
// BUSCA E FILTROS
// ============================================================
let filtroAtual = 'todos';

document.getElementById('buscaProduto')?.addEventListener('input', aplicarFiltros);

document.querySelectorAll('.tab-filtro').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.tab-filtro').forEach(t => t.classList.remove('ativo'));
        this.classList.add('ativo');
        filtroAtual = this.dataset.filtro;
        aplicarFiltros();
    });
});

function aplicarFiltros() {
    const termo = (document.getElementById('buscaProduto')?.value || '').toLowerCase().trim();
    const itens = document.querySelectorAll('.produto-item');
    let visiveis = 0;

    itens.forEach(item => {
        const nome = item.dataset.nome;
        const cat = item.dataset.categoria;
        const baixo = item.dataset.baixo === '1';

        let matchBusca = !termo || nome.includes(termo);
        let matchFiltro = true;

        if (filtroAtual === 'estoque')  matchFiltro = cat === 'estoque';
        if (filtroAtual === 'producao') matchFiltro = cat === 'producao';
        if (filtroAtual === 'baixo')    matchFiltro = baixo;

        const mostrar = matchBusca && matchFiltro;
        item.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    const aviso = document.getElementById('buscaVazia');
    if (aviso) aviso.style.display = visiveis === 0 ? 'flex' : 'none';
}

// ============================================================
// CONFIRMAÇÃO DE EXCLUSÃO
// ============================================================
function confirmarExclusao(e, nome) {
    e.preventDefault();
    const form = e.target;

    if (typeof confirmar === 'function') {
        confirmar({
            titulo: '🗑️ Excluir produto',
            mensagem: `Tem certeza que deseja excluir "<strong>${nome}</strong>"? Esta ação não pode ser desfeita.`,
            textoBotao: 'Sim, excluir',
            tipoBotao: 'perigo'
        }).then(ok => {
            if (ok) form.submit();
        });
    } else {
        if (window.confirm(`Excluir "${nome}"?`)) form.submit();
    }

    return false;
}

// ============================================================
// UTILITÁRIOS
// ============================================================
function formatarMoeda(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Atalho Ctrl+K → busca
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('buscaProduto')?.focus();
    }
});
</script>

<?php require 'includes/footer.php'; ?>