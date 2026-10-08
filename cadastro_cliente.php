<?php
/**
 * ============================================================
 * CADASTRO DE CLIENTES - Padaria do Gil
 * ============================================================
 */

// 1. Conexão ANTES do header (evita "headers already sent")
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
            // Verifica se tem vendas vinculadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE cliente_id = ?");
            $stmt->execute([$id]);
            $temVendas = $stmt->fetchColumn();

            if ($temVendas > 0) {
                $msg = "Não é possível excluir: este cliente possui $temVendas venda(s) registrada(s).";
                $msgTipo = 'erro';
            } else {
                $pdo->prepare("DELETE FROM clientes WHERE id = ?")->execute([$id]);
                $msg = "Cliente excluído com sucesso.";
                $msgTipo = 'sucesso';
            }
        } catch (PDOException $e) {
            $msg = "Erro ao excluir: " . $e->getMessage();
            $msgTipo = 'erro';
        }
    }
    // ---------- EDITAR ----------
    elseif ($acao === 'editar') {
        $id      = (int)$_POST['id'];
        $nome    = trim($_POST['nome']);
        $tel     = trim($_POST['telefone']);
        $end     = trim($_POST['endereco']);
        $limite  = (float)str_replace(['.', ','], ['', '.'], $_POST['limite_credito'] ?? '0');

        if ($nome === '') {
            $msg = "O nome é obrigatório.";
            $msgTipo = 'erro';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE clientes
                    SET nome = ?, telefone = ?, endereco = ?, limite_credito = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $tel, $end, $limite, $id]);
                $msg = "Cliente atualizado com sucesso!";
                $msgTipo = 'sucesso';
            } catch (PDOException $e) {
                $msg = "Erro ao atualizar: " . $e->getMessage();
                $msgTipo = 'erro';
            }
        }
    }
    // ---------- CRIAR ----------
    else {
        $nome   = trim($_POST['nome']);
        $tel    = trim($_POST['telefone']);
        $end    = trim($_POST['endereco']);
        $limite = (float)str_replace(['.', ','], ['', '.'], $_POST['limite_credito'] ?? '0');

        if ($nome === '') {
            $msg = "O nome é obrigatório.";
            $msgTipo = 'erro';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO clientes (nome, telefone, endereco, limite_credito, saldo_devedor)
                    VALUES (?, ?, ?, ?, 0)
                ");
                $stmt->execute([$nome, $tel, $end, $limite]);
                $msg = "Cliente <strong>" . htmlspecialchars($nome) . "</strong> cadastrado com sucesso!";
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
$kpiTotal     = $pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$kpiDevedores = $pdo->query("SELECT COUNT(*) FROM clientes WHERE saldo_devedor > 0")->fetchColumn();
$kpiReceber   = $pdo->query("SELECT COALESCE(SUM(saldo_devedor),0) FROM clientes")->fetchColumn();
$kpiLimite    = $pdo->query("SELECT COALESCE(SUM(limite_credito),0) FROM clientes")->fetchColumn();
$kpiEstourado = $pdo->query("
    SELECT COUNT(*) FROM clientes
    WHERE saldo_devedor >= limite_credito AND limite_credito > 0
")->fetchColumn();

// 5. Lista de clientes
$clientes = $pdo->query("
    SELECT *,
        (limite_credito - saldo_devedor) AS disponivel
    FROM clientes
    ORDER BY nome ASC
")->fetchAll();
?>

<!-- ==================== CABEÇALHO ==================== -->
<div class="page-header">
    <div>
        <h1>👥 Cadastro de Clientes</h1>
        <p class="subtitulo">Gerencie seus clientes e limites de crédito</p>
    </div>
    <button type="button" class="btn-novo" onclick="abrirModal()">
        ➕ Novo Cliente
    </button>
</div>

<?php if ($msg): ?>
    <p class="msg <?= $msgTipo === 'erro' ? 'msg-erro' : 'msg-ok' ?>"><?= $msg ?></p>
<?php endif; ?>

<!-- ==================== KPIs ==================== -->
<div class="cards kpis-clientes">
    <div class="card marrom">
        <h3>Total de Clientes</h3>
        <p><?= (int)$kpiTotal ?></p>
    </div>
    <div class="card azul">
        <h3>Com Dívida</h3>
        <p><?= (int)$kpiDevedores ?></p>
    </div>
    <div class="card verde">
        <h3>💵 A Receber</h3>
        <p>R$ <?= number_format($kpiReceber, 2, ',', '.') ?></p>
    </div>
    <div class="card laranja">
        <h3>Limite Concedido</h3>
        <p>R$ <?= number_format($kpiLimite, 2, ',', '.') ?></p>
    </div>
    <div class="card <?= $kpiEstourado > 0 ? 'vermelho' : 'roxo' ?>">
        <h3>⚠️ Limite Estourado</h3>
        <p><?= (int)$kpiEstourado ?></p>
    </div>
</div>

<!-- ==================== FILTROS ==================== -->
<div class="filtros-produtos">
    <input type="search" id="buscaCliente"
           placeholder="🔍 Buscar por nome, telefone ou endereço..."
           class="input-busca">

    <div class="filtro-tabs">
        <button type="button" class="tab-filtro ativo" data-filtro="todos">Todos</button>
        <button type="button" class="tab-filtro" data-filtro="devedores">💰 Devedores</button>
        <button type="button" class="tab-filtro" data-filtro="limpos">✅ Sem dívida</button>
        <button type="button" class="tab-filtro" data-filtro="estourado">⚠️ Estourado</button>
    </div>
</div>

<!-- ==================== LISTA ==================== -->
<?php if (empty($clientes)): ?>
    <div class="aviso-vazio">
        <span>👥</span>
        <p>Nenhum cliente cadastrado ainda.</p>
        <button type="button" class="btn-add" onclick="abrirModal()">➕ Cadastrar primeiro cliente</button>
    </div>
<?php else: ?>

<div class="clientes-grid-cadastro" id="clientesGrid">
    <?php foreach ($clientes as $c):
        $limite = (float)$c['limite_credito'];
        $devedor = (float)$c['saldo_devedor'];
        $disponivel = (float)$c['disponivel'];

        $uso = $limite > 0 ? min(100, ($devedor / $limite) * 100) : 0;
        $estourado = $devedor >= $limite && $limite > 0;

        // Classe da barra
        $classeBarra = 'barra-ok';
        if ($estourado)       $classeBarra = 'barra-cheia';
        elseif ($uso >= 80)   $classeBarra = 'barra-alerta';
        elseif ($uso >= 50)   $classeBarra = 'barra-medio';

        // Iniciais (2 letras)
        $partes = explode(' ', trim($c['nome']));
        $iniciais = strtoupper(
            mb_substr($partes[0], 0, 1) .
            (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')
        );
    ?>
        <div class="cliente-item-cad"
             data-nome="<?= htmlspecialchars(strtolower($c['nome'])) ?>"
             data-tel="<?= htmlspecialchars($c['telefone'] ?? '') ?>"
             data-end="<?= htmlspecialchars(strtolower($c['endereco'] ?? '')) ?>"
             data-devedor="<?= $devedor > 0 ? '1' : '0' ?>"
             data-estourado="<?= $estourado ? '1' : '0' ?>">

            <div class="cliente-item-topo">
                <span class="cliente-avatar-lg"><?= $iniciais ?></span>
                <div class="cliente-titulo-box">
                    <h3 class="cliente-titulo"><?= htmlspecialchars($c['nome']) ?></h3>
                    <?php if (!empty($c['telefone'])): ?>
                        <small class="cliente-sub">📞 <?= htmlspecialchars($c['telefone']) ?></small>
                    <?php endif; ?>
                </div>
                <?php if ($estourado): ?>
                    <span class="badge-status badge-erro">Limite cheio</span>
                <?php elseif ($devedor > 0): ?>
                    <span class="badge-status badge-aviso">Devendo</span>
                <?php else: ?>
                    <span class="badge-status badge-sucesso">Em dia</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($c['endereco'])): ?>
                <p class="cliente-endereco">📍 <?= htmlspecialchars($c['endereco']) ?></p>
            <?php endif; ?>

            <div class="cliente-valores-cad">
                <div>
                    <small>Limite</small>
                    <strong>R$ <?= number_format($limite, 2, ',', '.') ?></strong>
                </div>
                <div>
                    <small>Devedor</small>
                    <strong class="<?= $devedor > 0 ? 'texto-erro' : '' ?>">
                        R$ <?= number_format($devedor, 2, ',', '.') ?>
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

            <div class="cliente-item-acoes">
                <?php if ($devedor > 0): ?>
                    <a href="venda_prazo.php?cliente=<?= (int)$c['id'] ?>"
                       class="btn-icon" title="Novo fiado">
                        📝
                    </a>
                <?php else: ?>
                    <a href="venda.php" class="btn-icon" title="Nova venda">
                        🛒
                    </a>
                <?php endif; ?>

                <button type="button" class="btn-icon"
                        title="Editar"
                        onclick='editarCliente(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    ✏️
                </button>

                <form method="POST" style="display: inline;"
                      onsubmit="return confirmarExclusao(event, '<?= htmlspecialchars(addslashes($c['nome'])) ?>')">
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="btn-icon btn-icon-perigo" title="Excluir">🗑️</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="buscaVazia" class="aviso-vazio" style="display: none;">
    <span>🔍</span>
    <p>Nenhum cliente encontrado com esse filtro.</p>
</div>

<?php endif; ?>

<!-- ==================== MODAL ==================== -->
<div class="modal-overlay" id="modalCliente" style="display: none;">
    <div class="modal-box modal-grande">
        <div class="modal-header">
            <h3 class="modal-titulo" id="modalTitulo">➕ Novo Cliente</h3>
            <button type="button" class="modal-fechar" onclick="fecharModal()">✕</button>
        </div>

        <form method="POST" id="formCliente" autocomplete="off">
            <input type="hidden" name="acao" value="criar" id="formAcao">
            <input type="hidden" name="id" value="" id="formId">

            <div class="form-modal-grid">
                <div class="campo campo-full">
                    <label for="nome">Nome completo *</label>
                    <input type="text" name="nome" id="nome"
                           placeholder="Ex: João da Silva" required maxlength="100">
                </div>

                <div class="campo">
                    <label for="telefone">Telefone</label>
                    <input type="tel" name="telefone" id="telefone"
                           placeholder="(00) 00000-0000" maxlength="20">
                </div>

                <div class="campo">
                    <label for="limite_credito">Limite de crédito (R$)</label>
                    <input type="text" name="limite_credito" id="limite_credito"
                           placeholder="0,00" inputmode="numeric">
                </div>

                <div class="campo campo-full">
                    <label for="endereco">Endereço</label>
                    <input type="text" name="endereco" id="endereco"
                           placeholder="Rua, número, bairro..." maxlength="255">
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
    document.getElementById('modalTitulo').textContent = '➕ Novo Cliente';
    document.getElementById('formAcao').value = 'criar';
    document.getElementById('formId').value = '';
    document.getElementById('formCliente').reset();
    document.getElementById('limite_credito').value = '0,00';
    document.getElementById('modalCliente').style.display = 'flex';
    setTimeout(() => document.getElementById('modalCliente').classList.add('modal-visivel'), 10);
    setTimeout(() => document.getElementById('nome').focus(), 200);
}

function fecharModal() {
    const modal = document.getElementById('modalCliente');
    modal.classList.remove('modal-visivel');
    setTimeout(() => modal.style.display = 'none', 250);
}

function editarCliente(c) {
    document.getElementById('modalTitulo').textContent = '✏️ Editar Cliente';
    document.getElementById('formAcao').value = 'editar';
    document.getElementById('formId').value = c.id;
    document.getElementById('nome').value = c.nome;
    document.getElementById('telefone').value = c.telefone || '';
    document.getElementById('endereco').value = c.endereco || '';
    document.getElementById('limite_credito').value = parseFloat(c.limite_credito).toLocaleString('pt-BR', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
    document.getElementById('modalCliente').style.display = 'flex';
    setTimeout(() => document.getElementById('modalCliente').classList.add('modal-visivel'), 10);
}

document.getElementById('modalCliente')?.addEventListener('click', function (e) {
    if (e.target === this) fecharModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalCliente');
        if (modal && modal.classList.contains('modal-visivel')) fecharModal();
    }
});

// ============================================================
// MÁSCARA DE TELEFONE
// ============================================================
document.getElementById('telefone')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10)      v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    else if (v.length > 6)  v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
    else if (v.length > 2)  v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
    else if (v.length > 0)  v = v.replace(/^(\d{0,2})$/, '($1');
    this.value = v;
});

