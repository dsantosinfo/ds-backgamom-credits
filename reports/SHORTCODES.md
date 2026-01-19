# DS Backgamom Credits - Shortcodes Otimizados

## 📋 Visão Geral

Sistema completo de shortcodes para usuários logados acompanharem seus créditos, histórico de transações, estatísticas e realizarem ações relacionadas aos créditos.

## 🚀 Shortcodes Disponíveis

### 1. `[ds_credit_balance]` - Saldo Simples

Exibe o saldo atual do usuário em diferentes formatos.

**Parâmetros:**
- `format` - Formato de exibição (default: `number`)
  - `number` - Apenas o número
  - `badge` - Badge estilizado
  - `card` - Card com destaque
- `show_label` - Mostrar texto "créditos" (default: `true`)

**Exemplos:**
```
[ds_credit_balance]
[ds_credit_balance format="badge"]
[ds_credit_balance format="card" show_label="false"]
```

### 2. `[ds_credit_dashboard]` - Dashboard Completo

Dashboard completo com saldo, estatísticas e histórico recente.

**Parâmetros:**
- `show_history` - Exibir histórico recente (default: `true`)
- `history_limit` - Limite de transações no histórico (default: `5`)
- `show_stats` - Exibir estatísticas rápidas (default: `true`)

**Exemplos:**
```
[ds_credit_dashboard]
[ds_credit_dashboard show_history="false"]
[ds_credit_dashboard history_limit="10" show_stats="false"]
```

**Funcionalidades:**
- Saldo destacado com design atrativo
- Botões de ação (Comprar Créditos, Solicitar Saque)
- Estatísticas: Total Ganho, Total Gasto, Número de Transações
- Histórico das últimas transações
- Carregamento AJAX de mais transações
- Design responsivo

### 3. `[ds_credit_history]` - Histórico Detalhado

Histórico completo de transações com filtros e paginação.

**Parâmetros:**
- `limit` - Número de transações por página (default: `10`)
- `type` - Filtro por tipo (default: `all`)
  - `all` - Todas as transações
  - `deposit` - Apenas depósitos
  - `withdrawal` - Apenas saques
  - `manual_addition` - Apenas adições manuais
- `show_pagination` - Mostrar paginação (default: `true`)

**Exemplos:**
```
[ds_credit_history]
[ds_credit_history type="deposit" limit="20"]
[ds_credit_history show_pagination="false"]
```

**Funcionalidades:**
- Filtro por tipo de transação
- Carregamento AJAX de mais registros
- Detalhes completos: tipo, valor, data, observações
- Badges coloridos por tipo de transação

### 4. `[ds_credit_stats]` - Estatísticas por Período

Estatísticas detalhadas por período específico.

**Parâmetros:**
- `period` - Período em dias (default: `30`)
- `show_chart` - Exibir gráfico (default: `false`) *[Futuro]*

**Exemplos:**
```
[ds_credit_stats]
[ds_credit_stats period="7"]
[ds_credit_stats period="90" show_chart="true"]
```

**Métricas:**
- Créditos Recebidos no período
- Créditos Gastos no período
- Número de Transações
- Saldo Líquido (ganhos - gastos)

### 5. `[ds_credit_widget]` - Widget Compacto

Widget compacto para sidebars e áreas menores.

**Parâmetros:**
- `style` - Estilo visual (default: `default`)
  - `default` - Estilo padrão com borda
  - `minimal` - Estilo minimalista
  - `card` - Estilo card com gradiente
- `show_actions` - Mostrar botões de ação (default: `true`)
- `show_last_transaction` - Mostrar última transação (default: `false`)

**Exemplos:**
```
[ds_credit_widget]
[ds_credit_widget style="card" show_last_transaction="true"]
[ds_credit_widget style="minimal" show_actions="false"]
```

## 🎨 Estilos e Personalização

### Classes CSS Principais

- `.ds-credit-dashboard` - Container principal do dashboard
- `.ds-balance-section` - Seção do saldo principal
- `.ds-stats-section` - Grid de estatísticas
- `.ds-history-section` - Seção do histórico
- `.ds-credit-widget` - Widget compacto
- `.history-item` - Item individual do histórico

### Responsividade

Todos os shortcodes são totalmente responsivos:
- **Desktop**: Layout em grid com múltiplas colunas
- **Tablet**: Adaptação automática do grid
- **Mobile**: Layout em coluna única com elementos empilhados

### Cores e Temas

- **Verde**: `#28a745` - Valores positivos, botões primários
- **Vermelho**: `#dc3545` - Valores negativos, alertas
- **Azul**: `#007cba` - Links e ações secundárias
- **Gradiente**: `#667eea` → `#764ba2` - Cards destacados

## ⚡ Funcionalidades AJAX

### Carregamento Dinâmico
- Histórico carregado sob demanda
- Filtros aplicados sem recarregar página
- Atualização automática de saldo (opcional)

### Segurança
- Verificação de nonce em todas as requisições
- Validação de usuário logado
- Sanitização de dados de entrada

## 📱 Integração com WhatsApp

Os shortcodes se integram automaticamente com o sistema de notificações:
- Botão de saque conectado ao sistema de solicitações
- Notificações automáticas para transações
- Links diretos para suporte via WhatsApp

## 🔧 Configuração e Uso

### Requisitos
- Usuário deve estar logado
- Plugin DS Backgamom Credits ativo
- WooCommerce instalado e configurado

### Implementação Básica

**Página "Meus Créditos":**
```
[ds_credit_dashboard]
```

**Sidebar com Widget:**
```
[ds_credit_widget style="card"]
```

**Página de Histórico:**
```
[ds_credit_history limit="20"]
```

### Implementação Avançada

**Dashboard Personalizado:**
```
<div class="minha-conta-creditos">
    <h2>Meus Créditos</h2>
    [ds_credit_balance format="card"]
    
    <div class="row">
        <div class="col-md-8">
            [ds_credit_history limit="15"]
        </div>
        <div class="col-md-4">
            [ds_credit_stats period="30"]
        </div>
    </div>
</div>
```

## 🚀 Performance

### Otimizações Implementadas
- Consultas SQL otimizadas com índices
- Carregamento lazy de histórico
- Cache de estatísticas (quando possível)
- Minificação automática de CSS inline

### Limites Recomendados
- Histórico inicial: 5-10 transações
- Carregamento adicional: 10-20 transações
- Período de estatísticas: 30-90 dias

## 🔮 Funcionalidades Futuras

### Em Desenvolvimento
- Gráficos interativos de estatísticas
- Exportação de relatórios em PDF
- Notificações push em tempo real
- Integração com carteira digital

### Planejado
- Comparativo de períodos
- Metas de economia
- Cashback automático
- Programa de fidelidade

## 📞 Suporte

Para dúvidas sobre implementação ou customização dos shortcodes:
- **Site**: [dsantosinfo.com.br](https://dsantosinfo.com.br)
- **Documentação**: Painel administrativo do plugin
- **Suporte**: Através do sistema de tickets

---

**Versão:** 2.0.0  
**Compatibilidade:** WordPress 5.0+, WooCommerce 5.0+  
**Última atualização:** 05/11/2025