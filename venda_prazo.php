<?php
/**
 * ============================================================
 * VENDA A PRAZO (FIADO) - Padaria do Gil
 * ============================================================
 */

// 1. Conexão primeiro (antes do header)
require_once 'config/database.php';

// 2. Processa a venda ANTES do header
$msg     = '';
$msgTipo = '';
$vendaId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['itens'])) {
    $clienteId = (int)($_POST['cliente_id'] ?? 0);

    try {
        $pdo->beginTransaction();

        // Buscar cliente
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$clienteId]);
        $cliente = $stmt->fetch();

        if (!$cliente) throw new Exception("Cliente não encontrado.");

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

            if ($prod['categoria'] === 'estoque' && $prod['estoque'] < $qtd) {
                throw new Exception("Estoque insuficiente para '{$prod['nome']}'. Disponível: {$prod['estoque']}.");
            }

            $subtotal = $prod['preco_venda'] * $qtd;
            $total += $subtotal;
            $itensValidos[] = [$pid, $qtd, $prod['preco_venda'], $subtotal, $prod['categoria']];
        }

        if ($total <= 0) throw new Exception("Adicione ao menos 1 item válido.");

        // Verificar limite
        $disponivel = $cliente['limite_credito'] - $cliente['saldo_devedor'];
        if ($total > $disponivel) {
            throw new Exception("Limite de crédito excedido. Disponível: R$ " . number_format($disponivel, 2, ',', '.'));
        }

        // Criar venda
        $stmt = $pdo->prepare("INSERT INTO vendas (cliente_id, usuario_id, tipo, total, status) VALUES (?, ?, 'prazo', ?, 'pendente')");
        $stmt->execute([$clienteId, $_SESSION['usuario_id'] ?? 1, $total]);
        $vendaId = $pdo->lastInsertId();

        $insItem = $pdo->prepare("INSERT INTO venda_itens (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?)");
        $updEstq = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND categoria = 'estoque'");

        foreach ($itensValidos as $it) {
            $insItem->execute([$vendaId, $it[0], $it[1], $it[2], $it[3]]);
            if ($it[4] === 'estoque') $updEstq->execute([$it[1], $it[0]]);
        }

        // Atualizar saldo devedor
        $pdo->prepare("UPDATE clientes SET saldo_devedor = saldo_devedor + ? WHERE id = ?")
            ->execute([$total, $clienteId]);

        $pdo->commit();

        $msg = "Venda a prazo <strong>#$vendaId</strong> registrada para <strong>" . htmlspecialchars($cliente['nome']) . "</strong>! Total: <strong>R$ " . number_format($total, 2, ',', '.') . "</strong>";
        $msgTipo = 'sucesso';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e->getMessage();
        $msgTipo = 'erro';
    }
}

// 3. Header
require 'includes/header.php';

