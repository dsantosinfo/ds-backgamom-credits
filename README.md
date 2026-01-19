# DS Backgamom Credits

**Versão:** 2.0.0  
**Status:** ✅ IMPLEMENTADO E TESTADO  
**Última atualização:** 05/11/2025  
**Compatibilidade:** WordPress 5.0+, WooCommerce 5.0+, PHP 7.4+

Sistema completo de créditos para a plataforma Backgamom Brasil com integração ao gateway de pagamento Asaas e suporte completo ao HPOS (High-Performance Order Storage).

## 📋 Descrição

Sistema de moeda virtual (créditos) que substitui o TeraWallet, oferecendo integração nativa com o gateway de pagamento Asaas. Gerencia toda a economia interna da plataforma, incluindo depósitos, saques e transações.

## 🚀 Funcionalidades Principais

### Sistema de Créditos
- **Carteira Virtual**: Sistema próprio de créditos
- **Transações Seguras**: Histórico completo de movimentações
- **Saldo em Tempo Real**: Consulta instantânea de saldos
- **API Completa**: Funções para integração com outros plugins

### Gateway de Pagamento Asaas
- **Integração Nativa**: Gateway personalizado para WooCommerce
- **Múltiplas Formas**: PIX, Cartão, Boleto
- **Webhooks**: Confirmação automática de pagamentos
- **Ambiente Sandbox**: Testes seguros em desenvolvimento

### Sistema de Saques
- **Solicitações**: Interface para pedidos de saque
- **Aprovação Manual**: Controle administrativo
- **Notificações**: WhatsApp para aprovações/rejeições
- **Histórico Completo**: Auditoria de todas as operações

### Auto-Complete de Pedidos
- **Processamento Automático**: Pedidos de créditos marcados como concluídos
- **Notificações Automáticas**: WhatsApp para usuários e administradores
- **Integração WooCommerce**: Compatível com HPOS

## 🔧 Componentes Técnicos

### Classes Principais
- `DS_Credit_Manager` - Gerenciamento de créditos e transações
- `DS_Asaas_Gateway` - Gateway de pagamento Asaas
- `DS_Asaas_API_Client` - Cliente da API Asaas
- `DS_Webhook_Handler` - Processamento de webhooks
- `DS_Withdrawal_Handler` - Gestão completa de saques
- `DS_Simple_Withdrawals` - Interface simplificada de saques
- `DS_Admin_Dashboard` - Dashboard administrativo
- `DS_Admin_Reports` - Sistema de relatórios
- `DS_Admin_History` - Histórico de transações
- `DS_Admin_Lookup` - Consulta de usuários
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

#### Saldo Simples
```
[ds_credit_balance format="badge" show_label="true"]
```

#### Dashboard Completo Otimizado
```
[ds_credit_dashboard show_history="true" history_limit="5" show_stats="true"]
```
Funcionalidades do dashboard:
- Saldo destacado com design atrativo
- Estatísticas: Total Ganho, Total Gasto, Transações
- Histórico das últimas transações com AJAX
- Botões de ação (Comprar/Sacar)
- Design totalmente responsivo

#### Histórico Detalhado
```
[ds_credit_history limit="10" type="all" show_pagination="true"]
```
Recursos do histórico:
- Filtros por tipo de transação
- Carregamento AJAX de mais registros
- Detalhes completos com observações
- Badges coloridos por tipo

#### Estatísticas por Período
```
[ds_credit_stats period="30"]
```
Métricas disponíveis:
- Créditos recebidos no período
- Créditos gastos no período
- Número de transações
- Saldo líquido

#### Widget Compacto
```
[ds_credit_widget style="card" show_actions="true" show_last_transaction="false"]
```
Estilos disponíveis:
- `default` - Estilo padrão com borda
- `minimal` - Estilo minimalista
- `card` - Card com gradiente

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
- **Formas de Pagamento**: PIX, Cartão, Boleto
- **Taxas**: Configuráveis por método

