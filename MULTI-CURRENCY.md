# Sistema Multi-Moedas Aprimorado - DS Backgamom Credits

## Visão Geral

O sistema de multi-moedas foi aprimorado para oferecer uma experiência otimizada baseada no país do usuário, garantindo que:

- **Brasileiros** vejam preços em Real (R$) como principal e Dólar ($) como referência
- **Outros países** vejam preços em Dólar ($) como principal
- **Pagamentos** sejam processados na moeda correta conforme o país

## Funcionalidades Implementadas

### 1. Exibição de Preços Inteligente

#### Para Usuários Brasileiros:
- **Preço Principal**: Real Brasileiro (R$) - destacado visualmente
- **Preço Secundário**: Dólar Americano ($) - como referência
- **Gateway**: Asaas (PIX/Cartão) para BRL

#### Para Usuários Internacionais:
- **Preço Principal**: Dólar Americano ($) - destacado visualmente  
- **Preço Secundário**: Real Brasileiro (R$) - como referência (se disponível)
- **Gateway**: WISE para USD e outras moedas

### 2. Seletor de Moeda Aprimorado

- **Auto-seleção** baseada no país do usuário
- **Feedback visual** da moeda selecionada
- **Informações de pagamento** dinâmicas
- **Validação** durante o checkout

### 3. Gateways de Pagamento por País

#### Gateway Asaas (Brasileiros):
- **Moedas aceitas**: Apenas BRL
- **Métodos**: PIX, Cartão de Crédito
- **Disponibilidade**: Apenas para usuários do Brasil

#### Gateway WISE (Internacional):
- **Moedas aceitas**: USD, EUR, GBP, BRL
- **Método**: Transferência internacional com comprovante
- **Disponibilidade**: Todos os países

### 4. Validações de Checkout

- **Brasileiros**: Forçados a pagar em BRL via Asaas
- **Internacionais**: Direcionados para USD via WISE
- **Verificação**: Disponibilidade de preços nas moedas corretas
- **Fallback**: Mensagens de erro se produto não disponível na moeda necessária

## Configuração de Produtos

### Campos de Preço por Moeda

No painel administrativo do produto, configure:

```
Real Brasileiro (R$): 100.00
Dólar Americano ($): 20.00
Euro (€): 18.00
Libra Esterlina (£): 15.00
```

### Conversão Automática para Créditos

O sistema calcula automaticamente os créditos baseado no preço em cada moeda:

- **BRL**: R$ 100,00 = 100 créditos (1:1)
- **USD**: $20,00 = 100 créditos (5:1)
- **EUR**: €18,00 = 99 créditos (5.5:1)

## Experiência do Usuário

### Na Página do Produto

1. **Preços exibidos** conforme país do usuário
2. **Seletor de moeda** (se múltiplas disponíveis)
3. **Informações de pagamento** dinâmicas
4. **Destaque visual** da moeda principal

### No Carrinho

1. **Preço na moeda selecionada**
2. **Indicação da moeda** nos itens
3. **Validação** de disponibilidade

### No Checkout

1. **Gateway apropriado** baseado na moeda
2. **Validação** de país vs moeda
3. **Mensagens de erro** se incompatível
4. **Processamento** na moeda correta

## Arquivos Modificados

### Classes Principais:
- `DS_Currency_Manager` - Lógica principal de multi-moedas
- `DS_Asaas_Gateway` - Restrição para BRL apenas
- `DS_Wise_Gateway` - Suporte multi-moedas

### Assets:
- `frontend.css` - Estilos para exibição de preços
- `currency-selector.js` - Interatividade do seletor

### Funcionalidades Adicionadas:
- Validação de moeda por país no checkout
- Salvamento de moeda do pedido
- Feedback visual aprimorado
- Auto-seleção inteligente de moeda

## Fluxo de Pagamento

### Usuário Brasileiro:
1. Vê preços em R$ (principal) e $ (referência)
2. Seleciona produto (auto-seleciona BRL)
3. Vai para checkout
4. Vê apenas gateway Asaas
5. Paga em BRL via PIX/Cartão

### Usuário Internacional:
1. Vê preços em $ (principal)
2. Seleciona produto (auto-seleciona USD)
3. Vai para checkout  
4. Vê apenas gateway WISE
5. Paga em USD via transferência

## Configurações Administrativas

### Gateway Asaas:
- Ativar apenas para BRL
- Configurar API key
- Definir métodos (PIX/Cartão)

### Gateway WISE:
- Configurar moedas aceitas
- Definir email para recebimento
- Configurar upload de comprovantes

### Produtos:
- Definir preços em cada moeda
- Deixar em branco moedas não disponíveis
- Sistema calcula créditos automaticamente

## Benefícios

1. **Experiência Localizada**: Cada usuário vê preços na moeda apropriada
2. **Pagamentos Otimizados**: Gateways corretos para cada região
3. **Conversão Transparente**: Créditos calculados automaticamente
4. **Validação Robusta**: Previne erros de moeda/país
5. **Interface Intuitiva**: Seleção e feedback visual claros

## Suporte Técnico

Para dúvidas ou problemas:
- Verificar logs do WooCommerce
- Testar com usuários de diferentes países
- Validar configuração de preços nos produtos
- Confirmar ativação dos gateways corretos