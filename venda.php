<?php
/**
 * ============================================================
 * VENDA À VISTA - Padaria do Gil
 * ============================================================
 */

// 1. Conexão primeiro (antes do header)
require_once 'config/database.php';

// 2. Processa a venda ANTES de incluir o header (evita headers already sent)
$msg     = '';
$msgTipo = '';
$vendaId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['itens'])) {
    try {
        $pdo->beginTransaction();

        $total = 0;
        $itensValidos = [];

        foreach ($_POST['itens'] as $item) {
            $pid = (int)($item['produto_id'] ?? 0);
            $qtd = (int)($item['quantidade'] ?? 0);
            if ($pid <= 0 || $qtd <= 0) continue;

            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$pid]);
            $prod = $stmt->fetch();
            if (!$prod) continue;

            // Valida estoque para produtos de estoque
            if ($prod['categoria'] === 'estoque' && $prod['estoque'] < $qtd) {
                throw new Exception("Estoque insuficiente para '{$prod['nome']}'. Disponível: {$prod['estoque']}.");
            }

            $subtotal = $prod['preco_venda'] * $qtd;
            $total += $subtotal;
            $itensValidos[] = [$pid, $qtd, $prod['preco_venda'], $subtotal, $prod['categoria']];
        }

        if ($total > 0) {
            // Cria a venda
            $stmt = $pdo->prepare("INSERT INTO vendas (usuario_id, tipo, total, status) VALUES (?, 'avista', ?, 'pago')");
            $stmt->execute([$_SESSION['usuario_id'] ?? 1, $total]);
            $vendaId = $pdo->lastInsertId();

            $insItem = $pdo->prepare("INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?)");
            $updEstq = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND categoria = 'estoque'");

            foreach ($itensValidos as $it) {
                $insItem->execute([$vendaId, $it[0], $it[1], $it[2], $it[3]]);
                if ($it[4] === 'estoque') {
                    $updEstq->execute([$it[1], $it[0]]);
                }
            }

            $pdo->commit();
            $msg = "Venda <strong>#$vendaId</strong> registrada com sucesso! Total: <strong>R$ " . number_format($total, 2, ',', '.') . "</strong>";
            $msgTipo = 'sucesso';
        } else {
            $pdo->rollBack();
            $msg = "Adicione ao menos 1 item válido.";
            $msgTipo = 'aviso';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e->getMessage();
        $msgTipo = 'erro';
    }
}

// 3. Inclui header
require 'includes/header.php';