### Fluxo de Pagamento
1. **Produto de Crédito**: Cliente adiciona ao carrinho
2. **Checkout**: Seleção do gateway Asaas
3. **Processamento**: Cobrança criada na API Asaas
4. **Confirmação**: Webhook confirma pagamento
5. **Créditos**: Adicionados automaticamente à carteira
6. **Notificação**: WhatsApp enviado ao cliente

### Webhooks Suportados
- `PAYMENT_CONFIRMED` - Pagamento confirmado
- `PAYMENT_RECEIVED` - Pagamento recebido
- `PAYMENT_OVERDUE` - Pagamento em atraso
- `PAYMENT_DELETED` - Pagamento cancelado

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
    PRIMARY KEY (id)
);
```

### Tipos de Transação
- `manual_addition` - Adição manual pelo administrador
- `withdrawal` - Saque processado
- `deposit` - Depósito via pagamento (automático)
- `deduction` - Dedução por compra/inscrição
- `refund` - Estorno/reembolso

### Histórico de Transações
```php
$transaction = [
    'id' => uniqid(),
    'type' => 'deposit',
    'amount' => 100,
    'balance_before' => 50,
    'balance_after' => 150,
    'reason' => 'Depósito via PIX',
    'date' => current_time('mysql'),
    'admin_id' => null,
    'order_id' => 123
];
```

## 🏦 Sistema de Saques

### Fluxo de Saque
1. **Solicitação**: Usuário solicita via formulário
2. **Validação**: Verificação de saldo disponível
3. **Pendência**: Saque fica pendente de aprovação
4. **Análise**: Administrador aprova/rejeita
5. **Processamento**: Créditos deduzidos se aprovado
6. **Notificação**: WhatsApp enviado ao usuário

### Estados do Saque
- `pending` - Aguardando aprovação
- `approved` - Aprovado e processado
- `rejected` - Rejeitado pelo administrador
- `cancelled` - Cancelado pelo usuário

### Interface Administrativa
- **Lista de Solicitações**: Todas as solicitações pendentes
- **Detalhes**: Informações completas do saque
- **Ações**: Aprovar/Rejeitar com observações
- **Histórico**: Log de todas as operações

## 📱 Notificações WhatsApp

### Integração com WhatsApp Connector
O plugin utiliza a classe `WhatsApp_Connector` para envio de notificações automáticas.

### Depósito de Créditos
Utiliza template 'deposit' com variáveis:
- `{Nome_Usuario}` - Nome do usuário
- `{quantia_creditos}` - Quantidade adicionada
- `{saldo_atual}` - Saldo atual após depósito

### Saque Processado
Notificação personalizada informando:
- Valor do saque processado
- Motivo/observações do saque

### Configuração de Telefone
O sistema busca o telefone do usuário em:
1. Campo ACF `user_whatsapp`
2. Meta `billing_phone` (WooCommerce)
3. Formatação automática para padrão brasileiro (+55)

## 🔗 Integração com WooCommerce

### Produtos de Crédito
- **Produtos Virtuais**: Configurados para adicionar créditos
- **Meta Personalizada**: `_dsbc_credit_amount` define quantidade
- **Auto-Complete**: Pedidos marcados como concluídos automaticamente
- **Compatibilidade HPOS**: Suporte ao novo sistema de pedidos

### Hooks Utilizados
- `woocommerce_order_status_completed` - Adicionar créditos
- `woocommerce_payment_complete` - Processar pagamento
- `woocommerce_order_status_cancelled` - Estornar créditos

## ⚙️ Configuração

### Configurações do Gateway
1. **Ativar Gateway**: WooCommerce > Configurações > Pagamentos
2. **API Key**: Inserir chave da API Asaas
3. **Ambiente**: Sandbox ou Produção
4. **Webhook**: Configurar URL de retorno
5. **Formas de Pagamento**: Ativar PIX/Cartão/Boleto

### Configurações de Produtos
1. **Criar Produto**: Tipo "Virtual"
2. **Meta Créditos**: `_dsbc_credit_amount` = quantidade
3. **Preço**: Valor em reais
4. **Categoria**: "Créditos" (recomendado)

### Configurações de Saque
- **Valor Mínimo**: Configurável
- **Taxa de Saque**: Percentual ou valor fixo
- **Métodos**: PIX, Transferência, etc.
- **Aprovação**: Manual ou automática

## 🛠️ Desenvolvimento

### Estrutura de Arquivos
```
ds-backgamom-credits/
├── ds-backgamom-credits.php (arquivo principal)
├── README.md
├── reports/ (diretório para relatórios)
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
    ├── class-ds-credit-manager.php
    ├── class-ds-simple-withdrawals.php
    ├── class-ds-webhook-handler.php
    ├── class-ds-withdrawal-handler-complete.php
    └── class-ds-withdrawal-handler.php
