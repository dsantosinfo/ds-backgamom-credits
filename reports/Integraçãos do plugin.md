# DS Backgamom Credits - Integrações Técnicas

## Integrações com WooCommerce

### 1. **Gateway de Pagamento Personalizado**
- **Classe**: `DS_Asaas_Gateway` (extends `WC_Payment_Gateway`)
- **Integração**: Registrado via filtro `woocommerce_payment_gateways`
- **Funcionalidades**:
  - Processa pagamentos PIX e Cartão via API Asaas
  - Gerencia clientes Asaas automaticamente
  - Suporte a CPF para clientes brasileiros
  - Redirecionamento para página de pagamento Asaas
  - Validação automática de CPF por país

### 2. **Sistema de Produtos com Créditos**
- **Hooks utilizados**:
  - `woocommerce_product_options_general_product_data` - Adiciona campo de créditos
  - `woocommerce_process_product_meta` - Salva configuração de créditos
- **Meta personalizada**: `_dsbc_credits_amount` define quantos créditos o produto concede
- **Auto-complete**: Produtos de créditos são marcados como concluídos automaticamente

### 3. **Processamento Automático de Pedidos**
- **Hooks principais**:
  - `woocommerce_order_status_completed` - Concede créditos quando pedido é concluído
  - `woocommerce_payment_complete` - Concede créditos quando pagamento é confirmado
- **Prevenção de duplicação**: Meta `_dsbc_credits_awarded` evita processamento duplo
- **Logs automáticos**: Registra como 'deposit' na tabela de auditoria
- **Compatibilidade HPOS**: Declarada no arquivo principal

### 4. **Integração com Checkout Inteligente**
- **Classe**: `DS_Checkout_Manager`
- **Funcionalidades**:
  - Campo CPF obrigatório para clientes brasileiros
  - Auto-preenchimento de dados salvos (nome, sobrenome, CPF)
  - Validação e formatação automática com JavaScript
  - Salvamento no perfil do usuário para uso futuro
  - Sincronização entre campos do checkout e gateway

### 5. **Webhooks para Confirmação de Pagamento**
- **Classe**: `DS_Webhook_Handler`
- **Endpoint**: `/wp-json/ds-backgamom-credits/v1/asaas-webhook`
- **Segurança**: Header `asaas-access-token` para validação
- **Eventos processados**:
  - `PAYMENT_CONFIRMED` / `PAYMENT_RECEIVED` - Concede créditos
  - `PAYMENT_OVERDUE` - Marca pedido como falhado
  - `PAYMENT_REFUNDED` - Processa estorno

### 6. **Integração com Minha Conta**
- **Hook**: `woocommerce_account_dashboard`
- **Funcionalidade**: Exibe saldo de créditos no dashboard do cliente
- **Shortcodes**: Disponíveis para personalização

### 7. **Shortcodes para Frontend**
- `[ds_credit_balance]` - Exibe saldo atual
- `[ds_credit_dashboard]` - Dashboard completo de créditos
- `[ds_withdrawal_form]` - Formulário de saque com AJAX
- `[ds_checkout_fluido]` - Checkout otimizado

### 8. **Gestão de Estoque e Carrinho**
- Redução automática de estoque: `wc_reduce_stock_levels()`
- Limpeza do carrinho: `WC()->cart->empty_cart()`
- Notas automáticas no pedido com informações do gateway

### 9. **Compatibilidade e Dependências**
- Verificação de WooCommerce ativo
- Suporte ao HPOS (High-Performance Order Storage)
- Integração com meta de usuário e pedidos
- Compatibilidade com diferentes gateways de pagamento

## Integrações com WordPress

### 1. **Sistema de Usuários e Meta**
- **User Meta**: `_dsbc_credit_balance` armazena saldo de créditos
- **Integração ACF**: Campos personalizados para PIX, Wise, WhatsApp
- **Billing Meta**: Integração com dados de cobrança do WooCommerce
- **Perfil do Usuário**: Salvamento automático de CPF e dados de pagamento

### 2. **Sistema de Banco de Dados**
- **Tabela Personalizada**: `wp_dsbc_credit_logs` para histórico completo
- **Tabela de Saques**: `wp_ds_withdrawal_requests` para solicitações
- **Auto-criação**: Tabelas criadas automaticamente via `dbDelta()`
- **Índices**: Otimização para consultas por usuário e data

### 3. **API REST Personalizada**
- **Namespace**: `ds-backgamom-credits/v1`
- **Endpoint Webhook**: `/asaas-webhook` para receber notificações
- **Autenticação**: Token personalizado para segurança
- **Validação**: Verificação de nonce e permissões

### 4. **Sistema de Opções**
- **Settings API**: Uso nativo do WordPress para configurações
- **Option Groups**: `ds_backgamom_credits_group`
- **Configurações**: Valores mínimos, tokens, formulários
- **Sanitização**: Limpeza automática de dados

