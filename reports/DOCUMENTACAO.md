# DS Backgamom Credits - Documentação Completa

**Versão:** 2.0.0  
**Status:** ✅ IMPLEMENTADO E TESTADO  
**Última atualização:** 01/12/2025  
**Compatibilidade:** WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+

Sistema completo de créditos para a plataforma Backgamom Brasil com integração ao gateway de pagamento Asaas e suporte completo ao HPOS (High-Performance Order Storage).

## 📋 Descrição

Sistema de moeda virtual (créditos) que substitui o TeraWallet, oferecendo integração nativa com o gateway de pagamento Asaas. Gerencia toda a economia interna da plataforma, incluindo depósitos, saques e transações com logs completos de auditoria.

## 🚀 Funcionalidades Principais

### Sistema de Créditos
- **Carteira Virtual**: Sistema próprio de créditos com saldo em tempo real
- **Transações Seguras**: Histórico completo de movimentações com auditoria
- **Logs Automáticos**: Registro de todas as transações (depósitos, saques, adições manuais)
- **API Completa**: Funções para integração com outros plugins

### Gateway de Pagamento Asaas
- **Integração Nativa**: Gateway personalizado para WooCommerce
- **Múltiplas Formas**: PIX, Cartão de Crédito
- **Webhooks Seguros**: Confirmação automática com token de segurança
- **Ambiente Sandbox**: Testes seguros em desenvolvimento
- **CPF Inteligente**: Captura automática e validação para clientes brasileiros

### Sistema de Saques
- **Solicitações Simplificadas**: Interface intuitiva para pedidos de saque
- **Aprovação Manual**: Controle administrativo completo
- **Notificações WhatsApp**: Automáticas para aprovações/rejeições
- **Histórico Completo**: Auditoria de todas as operações
- **Múltiplos Métodos**: PIX (Brasil) e Wise (Internacional)

### Auto-Complete de Pedidos
- **Processamento Automático**: Pedidos de créditos marcados como concluídos
- **Notificações Automáticas**: WhatsApp para usuários e administradores
- **Integração WooCommerce**: Compatível com HPOS
- **Prevenção Duplicação**: Meta `_dsbc_credits_awarded` evita processamento duplo

## 🔧 Componentes Técnicos

### Classes Principais
- `DS_Credit_Manager` - Gerenciamento de créditos e transações
- `DS_Asaas_Gateway` - Gateway de pagamento Asaas
- `DS_Asaas_API_Client` - Cliente da API Asaas
- `DS_Webhook_Handler` - Processamento de webhooks
- `DS_Checkout_Manager` - Gestão do checkout (CPF, auto-preenchimento)
- `DS_Simple_Withdrawals` - Interface simplificada de saques
- `DS_Admin_Dashboard` - Dashboard administrativo
- `DS_Admin_Reports` - Sistema de relatórios
- `DS_Admin_History` - Histórico de transações com paginação
- `DS_Admin_Lookup` - Consulta de usuários com modais AJAX
- `DS_Admin_Withdrawals` - Gerenciamento de saques

### API de Integração

#### Consultar Saldo
```php
$balance = dsbc_get_user_balance($user_id);
```

#### Adicionar Créditos
```php
$success = dsbc_add_credits($user_id, $amount, $reason);
```

#### Deduzir Créditos
```php
$success = dsbc_deduct_credits($user_id, $amount, $reason);
```

#### Verificar Saldo Suficiente
```php
$has_balance = dsbc_has_sufficient_balance($user_id, $amount);
```

#### Processar Saque
```php
$success = dsbc_process_withdrawal($user_id, $amount, $method, $notes);
```

### Shortcodes Disponíveis

#### Exibir Saldo
```
[ds_credit_balance]
```

#### Dashboard Completo
```
[ds_credit_dashboard]
```

#### Formulário de Saque
```
[ds_withdrawal_form]
```
Exibe formulário completo para solicitação de saques com:
- Validação de saldo mínimo
- Seleção de método (PIX/Wise)
- Validação por país (PIX apenas Brasil)
- Processamento via AJAX

## 💳 Gateway Asaas

### Configurações
- **API Key**: Chave de acesso (sandbox/produção)
- **Webhook URL**: Endpoint para confirmações
- **Formas de Pagamento**: PIX, Cartão de Crédito
- **Token de Segurança**: Proteção contra requisições não autorizadas

### Fluxo de Pagamento
1. **Produto de Crédito**: Cliente adiciona ao carrinho
2. **Checkout**: Seleção do gateway Asaas com CPF automático
3. **Processamento**: Cobrança criada na API Asaas
4. **Confirmação**: Webhook confirma pagamento
5. **Créditos**: Adicionados automaticamente à carteira
6. **Log**: Registrado como 'deposit' na tabela de logs
7. **Notificação**: WhatsApp enviado ao cliente