```

### Hooks Disponíveis
- `dsbc_plugin_loaded` - Plugin carregado e inicializado
- `woocommerce_order_status_completed` - Concede créditos ao completar pedido
- `woocommerce_payment_complete` - Concede créditos ao confirmar pagamento
- `woocommerce_product_options_general_product_data` - Campo de créditos no produto
- `woocommerce_process_product_meta` - Salva configuração de créditos
- `woocommerce_account_dashboard` - Exibe saldo na conta do cliente

### Filtros Disponíveis
- `dsbc_minimum_withdrawal` - Valor mínimo de saque
- `dsbc_withdrawal_fee` - Taxa de saque
- `dsbc_credit_product_types` - Tipos de produto que geram créditos

## 🔒 Segurança

### Validações
- **Saldo Suficiente**: Verificação antes de deduções
- **Nonces**: Proteção CSRF em formulários
- **Sanitização**: Dados limpos antes do armazenamento
- **Permissões**: Verificação de capacidades do usuário

### Auditoria
- **Log de Transações**: Histórico completo
- **Rastreabilidade**: Origem de cada operação
- **Backup**: Dados críticos protegidos
- **Webhooks Seguros**: Validação de assinatura

## 📊 Dashboard Administrativo

### Estatísticas em Tempo Real
- **Total de Créditos**: Soma de todos os créditos em circulação
- **Usuários Ativos**: Quantidade de usuários com saldo > 0
- **Pedidos Hoje**: Pedidos processados no dia atual

### Status do Sistema
- **WooCommerce**: Verificação de dependência
- **Webhook Asaas**: Status da configuração
- **Gravity Forms**: Disponibilidade para formulários
- **Formulário de Saque**: Configuração ativa

### Funcionalidades Administrativas
- **Consultar Créditos**: Busca por usuário específico
- **Gerenciar Saques**: Aprovação/rejeição de solicitações
- **Histórico Completo**: Log de todas as transações
- **Relatórios**: Análises detalhadas do sistema

## 📝 Changelog

### v2.0.0 (05/11/2025)
- Sistema completo de créditos implementado
- Gateway Asaas integrado
- Sistema de saques com aprovação
- Notificações WhatsApp automáticas
- API completa para integração
- Compatibilidade com HPOS

## 👨💻 Desenvolvedor

**DSantos Info**  
Site: [dsantosinfo.com.br](https://dsantosinfo.com.br)  
Suporte: Através do painel administrativo

## 📄 Licença

GPL v2 or later - Licença livre para uso e modificação


---
# Guia de Padrões de Desenvolvimento

*   **Manutenção do Guia:** Este documento deve ser mantido atualizado para refletir as práticas e padrões mais recentes do projeto.

*   **Análise do Código Base:** Antes de criar novos arquivos ou funcionalidades, analise o código existente para evitar duplicidade, garantir consistência e reaproveitar soluções.

*   **Princípio da Responsabilidade Única (SRP):** Cada classe deve ter uma única e bem definida responsabilidade. Isso facilita a manutenção, os testes e a escalabilidade do sistema.

*   **Arquitetura Modular:** Organize o código em módulos independentes e coesos. Cada módulo deve encapsular uma parte da lógica de negócio, promovendo a reutilização e o desacoplamento.

*   **Padrões WordPress:** Siga estritamente as [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) para garantir a qualidade, legibilidade e compatibilidade do código com o ecossistema WordPress.

---