### 5. **Menu Administrativo**
- **Menu Principal**: "Créditos" com ícone `dashicons-money-alt`
- **Submenus**: Dashboard, Relatórios, Consultas, Histórico, Saques
- **Capacidades**: Diferentes níveis de acesso (`manage_options`, `edit_shop_orders`)
- **Navegação por Abas**: Interface organizada

### 6. **Sistema AJAX**
- **Handlers**: `wp_ajax_*` para ações administrativas
- **Nonces**: Proteção CSRF em todas as requisições
- **Respostas JSON**: Padronização de retornos
- **Ações disponíveis**:
  - `ds_add_credits_manually` - Adição manual de créditos
  - `ds_get_user_history` - Histórico do usuário
  - `approve_withdrawal` / `reject_withdrawal` - Gestão de saques

### 7. **Shortcodes**
- **Registro**: `add_shortcode()` para funcionalidades frontend
- **Shortcodes disponíveis**:
  - `[ds_credit_balance]` - Saldo simples
  - `[ds_credit_dashboard]` - Dashboard completo
  - `[ds_withdrawal_form]` - Formulário de saque
  - `[ds_checkout_fluido]` - Checkout otimizado

### 8. **Sistema de Hooks e Filtros**
- **Actions utilizadas**:
  - `plugins_loaded` - Inicialização do plugin
  - `admin_menu` - Criação de menus
  - `admin_init` - Registro de configurações
  - `wp_ajax_*` - Handlers AJAX
  - `rest_api_init` - Registro de endpoints
- **Filters utilizados**:
  - `woocommerce_billing_fields` - Campo CPF no checkout
  - `woocommerce_checkout_posted_data` - Auto-preenchimento

### 9. **Integração com Gravity Forms**
- **Verificação**: `class_exists('GFAPI')` para disponibilidade
- **Configuração**: Seleção de formulários para saques
- **Meta Fields**: Armazenamento de status de solicitações
- **API**: Uso da `GFAPI` para manipular entradas

### 10. **Sistema de Logs e Auditoria**
- **Estrutura completa**:
  ```sql
  CREATE TABLE wp_dsbc_credit_logs (
      id int(11) AUTO_INCREMENT,
      user_id int(11) NOT NULL,
      amount int(11) NOT NULL,
      type varchar(50) NOT NULL,
      observation text,
      admin_id int(11),
      admin_name varchar(255),
      old_balance int(11) DEFAULT 0,
      new_balance int(11) DEFAULT 0,
      created_at datetime NOT NULL
  );
  ```
- **Tipos de transação**: `manual_addition`, `withdrawal`, `deposit`, `deduction`, `refund`

### 11. **Integração com WhatsApp**
- **Classe Externa**: `WhatsApp_Connector` para notificações
- **Templates**: Sistema de templates para mensagens
- **Formatação**: `WhatsApp_Phone_Formatter` para números
- **Eventos notificados**:
  - Depósito de créditos
  - Aprovação/rejeição de saques
  - Novas solicitações (admin)

### 12. **Sistema de Permissões**
- **Verificações**: `current_user_can()` para controle de acesso
- **Níveis diferentes**:
  - `manage_options` - Administradores completos
  - `edit_shop_orders` - Gerentes de pedidos
- **Nonces**: Proteção contra CSRF em formulários

### 13. **Assets e Scripts**
- **Enqueue**: `wp_enqueue_scripts` para frontend
- **Admin Assets**: Scripts específicos para área administrativa
- **Inline Scripts**: JavaScript embutido para funcionalidades específicas
- **Estilos**: CSS inline para componentes

### 14. **Singleton Pattern**
- **Implementação**: Classe principal usa padrão Singleton
- **Instância única**: `DS_Backgamom_Credits::instance()`
- **Inicialização controlada**: Evita conflitos e duplicações

### 15. **Compatibilidade e Dependências**
- **Verificação WooCommerce**: `class_exists('WooCommerce')`
- **HPOS Support**: Declaração de compatibilidade
- **PHP Version**: Requisito mínimo 7.4
- **WordPress Version**: Mínimo 5.0

## Resumo

O plugin DS Backgamom Credits demonstra excelente integração com o ecossistema WordPress e WooCommerce, utilizando:

- **APIs nativas** do WordPress e WooCommerce
- **Padrões de desenvolvimento** seguindo melhores práticas
- **Compatibilidade** com plugins populares (ACF, Gravity Forms)
- **Segurança robusta** com nonces, sanitização e validações
- **Performance otimizada** com índices de banco e consultas eficientes
- **Interface moderna** com AJAX e modais responsivos
- **Logs completos** para auditoria e rastreabilidade
- **Sistema extensível** com hooks e filtros para customização

**Status: Sistema totalmente funcional e testado em produção!** ✅