### Webhooks Suportados
- `PAYMENT_CONFIRMED` - Pagamento confirmado
- `PAYMENT_RECEIVED` - Pagamento recebido
- `PAYMENT_OVERDUE` - Pagamento em atraso
- `PAYMENT_REFUNDED` - Pagamento estornado

### Configuração do Webhook
**URL**: `https://seusite.com/wp-json/ds-backgamom-credits/v1/asaas-webhook`
**Header**: `asaas-access-token` (usar token gerado no plugin)

## 💰 Sistema de Créditos

### Estrutura de Dados
```php
// Meta do usuário
$balance = get_user_meta($user_id, '_dsbc_credit_balance', true);

// Produtos com créditos
$credits = get_post_meta($product_id, '_dsbc_credits_amount', true);
```

### Sistema de Logs
O plugin mantém um log completo de todas as transações na tabela `wp_dsbc_credit_logs`:

```sql
CREATE TABLE wp_dsbc_credit_logs (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) NOT NULL,
    amount int(11) NOT NULL,
    type varchar(50) NOT NULL,
    observation text,
    admin_id int(11),
    admin_name varchar(255),
    old_balance int(11) NOT NULL DEFAULT 0,
    new_balance int(11) NOT NULL DEFAULT 0,
    created_at datetime NOT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY admin_id (admin_id),
    KEY created_at (created_at)
);
```

### Tipos de Transação
- `manual_addition` - Adição manual pelo administrador
- `deposit` - Depósito via pagamento (automático)
- `withdrawal` - Saque processado
- `deduction` - Dedução por compra/inscrição
- `refund` - Estorno/reembolso

## 🏦 Sistema de Saques

### Fluxo de Saque
1. **Solicitação**: Usuário solicita via formulário `[ds_withdrawal_form]`
2. **Validação**: Verificação de saldo disponível e dados do método
3. **Pendência**: Saque fica pendente de aprovação
4. **Análise**: Administrador aprova/rejeita via painel
5. **Processamento**: Créditos deduzidos se aprovado
6. **Notificação**: WhatsApp enviado ao usuário

### Estados do Saque
- `pending` - Aguardando aprovação
- `approved` - Aprovado e processado
- `rejected` - Rejeitado pelo administrador
- `cancelled` - Cancelado pelo usuário

### Métodos de Saque
- **PIX**: Apenas para usuários do Brasil (campo ACF `user_pix`)
- **Wise**: Para usuários internacionais (campo ACF `user_wise`)

## 📱 Notificações WhatsApp

### Integração com WhatsApp Connector
O plugin utiliza a classe `WhatsApp_Connector` para envio de notificações automáticas.

### Depósito de Créditos
Utiliza template 'deposit' com variáveis:
- `{Nome_Usuario}` - Nome do usuário
- `{quantia_creditos}` - Quantidade adicionada
- `{saldo_atual}` - Saldo atual após depósito

### Saque Processado
Templates específicos:
- `withdrawal_approved` - Saque aprovado
- `withdrawal_rejected` - Saque rejeitado

### Configuração de Telefone
O sistema busca o telefone do usuário em:
1. Campo ACF `user_whatsapp`
2. Meta `billing_phone` (WooCommerce)
3. Formatação automática para padrão brasileiro (+55)

## 🔗 Integração com WooCommerce

### Produtos de Créditos
- **Produtos Virtuais**: Configurados para adicionar créditos
- **Meta Personalizada**: `_dsbc_credits_amount` define quantidade
- **Auto-Complete**: Pedidos marcados como concluídos automaticamente
- **Compatibilidade HPOS**: Suporte ao novo sistema de pedidos

### Checkout Inteligente
- **CPF Automático**: Campo obrigatório para Brasil com auto-preenchimento
- **Validação**: Formato e obrigatoriedade por país
- **Salvamento**: CPF salvo no perfil do usuário
- **Máscaras**: JavaScript para formatação automática

### Hooks Utilizados
- `woocommerce_order_status_completed` - Adicionar créditos
- `woocommerce_payment_complete` - Processar pagamento
- `woocommerce_billing_fields` - Campo CPF
- `woocommerce_checkout_posted_data` - Auto-preenchimento

## ⚙️ Painel Administrativo

### Dashboard Principal
- **Estatísticas em Tempo Real**: Créditos ativos, depósitos, saques
- **Status do Sistema**: Verificação de dependências
- **Ações Rápidas**: Links para funcionalidades principais

### Consultar Créditos (`?page=ds-credits-lookup`)
- **Busca Avançada**: Por nome, email, usuário
- **Ordenação**: Por créditos, nome, data de registro
- **Ações AJAX**: Modais para adicionar créditos, ver histórico, processar saques
- **Interface Responsiva**: Botões com ícones e tooltips

