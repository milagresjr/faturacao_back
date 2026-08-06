<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;

trait DatabaseSetup
{
    protected function dropTabela(string $tabela): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement("DROP TABLE IF EXISTS {$tabela}");
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function criarTabelaEmpresas(): void
    {
        $this->dropTabela('empresas');
        DB::statement('CREATE TABLE empresas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            nif VARCHAR(255) NULL,
            telefone VARCHAR(255) NULL,
            morada TEXT NULL,
            website VARCHAR(255) NULL,
            regime_tributario VARCHAR(255) NULL,
            indicativo_fatura VARCHAR(10) NULL,
            logo VARCHAR(255) NULL,
            slogan VARCHAR(255) NULL,
            pais VARCHAR(255) NULL,
            provincia VARCHAR(255) NULL,
            municipio VARCHAR(255) NULL,
            bairro VARCHAR(255) NULL,
            codigo_postal VARCHAR(255) NULL,
            status VARCHAR(255) DEFAULT \'ativo\',
            must_fill_data_empresa TINYINT(1) DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPerfis(): void
    {
        $this->dropTabela('perfil_permissao');
        $this->dropTabela('perfis');
        DB::statement('CREATE TABLE perfis (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPermissoes(): void
    {
        $this->dropTabela('perfil_permissao');
        $this->dropTabela('permissoes');
        DB::statement('CREATE TABLE permissoes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NULL,
            modulo_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPerfilPermissao(): void
    {
        $this->dropTabela('perfil_permissao');
        DB::statement('CREATE TABLE perfil_permissao (
            perfil_id BIGINT UNSIGNED NOT NULL,
            permissao_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (perfil_id, permissao_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaUtilizadores(): void
    {
        $this->dropTabela('utilizadores');
        DB::statement('CREATE TABLE utilizadores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome_pessoal VARCHAR(255) NOT NULL,
            nome_de_utilizador VARCHAR(255) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            senha VARCHAR(255) NOT NULL,
            telefone VARCHAR(255) NULL,
            nivel_acesso VARCHAR(255) NOT NULL DEFAULT \'user\',
            remember_token TEXT NULL,
            estado TINYINT(1) DEFAULT 1,
            perfil_id BIGINT UNSIGNED NULL,
            empresa_id BIGINT UNSIGNED NULL,
            must_change_password TINYINT(1) DEFAULT 0,
            must_fill_data_empresa TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPersonalAccessTokens(): void
    {
        $this->dropTabela('personal_access_tokens');
        DB::statement('CREATE TABLE personal_access_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tokenable_type VARCHAR(255) NOT NULL,
            tokenable_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            abilities TEXT NULL,
            last_used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaConfiguracoesFatura(): void
    {
        $this->dropTabela('configuracoes_fatura');
        DB::statement('CREATE TABLE configuracoes_fatura (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            empresa_id BIGINT UNSIGNED NOT NULL,
            nome_empresa TINYINT(1) DEFAULT 1,
            nif TINYINT(1) DEFAULT 1,
            email TINYINT(1) DEFAULT 1,
            telefone TINYINT(1) DEFAULT 1,
            endereco TINYINT(1) DEFAULT 1,
            website TINYINT(1) DEFAULT 1,
            endereco_cliente TINYINT(1) DEFAULT 1,
            logo VARCHAR(255) NULL,
            template VARCHAR(20) DEFAULT \'classic\',
            rodape TEXT NULL,
            mostrar_utilizador TINYINT(1) DEFAULT 1,
            mostrar_logo TINYINT(1) DEFAULT 1,
            mostrar_nif TINYINT(1) DEFAULT 1,
            mostrar_rodape TINYINT(1) DEFAULT 1,
            num_via INT DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaTipoTaxaIva(): void
    {
        $this->dropTabela('tipos_taxa_iva');
        DB::statement('CREATE TABLE tipos_taxa_iva (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            codigo VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            taxa DECIMAL(5,2) NOT NULL DEFAULT 0,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaSeries(): void
    {
        $this->dropTabela('series');
        DB::statement('CREATE TABLE series (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            prefixo VARCHAR(20) NOT NULL,
            ano VARCHAR(4) NOT NULL,
            tipo_documento VARCHAR(255) NOT NULL,
            sequencia_atual INT DEFAULT 0,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            padrao TINYINT(1) DEFAULT 0,
            ativo TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaProdutos(): void
    {
        $this->dropTabela('lotes_produto');
        $this->dropTabela('movimentos_stock');
        $this->dropTabela('stocks');
        $this->dropTabela('itens_documento');
        $this->dropTabela('itens_documento_compra');
        $this->dropTabela('produtos');
        DB::statement('CREATE TABLE produtos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao TEXT NULL,
            preco_custo DECIMAL(12,2) DEFAULT 0,
            preco_venda DECIMAL(12,2) DEFAULT 0,
            preco_final DECIMAL(12,2) DEFAULT 0,
            margem_lucro DECIMAL(12,2) DEFAULT 0,
            valor_iva DECIMAL(12,2) DEFAULT 0,
            stock_min INT DEFAULT 0,
            stock_max INT DEFAULT 0,
            stock_ideial INT DEFAULT 0,
            stock_atual DECIMAL(12,2) DEFAULT 0,
            stock DECIMAL(12,2) DEFAULT 0,
            controla_validade TINYINT(1) DEFAULT 0,
            movimenta_stock TINYINT(1) DEFAULT 1,
            codigo_produto VARCHAR(255) NULL,
            codigo_barra VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NULL,
            armazem_id BIGINT UNSIGNED NULL,
            marca_id BIGINT UNSIGNED NULL,
            categoria_id BIGINT UNSIGNED NULL,
            tipo_id BIGINT UNSIGNED NULL,
            sub_categoria_id BIGINT UNSIGNED NULL,
            fornecedor_id BIGINT UNSIGNED NULL,
            utilizador_id BIGINT UNSIGNED NULL,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaArmazens(): void
    {
        $this->dropTabela('movimentos_stock');
        $this->dropTabela('stocks');
        $this->dropTabela('caixas');
        $this->dropTabela('armazens');
        DB::statement('CREATE TABLE armazens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            endereco VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            filial_id BIGINT UNSIGNED NULL,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            predefinido TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaClientes(): void
    {
        $this->dropTabela('clientes');
        DB::statement('CREATE TABLE clientes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            nif VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            telefone VARCHAR(255) NULL,
            endereco TEXT NULL,
            data_nasc DATE NULL,
            numero_bi VARCHAR(255) NULL,
            pais VARCHAR(255) DEFAULT \'AO\',
            telemovel VARCHAR(255) NULL,
            vencimento VARCHAR(255) DEFAULT \'A Pronto\',
            fatura_eletronica TINYINT(1) DEFAULT 0,
            website VARCHAR(255) NULL,
            observacoes TEXT NULL,
            faz_retencao TINYINT(1) DEFAULT 0,
            gestor_id BIGINT UNSIGNED NULL,
            grupo_preco_id BIGINT UNSIGNED NULL,
            tipo_cliente_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            estado TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaFornecedores(): void
    {
        $this->dropTabela('fornecedores');
        DB::statement('CREATE TABLE fornecedores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            telefone VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            endereco TEXT NULL,
            nif VARCHAR(255) NULL,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            estado TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaStocks(): void
    {
        $this->dropTabela('movimentos_stock');
        $this->dropTabela('alertas_stock');
        $this->dropTabela('stocks');
        DB::statement('CREATE TABLE stocks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            produto_id BIGINT UNSIGNED NOT NULL,
            armazem_id BIGINT UNSIGNED NOT NULL,
            empresa_id BIGINT UNSIGNED NULL,
            stock_atual DECIMAL(12,2) DEFAULT 0,
            stock_min INT DEFAULT 0,
            stock_max INT DEFAULT 0,
            stock_ideal INT DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaInfoGuia(): void
    {
        $this->dropTabela('info_guias');
        DB::statement('CREATE TABLE info_guias (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NULL,
            transportadora VARCHAR(255) NULL,
            data_transporte DATE NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaDocumentos(): void
    {
        $this->dropTabela('documento_relacoes');
        $this->dropTabela('meios_pagamento_documento');
        $this->dropTabela('impostos_documento');
        $this->dropTabela('itens_documento');
        $this->dropTabela('documentos');
        DB::statement('CREATE TABLE documentos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo_nome VARCHAR(255) NOT NULL,
            tipo_sigla VARCHAR(10) NOT NULL,
            tipo_cor VARCHAR(20) NULL,
            armazem_id BIGINT UNSIGNED NULL,
            estado_documento VARCHAR(50) DEFAULT \'rascunho\',
            estado_pagamento VARCHAR(50) DEFAULT \'nao_pago\',
            estado_vencimento VARCHAR(50) DEFAULT \'no_prazo\',
            num_fatura VARCHAR(255) NULL,
            via VARCHAR(255) DEFAULT \'original\',
            vezes_impresso INT DEFAULT 0,
            empresa_id BIGINT UNSIGNED NULL,
            empresa_logo TEXT NULL,
            empresa_nome VARCHAR(255) NULL,
            empresa_nif VARCHAR(255) NULL,
            empresa_telefone VARCHAR(255) NULL,
            empresa_email VARCHAR(255) NULL,
            empresa_endereco TEXT NULL,
            cliente_id BIGINT UNSIGNED NULL,
            cliente_nome VARCHAR(255) NULL,
            cliente_nif VARCHAR(255) NULL,
            cliente_telefone VARCHAR(255) NULL,
            cliente_email VARCHAR(255) NULL,
            cliente_endereco TEXT NULL,
            caixa VARCHAR(255) NULL,
            data_emissao DATE NULL,
            data_vencimento DATE NULL,
            forma_pagamento VARCHAR(255) NULL,
            movimenta_stock TINYINT(1) DEFAULT 1,
            descricao_iva TEXT NULL,
            taxa_iva DECIMAL(20,2) DEFAULT 0,
            valor_iva DECIMAL(20,2) DEFAULT 0,
            retencao DECIMAL(20,2) DEFAULT 0,
            desconto_tipo VARCHAR(20) NULL,
            desconto_total DECIMAL(20,2) DEFAULT 0,
            valor_transporte DECIMAL(20,2) DEFAULT 0,
            total_sem_desconto DECIMAL(20,2) DEFAULT 0,
            total_impostos DECIMAL(20,2) DEFAULT 0,
            total_geral DECIMAL(20,2) DEFAULT 0,
            troco DECIMAL(20,2) DEFAULT 0,
            motivo_emissao_nota_credito TEXT NULL,
            hash TEXT NULL,
            estado VARCHAR(50) DEFAULT \'rascunho\',
            utilizador_id BIGINT UNSIGNED NULL,
            utilizador VARCHAR(255) NULL,
            local_entrega TEXT NULL,
            data_recepcao DATE NULL,
            observacoes TEXT NULL,
            paga TINYINT(1) DEFAULT 0,
            valor_pago DECIMAL(12,2) DEFAULT 0,
            tipo_documento VARCHAR(255) NULL,
            info_guia_id BIGINT UNSIGNED NULL,
            serie_id BIGINT UNSIGNED NULL,
            documento_origem_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaItensDocumento(): void
    {
        $this->dropTabela('itens_documento');
        DB::statement('CREATE TABLE itens_documento (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NOT NULL,
            produto_id BIGINT UNSIGNED NULL,
            produto_nome VARCHAR(255) NULL,
            produto_codigo VARCHAR(255) NULL,
            preco_unitario DECIMAL(20,2) DEFAULT 0,
            descricao TEXT NULL,
            quantidade INT DEFAULT 0,
            desconto_percent DECIMAL(5,2) DEFAULT 0,
            desconto_fixo DECIMAL(20,2) DEFAULT 0,
            iva_percent DECIMAL(5,2) DEFAULT 0,
            total_sem_desconto DECIMAL(10,2) NULL,
            total DECIMAL(20,2) DEFAULT 0,
            codigo_iva VARCHAR(255) NULL,
            motivo_isencao VARCHAR(255) NULL,
            imposto_taxa_id BIGINT UNSIGNED NULL,
            motivo_isencao_id BIGINT UNSIGNED NULL,
            tipo_id BIGINT UNSIGNED NULL,
            codigo_lote VARCHAR(255) NULL,
            data_validade DATE NULL,
            detalhes_lote JSON NULL,
            lote_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaMeiosPagamentoDocumento(): void
    {
        $this->dropTabela('meios_pagamento_documento');
        DB::statement('CREATE TABLE meios_pagamento_documento (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NOT NULL,
            descricao VARCHAR(255) NULL,
            valor DECIMAL(15,2) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaImpostosDocumento(): void
    {
        $this->dropTabela('impostos_documento');
        DB::statement('CREATE TABLE impostos_documento (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NOT NULL,
            taxa DECIMAL(5,2) DEFAULT 0,
            codigo VARCHAR(20) NULL,
            isento TINYINT(1) DEFAULT 0,
            motivo_isencao VARCHAR(255) NULL,
            incidencia DECIMAL(10,2) DEFAULT 0,
            base DECIMAL(10,2) DEFAULT 0,
            imposto DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaDocumentoRelacoes(): void
    {
        $this->dropTabela('documento_relacoes');
        DB::statement('CREATE TABLE documento_relacoes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NOT NULL,
            documento_relacionado_id BIGINT UNSIGNED NOT NULL,
            tipo_relacao VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaMovimentosStock(): void
    {
        $this->dropTabela('movimentos_stock');
        DB::statement('CREATE TABLE movimentos_stock (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stock_id BIGINT UNSIGNED NULL,
            produto_id BIGINT UNSIGNED NULL,
            lote_id VARCHAR(255) NULL,
            codigo_lote VARCHAR(100) NULL,
            data_validade_lote DATE NULL,
            detalhes_lote JSON NULL,
            quantidade DECIMAL(12,3) DEFAULT 0,
            operacao VARCHAR(50) NOT NULL,
            observacao TEXT NULL,
            armazem_id BIGINT UNSIGNED NULL,
            armazem_origem_id BIGINT UNSIGNED NULL,
            armazem_destino_id BIGINT UNSIGNED NULL,
            utilizador_id BIGINT UNSIGNED NULL,
            origem_movimento VARCHAR(255) NULL,
            documento_relacionado_id BIGINT UNSIGNED NULL,
            documento_id BIGINT UNSIGNED NULL,
            documento_type VARCHAR(255) NULL,
            empresa_id BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaLotesProduto(): void
    {
        $this->dropTabela('notificacoes_validade');
        $this->dropTabela('lotes_produto');
        DB::statement('CREATE TABLE lotes_produto (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            produto_id BIGINT UNSIGNED NOT NULL,
            armazem_id BIGINT UNSIGNED NULL,
            codigo_lote VARCHAR(255) NOT NULL,
            data_fabricacao DATE NULL,
            data_validade DATE NOT NULL,
            qtd_atual DECIMAL(12,3) DEFAULT 0,
            qtd_inicial DECIMAL(12,3) DEFAULT 0,
            quantidade_actual DECIMAL(12,3) DEFAULT 0,
            quantidade_inicial DECIMAL(12,3) DEFAULT 0,
            status VARCHAR(50) DEFAULT \'activo\',
            data_entrada DATE NULL,
            data_consumo DATE NULL,
            preco_custo DECIMAL(12,2) NULL,
            observacao TEXT NULL,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaDocumentosCompra(): void
    {
        $this->dropTabela('pagamentos_documento_compra');
        $this->dropTabela('other_itens_documento_compra');
        $this->dropTabela('impostos_documento_compra');
        $this->dropTabela('itens_documento_compra');
        $this->dropTabela('documentos_compra');
        DB::statement('CREATE TABLE documentos_compra (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo_nome VARCHAR(255) NULL,
            tipo_sigla VARCHAR(255) NULL,
            tipo_cor VARCHAR(255) NULL,
            num_fatura VARCHAR(255) NULL,
            via VARCHAR(255) DEFAULT \'original\',
            vezes_impresso INT DEFAULT 0,
            armazem_id INT NULL,
            empresa_id VARCHAR(255) NULL,
            empresa_nome VARCHAR(255) NULL,
            empresa_nif VARCHAR(255) NULL,
            empresa_telefone VARCHAR(255) NULL,
            empresa_email VARCHAR(255) NULL,
            empresa_endereco TEXT NULL,
            fornecedor_id VARCHAR(255) NULL,
            fornecedor_nome VARCHAR(255) NULL,
            fornecedor_nif VARCHAR(255) NULL,
            fornecedor_telefone VARCHAR(255) NULL,
            fornecedor_email VARCHAR(255) NULL,
            fornecedor_endereco TEXT NULL,
            data_fatura DATE NULL,
            data_vencimento DATE NULL,
            obs_pagamento TEXT NULL,
            desconto_total DECIMAL(20,2) DEFAULT 0,
            taxa_iva DECIMAL(20,2) DEFAULT 0,
            valor_fatura DECIMAL(20,2) DEFAULT 0,
            retencao DECIMAL(20,2) DEFAULT 0,
            total_sem_desconto DECIMAL(20,2) DEFAULT 0,
            total_impostos DECIMAL(20,2) DEFAULT 0,
            total_geral DECIMAL(20,2) DEFAULT 0,
            troco DECIMAL(20,2) DEFAULT 0,
            local_entrega TEXT NULL,
            data_recepcao DATE NULL,
            observacoes TEXT NULL,
            paga TINYINT(1) DEFAULT 0,
            valor_pago DECIMAL(20,2) DEFAULT 0,
            hash TEXT NULL,
            utilizador_id VARCHAR(255) NULL,
            utilizador VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaItensDocumentoCompra(): void
    {
        $this->dropTabela('itens_documento_compra');
        DB::statement('CREATE TABLE itens_documento_compra (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_compra_id BIGINT UNSIGNED NOT NULL,
            produto_id BIGINT UNSIGNED NULL,
            lote_id BIGINT UNSIGNED NULL,
            lote VARCHAR(255) NULL,
            codigo_lote VARCHAR(255) NULL,
            lote_codigo_barras VARCHAR(255) NULL,
            lote_data_validade DATE NULL,
            produto_nome VARCHAR(255) NOT NULL,
            produto_codigo VARCHAR(255) NULL,
            preco_custo DECIMAL(20,2) NOT NULL DEFAULT 0,
            descricao TEXT NULL,
            quantidade INT NULL,
            desconto_percent DECIMAL(5,2) DEFAULT 0,
            desconto_fixo DECIMAL(20,2) DEFAULT 0,
            iva_percent DECIMAL(5,2) DEFAULT 0,
            total_sem_desconto DECIMAL(20,2) DEFAULT 0,
            total_sem_imposto DECIMAL(20,2) DEFAULT 0,
            valor_imposto DECIMAL(20,2) DEFAULT 0,
            total DECIMAL(20,2) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaImpostosDocumentoCompra(): void
    {
        $this->dropTabela('impostos_documento_compra');
        DB::statement('CREATE TABLE impostos_documento_compra (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_compra_id BIGINT UNSIGNED NOT NULL,
            taxa DECIMAL(5,2) DEFAULT 0,
            codigo VARCHAR(20) NULL,
            isento TINYINT(1) DEFAULT 0,
            motivo_isencao VARCHAR(255) NULL,
            incidencia DECIMAL(10,2) DEFAULT 0,
            base DECIMAL(10,2) DEFAULT 0,
            imposto DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaOtherItensDocumentoCompra(): void
    {
        $this->dropTabela('other_itens_documento_compra');
        DB::statement('CREATE TABLE other_itens_documento_compra (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_compra_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(255) NOT NULL,
            preco_custo DECIMAL(20,2) NOT NULL DEFAULT 0,
            descricao TEXT NULL,
            quantidade INT NULL,
            desconto_percent DECIMAL(5,2) DEFAULT 0,
            desconto_fixo DECIMAL(20,2) DEFAULT 0,
            iva_percent DECIMAL(5,2) DEFAULT 0,
            total DECIMAL(20,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPagamentosDocumentoCompra(): void
    {
        $this->dropTabela('pagamentos_documento_compra');
        DB::statement('CREATE TABLE pagamentos_documento_compra (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_compra_id BIGINT UNSIGNED NOT NULL,
            observacao TEXT NULL,
            data_pagamento DATE NULL,
            valor DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaDocumentosInterno(): void
    {
        $this->dropTabela('itens_documentos_interno');
        $this->dropTabela('documentos_interno');
        DB::statement('CREATE TABLE documentos_interno (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo_sigla VARCHAR(10) NOT NULL,
            num_documento VARCHAR(255) NULL,
            armazem_origem_id BIGINT UNSIGNED NULL,
            armazem_destino_id BIGINT UNSIGNED NULL,
            empresa_id BIGINT UNSIGNED NULL,
            utilizador_id BIGINT UNSIGNED NULL,
            observacoes TEXT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaItensDocumentoInterno(): void
    {
        $this->dropTabela('itens_documentos_interno');
        DB::statement('CREATE TABLE itens_documentos_interno (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_interno_id BIGINT UNSIGNED NOT NULL,
            produto_id BIGINT UNSIGNED NULL,
            produto_nome VARCHAR(255) NULL,
            quantidade DECIMAL(12,3) DEFAULT 0,
            codigo_lote VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaBancos(): void
    {
        $this->dropTabela('contas');
        $this->dropTabela('bancos');
        DB::statement('CREATE TABLE bancos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            codigo VARCHAR(10) NULL,
            descricao VARCHAR(255) NULL,
            sigla VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaContas(): void
    {
        $this->dropTabela('contas');
        DB::statement('CREATE TABLE contas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            banco_id BIGINT UNSIGNED NOT NULL,
            nome VARCHAR(255) NOT NULL,
            numero_conta VARCHAR(255) NULL,
            iban VARCHAR(255) NULL,
            empresa_id BIGINT UNSIGNED NOT NULL,
            estado TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaBancosDocumento(): void
    {
        $this->dropTabela('bancos_documento');
        DB::statement('CREATE TABLE bancos_documento (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            documento_id BIGINT UNSIGNED NOT NULL,
            sigla VARCHAR(255) NULL,
            descricao VARCHAR(255) NULL,
            numero_conta VARCHAR(255) NULL,
            iban VARCHAR(255) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaUnidades(): void
    {
        $this->dropTabela('unidades');
        DB::statement('CREATE TABLE unidades (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            sigla VARCHAR(10) NULL,
            casas_decimais INT DEFAULT 0,
            predefinida TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaMarcas(): void
    {
        $this->dropTabela('marcas');
        DB::statement('CREATE TABLE marcas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaCategoriaProduto(): void
    {
        $this->dropTabela('sub_categorias');
        $this->dropTabela('categorias');
        DB::statement('CREATE TABLE categorias (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaSubCategorias(): void
    {
        $this->dropTabela('sub_categorias');
        DB::statement('CREATE TABLE sub_categorias (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            categoria_id BIGINT UNSIGNED NOT NULL,
            empresa_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaMotivoIsencao(): void
    {
        $this->dropTabela('motivos_isencao');
        DB::statement('CREATE TABLE motivos_isencao (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            codigo VARCHAR(50) NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaTipoProduto(): void
    {
        $this->dropTabela('tipo_produtos');
        DB::statement('CREATE TABLE tipo_produtos (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            descricao VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaTipoStock(): void
    {
        $this->dropTabela('tipo_stock');
        DB::statement('CREATE TABLE tipo_stock (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(255) NOT NULL,
            sigla VARCHAR(255) NULL,
            motivo_isencao_id BIGINT UNSIGNED NULL,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaFiliais(): void
    {
        $this->dropTabela('filiais');
        DB::statement('CREATE TABLE filiais (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            telefone VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            endereco VARCHAR(255) NULL,
            nif VARCHAR(255) NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaCaixas(): void
    {
        $this->dropTabela('caixas');
        DB::statement('CREATE TABLE caixas (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            localizacao VARCHAR(255) NULL,
            tipo VARCHAR(50) DEFAULT \'fisico\',
            estado VARCHAR(50) DEFAULT \'fechado\',
            saldo_inicial DECIMAL(15,2) DEFAULT 0,
            saldo_atual DECIMAL(15,2) DEFAULT 0,
            armazem_id BIGINT UNSIGNED NOT NULL,
            usuario_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaAlertasStock(): void
    {
        $this->dropTabela('alertas_stock');
        DB::statement('CREATE TABLE alertas_stock (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stock_id BIGINT UNSIGNED NOT NULL,
            produto_id BIGINT UNSIGNED NOT NULL,
            armazem_id BIGINT UNSIGNED NOT NULL,
            stock_atual DECIMAL(12,2) DEFAULT 0,
            empresa_id BIGINT UNSIGNED NULL,
            sms_enviado TINYINT(1) DEFAULT 0,
            enviado_em TIMESTAMP NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaNotificacoesValidade(): void
    {
        $this->dropTabela('notificacoes_validade');
        DB::statement('CREATE TABLE notificacoes_validade (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            lote_id BIGINT UNSIGNED NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            nivel VARCHAR(50) NULL,
            mensagem TEXT NULL,
            dias_restantes INT DEFAULT 0,
            quantidade_afetada DECIMAL(12,3) DEFAULT 0,
            data_envio TIMESTAMP NULL,
            lida TINYINT(1) DEFAULT 0,
            data_leitura TIMESTAMP NULL,
            lida_por BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaTipoCliente(): void
    {
        $this->dropTabela('tipo_clientes');
        DB::statement('CREATE TABLE tipo_clientes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            estado TINYINT(1) DEFAULT 1,
            empresa_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            utilizador_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaModuloPermissao(): void
    {
        $this->dropTabela('modulo_permissoes');
        DB::statement('CREATE TABLE modulo_permissoes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelaPasswordResetsCustom(): void
    {
        $this->dropTabela('password_resets_custom');
        DB::statement('CREATE TABLE password_resets_custom (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            codigo VARCHAR(255) NOT NULL,
            utilizado TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    protected function criarTabelasBase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $this->criarTabelaEmpresas();
        $this->criarTabelaPerfis();
        $this->criarTabelaPermissoes();
        $this->criarTabelaPerfilPermissao();
        $this->criarTabelaUtilizadores();
        $this->criarTabelaPersonalAccessTokens();
        $this->criarTabelaPasswordResetsCustom();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function autenticarComoAdmin(): string
    {
        DB::table('empresas')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Empresa Teste',
            'email' => 'empresa@teste.com',
            'nif' => '123456789',
            'telefone' => '923456789',
            'morada' => 'Rua Teste, 123',
        ]);

        DB::table('perfis')->insertOrIgnore([
            'id' => 1,
            'nome' => 'Administrador',
            'estado' => 1,
            'empresa_id' => 1,
        ]);

        $utilizador = \App\Models\Utilizador::create([
            'nome_pessoal' => 'Admin Teste',
            'nome_de_utilizador' => 'admin.teste',
            'email' => 'admin' . uniqid() . '@teste.com',
            'senha' => bcrypt('password123'),
            'nivel_acesso' => 'admin',
            'estado' => 1,
            'perfil_id' => 1,
            'empresa_id' => 1,
        ]);

        $token = $utilizador->createToken('auth_token')->plainTextToken;
        $utilizador->remember_token = $token;
        $utilizador->save();

        return $token;
    }

    protected function headersComToken(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }
}
