/* ============================================================
   MÁSCARAS DE INPUT - Padaria do Gil
   ============================================================ */
(function () {
    'use strict';

    // --------- MÁSCARA: Telefone (fixo e celular) ---------
    function aplicarMascaraTelefone(input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);

            if (v.length > 10) {
                v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            } else if (v.length > 6) {
                v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
            } else if (v.length > 2) {
                v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
            } else if (v.length > 0) {
                v = v.replace(/^(\d{0,2})$/, '($1');
            }
            this.value = v;
        });
    }

    // --------- MÁSCARA: CPF ---------
    function aplicarMascaraCPF(input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 11);
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            this.value = v;
        });
    }

    // --------- MÁSCARA: CEP ---------
    function aplicarMascaraCEP(input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '').slice(0, 8);
            v = v.replace(/^(\d{5})(\d)/, '$1-$2');
            this.value = v;
        });
    }

    // --------- MÁSCARA: Moeda (R$) ---------
    function aplicarMascaraMoeda(input) {
        input.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (!v) { this.value = ''; return; }
            const num = Number(v) / 100;
            this.value = num.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        });
    }

    // --------- Aplicação automática por atributo ---------
    function init() {
        // Telefone: qualquer input com data-mask="telefone" ou name="telefone"
        document.querySelectorAll('input[data-mask="telefone"], input[name="telefone"]')
            .forEach(aplicarMascaraTelefone);

        // CPF
        document.querySelectorAll('input[data-mask="cpf"], input[name="cpf"]')
            .forEach(aplicarMascaraCPF);

        // CEP
        document.querySelectorAll('input[data-mask="cep"], input[name="cep"]')
            .forEach(aplicarMascaraCEP);

        // Moeda
        document.querySelectorAll('input[data-mask="moeda"], input[name="limite_credito"], input[name="preco_venda"], input[name="preco_custo"]')
            .forEach(aplicarMascaraMoeda);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();