// 4. Busca produtos disponíveis
$produtos = $pdo->query("
    SELECT * FROM produtos
    WHERE (categoria = 'estoque' AND estoque > 0)
       OR categoria = 'producao'
    ORDER BY categoria DESC, nome ASC
")->fetchAll();

// 5. Últimas vendas do dia (mini painel lateral)
$ultimasVendas = $pdo->query("
    SELECT v.id, v.total, v.data_venda, u.nome AS vendedor
    FROM vendas v
    LEFT JOIN usuarios u ON u.id = v.usuario_id
    WHERE DATE(v.data_venda) = CURDATE() AND v.tipo = 'avista'
    ORDER BY v.id DESC
    LIMIT 5
")->fetchAll();

$totalHoje = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE DATE(data_venda) = CURDATE() AND tipo = 'avista'
")->fetchColumn();

$qtdHoje = $pdo->query("
    SELECT COUNT(*) FROM vendas
    WHERE DATE(data_venda) = CURDATE() AND tipo = 'avista'
")->fetchColumn();
?>

<div class="venda-header">
    <div>
        <h1>🛒 Venda à Vista</h1>
        <p class="subtitulo">Registre vendas de produtos de estoque e produção</p>
    </div>
    <div class="venda-stats">
        <div class="stat-mini">
            <span class="stat-label">Hoje</span>
            <span class="stat-valor">R$ <?= number_format($totalHoje, 2, ',', '.') ?></span>
        </div>
        <div class="stat-mini">
            <span class="stat-label">Vendas</span>
            <span class="stat-valor"><?= (int)$qtdHoje ?></span>
        </div>
    </div>
</div>

<?php if ($msg): ?>
    <p class="msg <?= $msgTipo === 'erro' ? 'msg-erro' : ($msgTipo === 'aviso' ? 'msg-aviso' : 'msg-ok') ?>"><?= $msg ?></p>
<?php endif; ?>

<form method="POST" id="formVenda" class="venda-layout">
    <!-- ================= COLUNA ESQUERDA: ITENS ================= -->
    <div class="venda-principal">

        <!-- Barra de busca de produtos -->
        <div class="produto-busca">
            <input type="search" id="buscaProduto"
                   placeholder="🔍 Buscar produto por nome ou categoria..."
                   autocomplete="off">
            <button type="button" class="btn-add" onclick="addItem()">
                <span>➕</span> Adicionar Item
            </button>
        </div>

        <!-- Lista de produtos rápidos (grid clicável) -->
        <div class="produtos-rapidos" id="produtosRapidos">
            <?php foreach ($produtos as $p): ?>
                <button type="button" class="produto-card"
                        data-id="<?= (int)$p['id'] ?>"
                        data-nome="<?= htmlspecialchars($p['nome']) ?>"
                        data-categoria="<?= $p['categoria'] ?>"
                        data-preco="<?= $p['preco_venda'] ?>"
                        data-estoque="<?= (int)$p['estoque'] ?>">
                    <span class="produto-badge <?= $p['categoria'] === 'estoque' ? 'badge-azul' : 'badge-laranja' ?>">
                        <?= $p['categoria'] === 'estoque' ? '📦 Estoque' : '🥐 Produção' ?>
                    </span>
                    <span class="produto-nome"><?= htmlspecialchars($p['nome']) ?></span>
                    <span class="produto-preco">R$ <?= number_format($p['preco_venda'], 2, ',', '.') ?></span>
                    <span class="produto-estoque">
                        <?= $p['categoria'] === 'estoque' ? $p['estoque'] . ' un.' : 'Sob demanda' ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Tabela do carrinho -->
        <div class="carrinho-box">
            <div class="carrinho-header">
                <h2>🛍️ Itens da Venda</h2>
                <button type="button" class="btn-limpar" onclick="limparCarrinho()">
                    🗑️ Limpar
                </button>
            </div>

            <table id="tabelaItens" class="tabela-carrinho">
                <thead>
                    <tr>
                        <th style="width: 40%">Produto</th>
                        <th style="width: 12%">Qtd</th>
                        <th style="width: 16%">Preço</th>
                        <th style="width: 18%">Subtotal</th>
                        <th style="width: 8%"></th>
                    </tr>
                </thead>
                <tbody><!-- preenchido via JS --></tbody>
            </table>

            <div id="carrinhoVazio" class="carrinho-vazio">
                <span>🛒</span>
                <p>Nenhum item adicionado ainda</p>
                <small>Clique em um produto acima ou use o botão "Adicionar Item"</small>
            </div>
        </div>
    </div>

    <!-- ================= COLUNA DIREITA: RESUMO ================= -->
    <aside class="venda-resumo">
        <div class="resumo-box">
            <h3>Resumo da Venda</h3>

            <div class="resumo-linha">
                <span>Itens</span>
                <strong id="resumoItens">0</strong>
            </div>
            <div class="resumo-linha">
                <span>Quantidade</span>
                <strong id="resumoQtd">0</strong>
            </div>

            <div class="resumo-divisor"></div>

            <div class="resumo-total">
                <span>Total</span>
                <strong id="totalGeral">R$ 0,00</strong>
            </div>

            <button type="submit" class="btn-finalizar" id="btnFinalizar" disabled>
                💾 Finalizar Venda
            </button>

            <p class="resumo-aviso">
                Pressione <kbd>Enter</kbd> para adicionar item rapidamente
            </p>
        </div>

        <!-- Últimas vendas do dia -->
        <div class="ultimas-vendas">
            <h3>🕐 Últimas vendas de hoje</h3>
            <?php if (empty($ultimasVendas)): ?>
                <p class="sem-vendas">Nenhuma venda registrada hoje.</p>
            <?php else: ?>
                <ul class="lista-vendas">
                    <?php foreach ($ultimasVendas as $v): ?>
                        <li>
                            <div>
                                <strong>#<?= $v['id'] ?></strong>
                                <small><?= date('H:i', strtotime($v['data_venda'])) ?></small>
                            </div>
                            <span>R$ <?= number_format($v['total'], 2, ',', '.') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </aside>
</form>

<!-- ============ CUPOM (oculto, só aparece na impressão) ============ -->
<div id="cupom" class="cupom" style="display: none;"></div>

<script>
// ============================================================
// DADOS DOS PRODUTOS (vindos do PHP)
// ============================================================
const produtos = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;

// ============================================================
// ADICIONAR ITEM VAZIO (linha manual)
// ============================================================
function addItem(produtoId = null, qtd = 1) {
    const tbody = document.querySelector('#tabelaItens tbody');
    const tr = document.createElement('tr');
    tr.className = 'item-linha';

    const optionsHtml = produtos.map(p => `
        <option value="${p.id}"
                data-preco="${p.preco_venda}"
                data-estoque="${p.estoque}"
                data-categoria="${p.categoria}"
                ${produtoId == p.id ? 'selected' : ''}>
            ${p.nome} — R$ ${parseFloat(p.preco_venda).toFixed(2)}
        </option>
    `).join('');

    tr.innerHTML = `
        <td>
            <select name="itens[][produto_id]" class="select-produto" onchange="calcTotal()" required>
                <option value="">Selecione o produto</option>
                ${optionsHtml}
            </select>
        </td>
        <td>
            <input type="number" name="itens[][quantidade]" class="input-qtd"
                   value="${qtd}" min="1" onchange="calcTotal()" oninput="calcTotal()" required>
        </td>
        <td class="celula-preco">R$ 0,00</td>
        <td class="celula-subtotal"><strong>R$ 0,00</strong></td>
        <td class="celula-acao">
            <button type="button" class="btn-remover" title="Remover" onclick="removerItem(this)">✕</button>
        </td>
    `;

    tbody.appendChild(tr);

    // Foca no select se for linha nova
    if (!produtoId) {
        tr.querySelector('select').focus();
    }

    calcTotal();
}

// ============================================================
// REMOVER ITEM
// ============================================================
async function removerItem(btn) {
    const linha = btn.closest('tr');
    linha.style.animation = 'fadeOutRight .25s ease forwards';
    setTimeout(() => {
        linha.remove();
        calcTotal();
    }, 200);
}

// ============================================================
// LIMPAR CARRINHO
// ============================================================
async function limparCarrinho() {
    const linhas = document.querySelectorAll('#tabelaItens tbody tr');
    if (!linhas.length) return;

    if (typeof confirmar === 'function') {
        const ok = await confirmar({
            titulo: '🗑️ Limpar carrinho',
            mensagem: 'Deseja remover todos os itens da venda?',
            textoBotao: 'Sim, limpar',
            tipoBotao: 'perigo'
        });
        if (!ok) return;
    }

    document.querySelector('#tabelaItens tbody').innerHTML = '';
    calcTotal();
}

// ============================================================
// CALCULAR TOTAL
// ============================================================
function calcTotal() {
    let total = 0;
    let qtdTotal = 0;
    let numItens = 0;

    const linhas = document.querySelectorAll('#tabelaItens tbody tr');

    linhas.forEach(tr => {
        const sel = tr.querySelector('select');
        const qtdInput = tr.querySelector('.input-qtd');
        const qtd = parseInt(qtdInput.value) || 0;

        const opt = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.dataset.preco || 0);
        const estoque = parseInt(opt?.dataset.estoque || 0);
        const categoria = opt?.dataset.categoria;

        const sub = preco * qtd;

        // Aviso visual de estoque
        const excedeEstoque = categoria === 'estoque' && qtd > estoque;
        tr.classList.toggle('linha-erro', excedeEstoque);
        qtdInput.title = excedeEstoque ? `⚠️ Apenas ${estoque} em estoque` : '';

        tr.querySelector('.celula-preco').textContent = formatarMoeda(preco);
        tr.querySelector('.celula-subtotal').innerHTML = '<strong>' + formatarMoeda(sub) + '</strong>';

        if (opt.value) {
            total += sub;
            qtdTotal += qtd;
            numItens++;
        }
    });

    // Atualiza totais
    document.getElementById('totalGeral').textContent = formatarMoeda(total);
    document.getElementById('resumoItens').textContent = numItens;
    document.getElementById('resumoQtd').textContent = qtdTotal;

    // Mostra/esconde "carrinho vazio"
    const vazio = document.getElementById('carrinhoVazio');
    if (vazio) vazio.style.display = numItens === 0 ? 'flex' : 'none';

    // Habilita/desabilita finalizar
    const btnFin = document.getElementById('btnFinalizar');
    if (btnFin) btnFin.disabled = numItens === 0 || temErroEstoque();

    // Anima o total
    const elTotal = document.getElementById('totalGeral');
    elTotal.classList.add('pulsar');
    setTimeout(() => elTotal.classList.remove('pulsar'), 300);
}

function temErroEstoque() {
    return !!document.querySelector('#tabelaItens tr.linha-erro');
}

// ============================================================
// UTILITÁRIO: formatar moeda
// ============================================================
function formatarMoeda(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ============================================================
// CLICAR EM PRODUTO RÁPIDO → ADICIONA AO CARRINHO
// ============================================================
document.addEventListener('click', function (e) {
    const card = e.target.closest('.produto-card');
    if (!card) return;

    const id = card.dataset.id;

    // Se já existe no carrinho → incrementa
    let encontrado = false;
    document.querySelectorAll('#tabelaItens tbody tr').forEach(tr => {
        const sel = tr.querySelector('select');
        if (sel.value === id) {
            const input = tr.querySelector('.input-qtd');
            input.value = parseInt(input.value) + 1;
            encontrado = true;
        }
    });

    if (!encontrado) {
        addItem(id, 1);
    } else {
        calcTotal();
    }

    // Feedback visual no card
    card.classList.add('card-pulsando');
    setTimeout(() => card.classList.remove('card-pulsando'), 300);

    if (typeof toast === 'function') {
        toast(`➕ ${card.dataset.nome} adicionado`, 'sucesso', 1500);
    }
});

// ============================================================
// BUSCA DE PRODUTOS EM TEMPO REAL
// ============================================================
document.addEventListener('input', function (e) {
    if (e.target.id !== 'buscaProduto') return;

    const termo = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.produto-card').forEach(card => {
        const nome = card.dataset.nome.toLowerCase();
        const cat  = card.dataset.categoria.toLowerCase();
        const match = !termo || nome.includes(termo) || cat.includes(termo);
        card.style.display = match ? '' : 'none';
    });
});

// ============================================================
// ATALHO: Enter no input de quantidade adiciona nova linha
// ============================================================
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.target.matches('#tabelaItens input, #tabelaItens select')) {
        e.preventDefault();
        // Se está na última linha → adiciona nova
        const tr = e.target.closest('tr');
        const tbody = document.querySelector('#tabelaItens tbody');
        if (tr === tbody.lastElementChild) {
            addItem();
        } else {
            // Se não, vai pra próxima linha
            tr.nextElementSibling?.querySelector('select')?.focus();
        }
    }
});

// ============================================================
// CUPOM SIMPLES APÓS FINALIZAR
// ============================================================
<?php if ($vendaId): ?>
document.addEventListener('DOMContentLoaded', function () {
    console.log('✅ Venda #<?= $vendaId ?> registrada');
});
<?php endif; ?>

// ============================================================
// INICIALIZAÇÃO
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    calcTotal();
    // Adiciona 1 linha vazia para começar
    addItem();
});
</script>

<?php require 'includes/footer.php'; ?>