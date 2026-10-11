/* ============================================================
   LÓGICA DE VENDAS - Padaria do Gil
   ============================================================ */
(function () {
    'use strict';

    const produtos = window.produtosVenda || [];

    // --------- Adicionar linha de item ---------
    window.addItem = function () {
        const tbody = document.querySelector('#tabelaItens tbody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.style.animation = 'fadeInUp .3s ease';
        tr.innerHTML = `
            <td>
                <select name="itens[][produto_id]" onchange="calcTotal()" required>
                    <option value="">Selecione o produto</option>
                    ${produtos.map(p => `
                        <option value="${p.id}" data-preco="${p.preco_venda}">
                            ${p.nome} — ${PadariaGil.formatarMoeda(p.preco_venda)}
                        </option>
                    `).join('')}
                </select>
            </td>
            <td>
                <input type="number" name="itens[][quantidade]" value="1" min="1"
                       onchange="calcTotal()" oninput="calcTotal()" required>
            </td>
            <td class="preco">R$ 0,00</td>
            <td class="subtotal"><strong>R$ 0,00</strong></td>
            <td>
                <button type="button" class="btn-remover" title="Remover"
                        onclick="removerItem(this)">🗑️</button>
            </td>
        `;
        tbody.appendChild(tr);
    };

    // --------- Remover item com confirmação ---------
    window.removerItem = async function (btn) {
        const linha = btn.closest('tr');
        if (await window.confirmar({
            titulo: '🗑️ Remover item',
            mensagem: 'Deseja remover este item da venda?',
            textoBotao: 'Remover',
            tipoBotao: 'perigo'
        })) {
            linha.style.animation = 'fadeOutRight .3s ease forwards';
            setTimeout(() => {
                linha.remove();
                calcTotal();
            }, 250);
        }
    };

    // --------- Recalcular total ---------
    window.calcTotal = function () {
        let total = 0;
        document.querySelectorAll('#tabelaItens tbody tr').forEach((tr) => {
            const sel = tr.querySelector('select');
            const qtdInput = tr.querySelector('input[type="number"]');
            const qtd = parseInt(qtdInput.value) || 0;

            const opt = sel.options[sel.selectedIndex];
            const preco = parseFloat(opt?.dataset.preco || 0);
            const sub = preco * qtd;

            tr.querySelector('.preco').textContent = PadariaGil.formatarMoeda(preco);
            tr.querySelector('.subtotal strong').textContent = PadariaGil.formatarMoeda(sub);

            total += sub;
        });

        const elTotal = document.getElementById('totalGeral');
        if (elTotal) {
            elTotal.innerHTML = `Total: <span>${PadariaGil.formatarMoeda(total)}</span>`;
            elTotal.classList.add('pulsar');
            setTimeout(() => elTotal.classList.remove('pulsar'), 300);
        }
    };

    // --------- Inicialização ---------
    function init() {
        if (!document.getElementById('tabelaItens')) return;
        window.addItem();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();