// ============================================================
// MÁSCARA DE MOEDA (limite)
// ============================================================
document.getElementById('limite_credito')?.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '');
    if (!v) { this.value = ''; return; }
    const num = Number(v) / 100;
    this.value = num.toLocaleString('pt-BR', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
});

// ============================================================
// BUSCA E FILTROS
// ============================================================
let filtroAtual = 'todos';

document.getElementById('buscaCliente')?.addEventListener('input', aplicarFiltros);

document.querySelectorAll('.tab-filtro').forEach(tab => {
    tab.addEventListener('click', function () {
        document.querySelectorAll('.tab-filtro').forEach(t => t.classList.remove('ativo'));
        this.classList.add('ativo');
        filtroAtual = this.dataset.filtro;
        aplicarFiltros();
    });
});

function aplicarFiltros() {
    const termo = (document.getElementById('buscaCliente')?.value || '').toLowerCase().trim();
    const itens = document.querySelectorAll('.cliente-item-cad');
    let visiveis = 0;

    itens.forEach(item => {
        const nome  = item.dataset.nome;
        const tel   = item.dataset.tel.toLowerCase();
        const end   = item.dataset.end;
        const dev   = item.dataset.devedor === '1';
        const est   = item.dataset.estourado === '1';

        const matchBusca = !termo ||
            nome.includes(termo) ||
            tel.includes(termo) ||
            end.includes(termo);

        let matchFiltro = true;
        if (filtroAtual === 'devedores') matchFiltro = dev;
        if (filtroAtual === 'limpos')    matchFiltro = !dev;
        if (filtroAtual === 'estourado') matchFiltro = est;

        const mostrar = matchBusca && matchFiltro;
        item.style.display = mostrar ? '' : 'none';
        if (mostrar) visiveis++;
    });

    const aviso = document.getElementById('buscaVazia');
    if (aviso) aviso.style.display = visiveis === 0 ? 'flex' : 'none';
}

// ============================================================
// CONFIRMAR EXCLUSÃO
// ============================================================
function confirmarExclusao(e, nome) {
    e.preventDefault();
    const form = e.target;

    if (typeof confirmar === 'function') {
        confirmar({
            titulo: '🗑️ Excluir cliente',
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
// ATALHO Ctrl+K → busca
// ============================================================
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.getElementById('buscaCliente')?.focus();
    }
});
</script>

<?php require 'includes/footer.php'; ?>