// 4. Dados
$produtos = $pdo->query("
    SELECT * FROM produtos
    WHERE (categoria = 'estoque' AND estoque > 0)
       OR categoria = 'producao'
    ORDER BY categoria DESC, nome ASC
")->fetchAll();

$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC")->fetchAll();

// Vendas a prazo de hoje
$vendasHoje = $pdo->query("
    SELECT v.id, v.total, v.data_venda, c.nome AS cliente
    FROM vendas v
    LEFT JOIN clientes c ON c.id = v.cliente_id
    WHERE DATE(v.data_venda) = CURDATE() AND v.tipo = 'prazo'
    ORDER BY v.id DESC
    LIMIT 5
")->fetchAll();

$totalHoje = $pdo->query("
    SELECT COALESCE(SUM(total),0) FROM vendas
    WHERE DATE(data_venda) = CURDATE() AND tipo = 'prazo'
")->fetchColumn();

$qtdHoje = $pdo->query("
    SELECT COUNT(*) FROM vendas
    WHERE DATE(data_venda) = CURDATE() AND tipo = 'prazo'
")->fetchColumn();
?>

<div class="venda-header">
    <div>
        <h1>📝 Venda a Prazo (Fiado)</h1>
        <p class="subtitulo">Registre vendas com pagamento posterior</p>
    </div>
    <div class="venda-stats">
        <div class="stat-mini">
            <span class="stat-label">Hoje</span>
            <span class="stat-valor">R$ <?= number_format($totalHoje, 2, ',', '.') ?></span>
        </div>
        <div class="stat-mini">
            <span class="stat-label">Fiados</span>
            <span class="stat-valor"><?= (int)$qtdHoje ?></span>
        </div>
    </div>
</div>

<?php if ($msg): ?>
    <p class="msg <?= $msgTipo === 'erro' ? 'msg-erro' : 'msg-ok' ?>"><?= $msg ?></p>
<?php endif; ?>

<form method="POST" id="formVendaPrazo" class="venda-layout">

    <!-- ================= COLUNA ESQUERDA ================= -->
    <div class="venda-principal">

        <!-- ===== ETAPA 1: Escolher cliente ===== -->
        <div class="etapa-box">
            <div class="etapa-titulo">
                <span class="etapa-numero">1</span>
                <h2>Selecione o Cliente</h2>
            </div>

            <div class="cliente-busca">
                <input type="search" id="buscaCliente"
                       placeholder="🔍 Buscar cliente por nome ou telefone..."
                       autocomplete="off">
            </div>

            <div class="clientes-grid" id="clientesGrid">
                <?php foreach ($clientes as $c):
                    $disponivel = (float)$c['limite_credito'] - (float)$c['saldo_devedor'];
                    $uso = $c['limite_credito'] > 0
                        ? min(100, ((float)$c['saldo_devedor'] / (float)$c['limite_credito']) * 100)
                        : 0;

                    // Define cor da barra
                    $classeBarra = 'barra-ok';
                    if ($uso >= 100)     $classeBarra = 'barra-cheia';
                    elseif ($uso >= 80)  $classeBarra = 'barra-alerta';
                    elseif ($uso >= 50)  $classeBarra = 'barra-medio';
                ?>
                    <label class="cliente-card" data-nome="<?= htmlspecialchars(strtolower($c['nome'])) ?>"
                           data-telefone="<?= htmlspecialchars($c['telefone'] ?? '') ?>">
                        <input type="radio" name="cliente_id" value="<?= (int)$c['id'] ?>"
                               data-limite="<?= $c['limite_credito'] ?>"
                               data-devedor="<?= $c['saldo_devedor'] ?>"
                               data-disponivel="<?= $disponivel ?>"
                               data-nome="<?= htmlspecialchars($c['nome']) ?>"
                               onchange="clienteSelecionado(this)" required>

                        <div class="cliente-info">
                            <div class="cliente-topo">
                                <span class="cliente-avatar">
                                    <?= mb_strtoupper(mb_substr($c['nome'], 0, 1)) ?>
                                </span>
                                <div>
                                    <strong class="cliente-nome"><?= htmlspecialchars($c['nome']) ?></strong>
                                    <?php if (!empty($c['telefone'])): ?>
                                        <small class="cliente-tel">📞 <?= htmlspecialchars($c['telefone']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="cliente-valores">
                                <div>
                                    <small>Limite</small>
                                    <strong>R$ <?= number_format($c['limite_credito'], 2, ',', '.') ?></strong>
                                </div>
                                <div>
                                    <small>Devendo</small>
                                    <strong class="<?= $c['saldo_devedor'] > 0 ? 'texto-erro' : '' ?>">
                                        R$ <?= number_format($c['saldo_devedor'], 2, ',', '.') ?>
                                    </strong>
                                </div>
                                <div>
                                    <small>Disponível</small>
                                    <strong class="texto-sucesso">
                                        R$ <?= number_format($disponivel, 2, ',', '.') ?>
                                    </strong>
                                </div>
                            </div>

                            <div class="cliente-barra <?= $classeBarra ?>">
                                <div class="barra-preenchida" style="width: <?= $uso ?>%"></div>
                            </div>
                            <small class="cliente-barra-label"><?= number_format($uso, 0) ?>% do limite utilizado</small>
                        </div>
                    </label>
                <?php endforeach; ?>

                <?php if (empty($clientes)): ?>
                    <div class="aviso-vazio">
                        <span>👥</span>
                        <p>Nenhum cliente cadastrado.</p>
                        <a href="cadastro_cliente.php" class="btn-add">➕ Cadastrar cliente</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== ETAPA 2: Adicionar produtos ===== -->
        <div class="etapa-box">
            <div class="etapa-titulo">
                <span class="etapa-numero">2</span>
                <h2>Adicione os Produtos</h2>
            </div>

            <div class="produto-busca">
                <input type="search" id="buscaProduto"
                       placeholder="🔍 Buscar produto..."
                       autocomplete="off">
                <button type="button" class="btn-add" onclick="addItem()">
                    <span>➕</span> Item Manual
                </button>
            </div>

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

            <!-- Carrinho -->
            <div class="carrinho-box">
                <div class="carrinho-header">
                    <h3>🛍️ Itens do Fiado</h3>
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
                    <tbody></tbody>
                </table>

                <div id="carrinhoVazio" class="carrinho-vazio">
                    <span>🛒</span>
                    <p>Nenhum item adicionado ainda</p>
                    <small>Clique em um produto acima para começar</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= COLUNA DIREITA: RESUMO ================= -->
    <aside class="venda-resumo">
        <div class="resumo-box resumo-prazo">
            <h3>Resumo do Fiado</h3>

            <!-- Cliente selecionado -->
            <div class="resumo-cliente" id="resumoCliente">
                <div class="resumo-cliente-vazio">
                    <span>👤</span>
                    <small>Nenhum cliente selecionado</small>
                </div>
            </div>

            <div class="resumo-divisor"></div>

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
                <span>Total do Fiado</span>
                <strong id="totalGeral">R$ 0,00</strong>
            </div>

            <!-- Limite após venda -->
            <div class="limite-info" id="limiteInfo" style="display: none;">
                <div class="limite-linha">
                    <span>Limite disponível</span>
                    <strong id="limiteDisponivel">R$ 0,00</strong>
                </div>
                <div class="limite-linha">
                    <span>Após esta venda</span>
                    <strong id="limiteApos">R$ 0,00</strong>
                </div>

                <div class="cliente-barra" id="barraApos">
                    <div class="barra-preenchida" style="width: 0%"></div>
                </div>
            </div>

            <div class="aviso-limite" id="avisoLimite" style="display: none;">
                ⚠️ <strong>Limite excedido!</strong> Reduza os itens ou escolha outro cliente.
            </div>

            <button type="submit" class="btn-finalizar btn-prazo" id="btnFinalizar" disabled>
                📝 Registrar Fiado
            </button>

            <p class="resumo-aviso">
                O valor será somado ao saldo devedor do cliente
            </p>
        </div>

        <!-- Últimos fiados -->
        <div class="ultimas-vendas">
            <h3>🕐 Fiados de hoje</h3>
            <?php if (empty($vendasHoje)): ?>
                <p class="sem-vendas">Nenhum fiado registrado hoje.</p>
            <?php else: ?>
                <ul class="lista-vendas">
                    <?php foreach ($vendasHoje as $v): ?>
                        <li>
                            <div>
                                <strong>#<?= $v['id'] ?></strong>
                                <small><?= date('H:i', strtotime($v['data_venda'])) ?></small>
                            </div>
                            <div class="lista-vendas-cliente">
                                <small><?= htmlspecialchars($v['cliente'] ?? '—') ?></small>
                                <span>R$ <?= number_format($v['total'], 2, ',', '.') ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </aside>
</form>

<script>
// ============================================================
// DADOS DO PHP
// ============================================================
const produtos = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;

let clienteAtual = null; // { id, nome, limite, devedor, disponivel }

// ============================================================
// UTILITÁRIOS
// ============================================================
function formatarMoeda(v) {
    return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ============================================================
// CLIENTE SELECIONADO
// ============================================================
function clienteSelecionado(radio) {
    clienteAtual = {
        id:         radio.value,
        nome:       radio.dataset.nome,
        limite:     parseFloat(radio.dataset.limite) || 0,
        devedor:    parseFloat(radio.dataset.devedor) || 0,
        disponivel: parseFloat(radio.dataset.disponivel) || 0
    };

    // Marca visual no card
    document.querySelectorAll('.cliente-card').forEach(c => c.classList.remove('selecionado'));
    radio.closest('.cliente-card').classList.add('selecionado');

    // Atualiza resumo lateral
    const resumo = document.getElementById('resumoCliente');
    resumo.innerHTML = `
        <div class="resumo-cliente-ativo">
            <span class="cliente-avatar">${clienteAtual.nome.charAt(0).toUpperCase()}</span>
            <div>
                <strong>${clienteAtual.nome}</strong>
                <small>Disponível: ${formatarMoeda(clienteAtual.disponivel)}</small>
            </div>
        </div>
    `;

    document.getElementById('limiteInfo').style.display = 'block';
    calcTotal();
}

// ============================================================
// ADICIONAR ITEM
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
    if (!produtoId) tr.querySelector('select').focus();
    calcTotal();
}

// ============================================================
// REMOVER
// ============================================================
function removerItem(btn) {
    const linha = btn.closest('tr');
    linha.style.animation = 'fadeOutRight .25s ease forwards';
    setTimeout(() => {
        linha.remove();
        calcTotal();
    }, 200);
}

async function limparCarrinho() {
    const linhas = document.querySelectorAll('#tabelaItens tbody tr');
    if (!linhas.length) return;

    if (typeof confirmar === 'function') {
        const ok = await confirmar({
            titulo: '🗑️ Limpar carrinho',
            mensagem: 'Deseja remover todos os itens?',
            textoBotao: 'Sim, limpar',
            tipoBotao: 'perigo'
        });
        if (!ok) return;
    }

    document.querySelector('#tabelaItens tbody').innerHTML = '';
    calcTotal();
}

// ============================================================
// CALCULAR TOTAL E LIMITE
// ============================================================
function calcTotal() {
    let total = 0;
    let qtdTotal = 0;
    let numItens = 0;

    document.querySelectorAll('#tabelaItens tbody tr').forEach(tr => {
        const sel = tr.querySelector('select');
        const qtdInput = tr.querySelector('.input-qtd');
        const qtd = parseInt(qtdInput.value) || 0;

        const opt = sel.options[sel.selectedIndex];
        const preco = parseFloat(opt?.dataset.preco || 0);
        const estoque = parseInt(opt?.dataset.estoque || 0);
        const categoria = opt?.dataset.categoria;

        const sub = preco * qtd;

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

    // Atualiza painéis
    document.getElementById('totalGeral').textContent = formatarMoeda(total);
    document.getElementById('resumoItens').textContent = numItens;
    document.getElementById('resumoQtd').textContent = qtdTotal;

    const vazio = document.getElementById('carrinhoVazio');
    if (vazio) vazio.style.display = numItens === 0 ? 'flex' : 'none';

    // Atualiza limites
    atualizarLimites(total, numItens);

    // Animação
    const elTotal = document.getElementById('totalGeral');
    elTotal.classList.add('pulsar');
    setTimeout(() => elTotal.classList.remove('pulsar'), 300);
}

function atualizarLimites(totalVenda, numItens) {
    if (!clienteAtual) return;

    const disponivelApos = clienteAtual.disponivel - totalVenda;
    const devedorApos = clienteAtual.devedor + totalVenda;
    const usoApos = clienteAtual.limite > 0
        ? Math.min(100, (devedorApos / clienteAtual.limite) * 100)
        : 0;

    // Atualiza valores
    document.getElementById('limiteDisponivel').textContent = formatarMoeda(clienteAtual.disponivel);
    const elApos = document.getElementById('limiteApos');
    elApos.textContent = formatarMoeda(Math.max(0, disponivelApos));
    elApos.className = disponivelApos < 0 ? 'texto-erro' : 'texto-sucesso';

    // Barra
    const barra = document.getElementById('barraApos');
    const preenchida = barra.querySelector('.barra-preenchida');
    preenchida.style.width = usoApos + '%';

    // Cores
    barra.classList.remove('barra-ok','barra-medio','barra-alerta','barra-cheia');
    if (usoApos >= 100)      barra.classList.add('barra-cheia');
    else if (usoApos >= 80)  barra.classList.add('barra-alerta');
    else if (usoApos >= 50)  barra.classList.add('barra-medio');
    else                     barra.classList.add('barra-ok');

    // Bloqueio
    const excedeu = disponivelApos < 0;
    document.getElementById('avisoLimite').style.display = excedeu ? 'block' : 'none';

    // Habilita/desabilita botão
    const btn = document.getElementById('btnFinalizar');
    btn.disabled = !clienteAtual || numItens === 0 || excedeu || temErroEstoque();
}

function temErroEstoque() {
    return !!document.querySelector('#tabelaItens tr.linha-erro');
}

// ============================================================
// CLICAR EM PRODUTO RÁPIDO
// ============================================================
document.addEventListener('click', function (e) {
    const card = e.target.closest('.produto-card');
    if (!card) return;

    const id = card.dataset.id;

    let encontrado = false;
    document.querySelectorAll('#tabelaItens tbody tr').forEach(tr => {
        const sel = tr.querySelector('select');
        if (sel.value === id) {
            const input = tr.querySelector('.input-qtd');
            input.value = parseInt(input.value) + 1;
            encontrado = true;
        }
    });

    if (!encontrado) addItem(id, 1);
    else calcTotal();

    card.classList.add('card-pulsando');
    setTimeout(() => card.classList.remove('card-pulsando'), 300);

    if (typeof toast === 'function') {
        toast(`➕ ${card.dataset.nome}`, 'sucesso', 1200);
    }
});

// ============================================================
// BUSCAS
// ============================================================
document.addEventListener('input', function (e) {
    // Busca produto
    if (e.target.id === 'buscaProduto') {
        const termo = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.produto-card').forEach(card => {
            const match = !termo
                || card.dataset.nome.toLowerCase().includes(termo)
                || card.dataset.categoria.toLowerCase().includes(termo);
            card.style.display = match ? '' : 'none';
        });
    }

    // Busca cliente
    if (e.target.id === 'buscaCliente') {
        const termo = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.cliente-card').forEach(card => {
            const match = !termo
                || card.dataset.nome.includes(termo)
                || card.dataset.telefone.includes(termo);
            card.style.display = match ? '' : 'none';
        });
    }
});

// ============================================================
// ENTER → ADICIONAR ITEM
// ============================================================
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.target.matches('#tabelaItens input, #tabelaItens select')) {
        e.preventDefault();
        const tr = e.target.closest('tr');
        const tbody = document.querySelector('#tabelaItens tbody');
        if (tr === tbody.lastElementChild) addItem();
        else tr.nextElementSibling?.querySelector('select')?.focus();
    }
});

// ============================================================
// INICIALIZAÇÃO
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    calcTotal();
    addItem();
});
</script>

<?php require 'includes/footer.php'; ?>