### Histórico Completo (`?page=ds-credits-history`)
- **Filtros Avançados**: Por tipo, período, usuário
- **Paginação**: 25 registros por página
- **Visualização Detalhada**: Saldos anterior/posterior, administrador responsável
- **Cores por Tipo**: Identificação visual dos tipos de transação

### Relatórios (`?page=ds-credits-reports`)
- **Visão Geral**: Resumo executivo com métricas principais
- **Vendas Detalhadas**: Relatório diário de créditos vendidos
- **Top Usuários**: Ranking por saldo de créditos

### Gerenciamento de Saques (`?page=ds-credits-withdrawals`)
- **Lista de Solicitações**: Todas as solicitações pendentes
- **Ações em Massa**: Aprovar/rejeitar múltiplas solicitações
- **Detalhes Completos**: Informações do usuário e método de pagamento

## 🛠️ Desenvolvimento

### Estrutura de Arquivos
```
ds-backgamom-credits/
├── ds-backgamom-credits.php (arquivo principal)
├── README.md
├── DOCUMENTACAO.md
└── includes/
    ├── admin/
    │   ├── class-ds-admin-ajax.php
    │   ├── class-ds-admin-base.php
    │   ├── class-ds-admin-dashboard.php
    │   ├── class-ds-admin-history.php
    │   ├── class-ds-admin-lookup.php
    │   ├── class-ds-admin-reports.php
    │   └── class-ds-admin-withdrawals.php
    ├── class-ds-admin-settings.php
    ├── class-ds-asaas-api-client.php
    ├── class-ds-asaas-gateway.php
    ├── class-ds-checkout-manager.php
    ├── class-ds-credit-manager.php
    ├── class-ds-simple-withdrawals.php
    ├── class-ds-webhook-handler.php
    └── class-ds-withdrawal-handler.php
```

### Hooks Disponíveis
- `dsbc_plugin_loaded` - Plugin carregado e inicializado
- `woocommerce_order_status_completed` - Concede créditos ao completar pedido
- `woocommerce_payment_complete` - Concede créditos ao confirmar pagamento

### Filtros Disponíveis
- `dsbc_minimum_withdrawal` - Valor mínimo de saque
- `dsbc_withdrawal_fee` - Taxa de saque
- `dsbc_credit_product_types` - Tipos de produto que geram créditos

## 🔒 Segurança

### Validações
- **Saldo Suficiente**: Verificação antes de deduções
- **Nonces**: Proteção CSRF em formulários AJAX
- **Sanitização**: Dados limpos antes do armazenamento
- **Permissões**: Verificação de capacidades do usuário
- **Webhook Seguro**: Token de validação para requisições Asaas

### Auditoria
- **Log Completo**: Histórico de todas as transações
- **Rastreabilidade**: Origem e responsável por cada operação
- **Integridade**: Saldos anterior e posterior registrados
- **Timestamps**: Data/hora precisa de cada movimentação

## 📊 Métricas e Relatórios

### Resumo Executivo
- **Créditos Ativos**: Total em circulação
- **Total Depositado**: Soma de todos os depósitos
- **Depósitos (30 dias)**: Movimentação recente
- **Total Sacado**: Valor total de saques processados

### Relatórios Detalhados
- **Vendas por Data**: Créditos vendidos diariamente
- **Top Usuários**: Ranking por saldo
- **Movimentação**: Histórico filtrado por tipo e período

## 📝 Changelog

### v2.0.0 (01/12/2025)
- ✅ Sistema completo de créditos implementado
- ✅ Gateway Asaas integrado com CPF automático
- ✅ Sistema de saques com aprovação e WhatsApp
- ✅ Logs completos de auditoria
- ✅ Painel administrativo com AJAX
- ✅ Relatórios em tempo real
- ✅ Histórico com paginação
- ✅ Compatibilidade HPOS
- ✅ API completa para integração
- ✅ Webhook seguro com token

## 🔧 Configuração Rápida

### 1. Ativação
- Ative o plugin no WordPress
- Tabelas são criadas automaticamente

### 2. Gateway Asaas
- Configure API Key em WooCommerce > Pagamentos
- Configure webhook no painel Asaas
- Use token gerado no plugin

### 3. Produtos
- Crie produtos virtuais
- Configure quantidade de créditos no campo personalizado
- Publique os produtos

### 4. Saques (Opcional)
- Configure formulário Gravity Forms
- Defina valor mínimo de saque
- Configure campos ACF para PIX/Wise

## 👨💻 Desenvolvedor

**DSantos Info**  
Site: [dsantosinfo.com.br](https://dsantosinfo.com.br)  
Suporte: Através do painel administrativo

## 📄 Licença

GPL v2 or later - Licença livre para uso e modificação

---

**Sistema totalmente funcional e testado em produção!** ✅