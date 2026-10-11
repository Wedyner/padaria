/* ============================================================
   PADARIA DO GIL - Relatórios Interativos
   Dependência: Chart.js (CDN carregado automaticamente)
   Versão: 1.0
   ============================================================ */

(function () {
    'use strict';

    // ============================================================
    // 1. CARREGAMENTO DINÂMICO DO CHART.JS
    // ============================================================
    function carregarChartJS() {
        return new Promise((resolve, reject) => {
            if (window.Chart) return resolve(window.Chart);

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.onload = () => resolve(window.Chart);
            script.onerror = () => reject(new Error('Falha ao carregar Chart.js'));
            document.head.appendChild(script);
        });
    }

    // ============================================================
    // 2. PALETA DE CORES
    // ============================================================
    const CORES = {
        marrom:  '#8b5e34',
        verde:   '#27ae60',
        azul:    '#2980b9',
        vermelho:'#c0392b',
        laranja: '#e67e22',
        roxo:    '#8e44ad',
        amarelo: '#f39c12',
        rosa:    '#e84393',
        ciano:   '#16a085',
        cinza:   '#7f8c8d'
    };

    const PALETA = [
        CORES.marrom, CORES.verde, CORES.azul, CORES.laranja,
        CORES.vermelho, CORES.roxo, CORES.amarelo, CORES.rosa,
        CORES.ciano, CORES.cinza
    ];

    // Detecta se está no dark mode
    function corTexto() {
        return document.documentElement.getAttribute('data-tema') === 'escuro'
            ? '#e8e8e8' : '#2c2c2c';
    }
    function corGrade() {
        return document.documentElement.getAttribute('data-tema') === 'escuro'
            ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)';
    }

    // ============================================================
    // 3. CONFIGURAÇÃO PADRÃO DO CHART.JS
    // ============================================================
    function configPadrao() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: corTexto(),
                        font: { size: 13, family: "'Segoe UI', sans-serif" },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,.85)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    padding: 12,
                    cornerRadius: 8,
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 13 },
                    displayColors: true,
                    callbacks: {
                        label: function (ctx) {
                            const valor = ctx.parsed.y ?? ctx.parsed;
                            return ` ${ctx.dataset.label || ctx.label}: ${PadariaGil.formatarMoeda(valor)}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: corTexto(), font: { size: 12 } },
                    grid:  { color: corGrade(), drawBorder: false }
                },
                y: {
                    ticks: {
                        color: corTexto(),
                        font: { size: 12 },
                        callback: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR')
                    },
                    grid: { color: corGrade(), drawBorder: false },
                    beginAtZero: true
                }
            }
        };
    }

    // ============================================================
    // 4. CRIAR UM GRÁFICO COM ANIMAÇÃO
    // ============================================================
    const graficos = {}; // Guarda referências p/ destruir ao recriar

    function criarGrafico(id, tipo, dados, opcoesExtras = {}) {
        const canvas = document.getElementById(id);
        if (!canvas) return null;

        // Destrói o anterior (se existir)
        if (graficos[id]) graficos[id].destroy();

        const ctx = canvas.getContext('2d');
        const config = {
            type: tipo,
            data: dados,
            options: {
                ...configPadrao(),
                ...opcoesExtras,
                plugins: {
                    ...configPadrao().plugins,
                    ...(opcoesExtras.plugins || {})
                }
            }
        };

        graficos[id] = new Chart(ctx, config);
        return graficos[id];
    }

    // ============================================================
    // 5. LER DADOS DAS TABELAS (HTML → JS)
    // ============================================================
    function lerTabelaPorDia() {
        const linhas = document.querySelectorAll('#tabela-vendas-dia tbody tr');
        const labels = [], valores = [], qtds = [];

        linhas.forEach((tr) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;

            const data = tds[0].textContent.trim();
            const qtd  = parseInt(tds[1].textContent.trim()) || 0;
            const val  = parseFloat(
                tds[2].textContent.replace(/[^\d,-]/g, '').replace('.', '').replace(',', '.')
            ) || 0;

            labels.push(data);
            qtds.push(qtd);
            valores.push(val);
        });

        return { labels, valores, qtds };
    }

    function lerTabelaPorMes() {
        const linhas = document.querySelectorAll('#tabela-vendas-mes tbody tr');
        const labels = [], valores = [], qtds = [];

        linhas.forEach((tr) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;

            const mes = tds[0].textContent.trim();
            const qtd = parseInt(tds[1].textContent.trim()) || 0;
            const val = parseFloat(
                tds[2].textContent.replace(/[^\d,-]/g, '').replace('.', '').replace(',', '.')
            ) || 0;

            // Formata "2025-10" -> "Out/2025"
            let labelFmt = mes;
            const m = mes.match(/^(\d{4})-(\d{2})$/);
            if (m) {
                const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
                labelFmt = `${meses[parseInt(m[2]) - 1]}/${m[1]}`;
            }

            labels.push(labelFmt);
            qtds.push(qtd);
            valores.push(val);
        });

        return { labels, valores, qtds };
    }

    function lerTabelaTopProdutos() {
        const linhas = document.querySelectorAll('#tabela-top-produtos tbody tr');
        const labels = [], valores = [], qtds = [];

        linhas.forEach((tr) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 3) return;

            const nome = tds[0].textContent.trim();
            const qtd  = parseInt(tds[1].textContent.trim()) || 0;
            const val  = parseFloat(
                tds[2].textContent.replace(/[^\d,-]/g, '').replace('.', '').replace(',', '.')
            ) || 0;

            labels.push(nome);
            qtds.push(qtd);
            valores.push(val);
        });

        return { labels, valores, qtds };
    }

    // ============================================================
    // 6. INJETAR CANVAS NO HTML
    // ============================================================
    function injetarCanvas() {
        // --- GRÁFICO 1: Vendas por Dia (linha) ---
        const tabelaDia = document.getElementById('tabela-vendas-dia');
        if (tabelaDia && !document.getElementById('grafico-dia')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'grafico-wrapper';
            wrapper.innerHTML = `
                <div class="grafico-header">
                    <h3>📈 Vendas por Dia</h3>
                    <div class="grafico-acoes">
                        <button type="button" class="btn-tipo" data-tipo="line" data-alvo="grafico-dia">Linha</button>
                        <button type="button" class="btn-tipo" data-tipo="bar"  data-alvo="grafico-dia">Barra</button>
                    </div>
                </div>
                <div class="grafico-canvas"><canvas id="grafico-dia"></canvas></div>
            `;
            tabelaDia.parentNode.insertBefore(wrapper, tabelaDia);
        }

        // --- GRÁFICO 2: Vendas por Mês (barra) ---
        const tabelaMes = document.getElementById('tabela-vendas-mes');
        if (tabelaMes && !document.getElementById('grafico-mes')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'grafico-wrapper';
            wrapper.innerHTML = `
                <div class="grafico-header">
                    <h3>📊 Vendas por Mês (últimos 12)</h3>
                </div>
                <div class="grafico-canvas"><canvas id="grafico-mes"></canvas></div>
            `;
            tabelaMes.parentNode.insertBefore(wrapper, tabelaMes);
        }

        // --- GRÁFICO 3: Top Produtos (rosca + barra horizontal) ---
        const tabelaTop = document.getElementById('tabela-top-produtos');
        if (tabelaTop && !document.getElementById('grafico-top')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'graficos-duplos';
            wrapper.innerHTML = `
                <div class="grafico-wrapper">
                    <div class="grafico-header"><h3>🏆 Top Produtos (Faturamento)</h3></div>
                    <div class="grafico-canvas"><canvas id="grafico-top"></canvas></div>
                </div>
                <div class="grafico-wrapper">
                    <div class="grafico-header"><h3>🥧 Distribuição por Produto</h3></div>
                    <div class="grafico-canvas"><canvas id="grafico-pizza"></canvas></div>
                </div>
            `;
            tabelaTop.parentNode.insertBefore(wrapper, tabelaTop);
        }
    }

    // ============================================================
    // 7. DESENHAR TODOS OS GRÁFICOS
    // ============================================================
    function desenharGraficoDia() {
        const d = lerTabelaPorDia();
        if (!d.labels.length) return;

        // Ordena por data crescente (para o gráfico fazer sentido)
        const pares = d.labels.map((l, i) => ({ l, v: d.valores[i], q: d.qtds[i] }));
        pares.sort((a, b) => {
            // data BR: dd/mm/yyyy
            const [da, ma, aa] = a.l.split('/');
            const [db, mb, ab] = b.l.split('/');
            return new Date(`${aa}-${ma}-${da}`) - new Date(`${ab}-${mb}-${db}`);
        });

        criarGrafico('grafico-dia', 'line', {
            labels: pares.map(p => p.l),
            datasets: [{
                label: 'Vendas (R$)',
                data: pares.map(p => p.v),
                borderColor: CORES.marrom,
                backgroundColor: 'rgba(139,94,52,.12)',
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: CORES.marrom,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        });
    }

    function desenharGraficoMes() {
        const d = lerTabelaPorMes();
        if (!d.labels.length) return;

        // Inverte para ficar cronológico
        const pares = d.labels.map((l, i) => ({ l, v: d.valores[i], q: d.qtds[i] })).reverse();

        criarGrafico('grafico-mes', 'bar', {
            labels: pares.map(p => p.l),
            datasets: [{
                label: 'Faturamento (R$)',
                data: pares.map(p => p.v),
                backgroundColor: pares.map((_, i) => PALETA[i % PALETA.length] + 'dd'),
                borderColor: pares.map((_, i) => PALETA[i % PALETA.length]),
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false
            }]
        });
    }

    function desenharGraficoTop() {
        const d = lerTabelaTopProdutos();
        if (!d.labels.length) return;

        // Gráfico de barras horizontais
        criarGrafico('grafico-top', 'bar', {
            labels: d.labels,
            datasets: [{
                label: 'Faturamento',
                data: d.valores,
                backgroundColor: d.labels.map((_, i) => PALETA[i % PALETA.length] + 'dd'),
                borderColor: d.labels.map((_, i) => PALETA[i % PALETA.length]),
                borderWidth: 2,
                borderRadius: 8
            }]
        }, {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    ticks: {
                        color: corTexto(),
                        callback: (v) => 'R$ ' + Number(v).toLocaleString('pt-BR')
                    },
                    grid: { color: corGrade() }
                },
                y: {
                    ticks: { color: corTexto(), font: { size: 12 } },
                    grid: { display: false }
                }
            }
        });

        // Pizza / rosca
        criarGrafico('grafico-pizza', 'doughnut', {
            labels: d.labels,
            datasets: [{
                data: d.valores,
                backgroundColor: d.labels.map((_, i) => PALETA[i % PALETA.length]),
                borderColor: document.documentElement.getAttribute('data-tema') === 'escuro' ? '#1a1a1a' : '#fff',
                borderWidth: 3,
                hoverOffset: 12
            }]
        }, {
            cutout: '60%',
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = ((ctx.parsed / total) * 100).toFixed(1);
                            return ` ${ctx.label}: ${PadariaGil.formatarMoeda(ctx.parsed)} (${pct}%)`;
                        }
                    }
                }
            }
        });
    }

    // ============================================================
    // 8. BOTÕES DE ALTERNÂNCIA DE TIPO (Linha ↔ Barra)
    // ============================================================
    function ativarBotoesTipo() {
        document.querySelectorAll('.btn-tipo').forEach((btn) => {
            btn.addEventListener('click', function () {
                const alvo = this.dataset.alvo;
                const tipo = this.dataset.tipo;
                const g = graficos[alvo];
                if (!g) return;

                g.config.type = tipo;

                // Ajustes específicos
                if (tipo === 'bar') {
                    g.data.datasets[0].backgroundColor = 'rgba(139,94,52,.75)';
                    g.data.datasets[0].borderRadius = 8;
                    g.data.datasets[0].fill = false;
                    g.data.datasets[0].tension = 0;
                } else {
                    g.data.datasets[0].backgroundColor = 'rgba(139,94,52,.12)';
                    g.data.datasets[0].borderRadius = 0;
                    g.data.datasets[0].fill = true;
                    g.data.datasets[0].tension = 0.35;
                }

                g.update();

                // Marca botão ativo
                document.querySelectorAll(`.btn-tipo[data-alvo="${alvo}"]`)
                    .forEach(b => b.classList.remove('ativo'));
                this.classList.add('ativo');
            });
        });
    }

    // ============================================================
    // 9. EXPORTAR TABELAS PARA CSV
    // ============================================================
    function exportarTabelaCSV(tabelaId, nomeArquivo) {
        const tabela = document.getElementById(tabelaId);
        if (!tabela) return;

        const linhas = [];
        tabela.querySelectorAll('tr').forEach((tr) => {
            const cols = [...tr.querySelectorAll('th, td')].map((c) => {
                const texto = c.textContent.trim().replace(/"/g, '""');
                return `"${texto}"`;
            });
            linhas.push(cols.join(';'));
        });

        // BOM para Excel reconhecer UTF-8
        const csv = '\uFEFF' + linhas.join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${nomeArquivo}_${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        toast('📥 Arquivo exportado!', 'sucesso');
    }

    // ============================================================
    // 10. ADICIONA BOTÕES DE EXPORTAR NAS TABELAS
    // ============================================================
    function adicionarBotoesExportar() {
        const tabelas = [
            { id: 'tabela-vendas-dia',  nome: 'vendas_por_dia' },
            { id: 'tabela-vendas-mes',  nome: 'vendas_por_mes' },
            { id: 'tabela-top-produtos', nome: 'top_produtos' }
        ];

        tabelas.forEach(({ id, nome }) => {
            const tabela = document.getElementById(id);
            if (!tabela) return;

            const parent = tabela.previousElementSibling;
            if (parent && parent.classList.contains('tabela-toolbar')) return;

            const toolbar = document.createElement('div');
            toolbar.className = 'tabela-toolbar';
            toolbar.innerHTML = `
                <button type="button" class="btn-exportar" data-tabela="${id}" data-nome="${nome}">
                    📥 Exportar CSV
                </button>
            `;
            tabela.parentNode.insertBefore(toolbar, tabela);

            toolbar.querySelector('.btn-exportar').addEventListener('click', function () {
                exportarTabelaCSV(this.dataset.tabela, this.dataset.nome);
            });
        });
    }

    // ============================================================
    // 11. RESUMO EM TEMPO REAL (recalcula os cards)
    // ============================================================
    function atualizarResumo() {
        const d = lerTabelaPorDia();
        if (!d.valores.length) return;

        const total = d.valores.reduce((a, b) => a + b, 0);
        const ticket = total / d.qtds.reduce((a, b) => a + b, 0) || 0;

        const elTicket = document.getElementById('ticket-medio');
        if (elTicket) elTicket.textContent = PadariaGil.formatarMoeda(ticket);
    }

    // ============================================================
    // 12. FILTRO RÁPIDO (atalhos: Hoje / Ontem / 7 dias / 30 dias / Mês)
    // ============================================================
    function adicionarFiltrosRapidos() {
        const formFiltro = document.querySelector('.form-filtro');
        if (!formFiltro || formFiltro.dataset.atalhos === 'on') return;

        formFiltro.dataset.atalhos = 'on';

        const atalhos = [
            { label: 'Hoje',      dias: 0 },
            { label: 'Ontem',     dias: -1 },
            { label: '7 dias',    dias: 7 },
            { label: '30 dias',   dias: 30 },
            { label: 'Este mês',  mes: true }
        ];

        const wrapper = document.createElement('div');
        wrapper.className = 'filtros-rapidos';
        wrapper.innerHTML = atalhos.map(a =>
            `<button type="button" class="btn-atalho" data-label="${a.label}">${a.label}</button>`
        ).join('');

        formFiltro.parentNode.insertBefore(wrapper, formFiltro.nextSibling);

        wrapper.querySelectorAll('.btn-atalho').forEach((btn) => {
            btn.addEventListener('click', function () {
                const label = this.dataset.label;
                const ini = formFiltro.querySelector('input[name="data_ini"]');
                const fim = formFiltro.querySelector('input[name="data_fim"]');
                if (!ini || !fim) return;

                const hoje = new Date();
                const fmt = (d) => d.toISOString().slice(0, 10);

                const atalho = atalhos.find(a => a.label === label);
                if (atalho.mes) {
                    const primeiro = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                    ini.value = fmt(primeiro);
                    fim.value = fmt(hoje);
                } else {
                    const alvo = new Date(hoje);
                    alvo.setDate(alvo.getDate() + atalho.dias);
                    ini.value = fmt(alvo);
                    fim.value = fmt(atalho.dias === 0 ? alvo : hoje);
                }

                formFiltro.querySelector('button[type="submit"]').click();
            });
        });
    }

    // ============================================================
    // 13. INICIALIZAÇÃO
    // ============================================================
    async function init() {
        // Só roda se estiver na página de relatório
        if (!document.querySelector('#tabela-vendas-dia, #tabela-vendas-mes, #tabela-top-produtos')) {
            return;
        }

        try {
            await carregarChartJS();
        } catch (e) {
            console.warn('Chart.js não carregou:', e.message);
            return;
        }

        injetarCanvas();
        desenharGraficoDia();
        desenharGraficoMes();
        desenharGraficoTop();
        ativarBotoesTipo();
        adicionarBotoesExportar();
        atualizarResumo();
        adicionarFiltrosRapidos();

        // Redesenha ao trocar de tema
        const observer = new MutationObserver(() => {
            Object.keys(graficos).forEach(id => {
                const g = graficos[id];
                if (!g) return;
                g.options.scales.x.ticks.color = corTexto();
                g.options.scales.y.ticks.color = corTexto();
                g.options.scales.x.grid.color  = corGrade();
                g.options.scales.y.grid.color  = corGrade();
                if (g.options.plugins.legend?.labels) {
                    g.options.plugins.legend.labels.color = corTexto();
                }
                g.update();
            });
        });
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-tema']
        });

        console.log('📊 Relatórios prontos!');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();