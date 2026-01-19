# Widgets Elementor - DS Backgamom Credits

## Visão Geral

Foram criados 2 widgets específicos para o Elementor que permitem exibir preços multi-moedas e conversão de créditos de forma personalizada.

## 🎯 Widgets Disponíveis

### 1. **Preço do Produto (Multi-Moeda)**
- **Nome**: `ds_product_price`
- **Uso**: Páginas de produto individual
- **Categoria**: DS Backgamom Credits

### 2. **Preços da Loja (Multi-Moeda)**
- **Nome**: `ds_shop_price`  
- **Uso**: Loops de produtos, páginas de loja, categorias
- **Categoria**: DS Backgamom Credits

## ⚙️ Configurações Disponíveis

### Configurações Gerais (Ambos Widgets)

#### **Moedas a Exibir**
- **Tipo**: Multi-seleção
- **Opções**: BRL, USD
- **Padrão**: BRL + USD
- **Função**: Escolher quais moedas mostrar

#### **Mostrar Créditos**
- **Tipo**: Switch
- **Padrão**: Sim
- **Função**: Exibir conversão em créditos junto ao preço

#### **Layout**
- **Horizontal**: Preços lado a lado separados por "|"
- **Vertical**: Preços em linhas separadas
- **Compacto**: Preços próximos (apenas widget da loja)

### Configurações Específicas

#### **Widget da Loja (ds_shop_price)**

##### **ID do Produto**
- **Tipo**: Número
- **Função**: Especificar produto específico
- **Padrão**: Usa produto atual do loop

##### **Mostrar Apenas Moeda Principal**
- **Tipo**: Switch
- **Função**: BRL para brasileiros, USD para outros
- **Padrão**: Não

### Configurações de Estilo

#### **Cores**
- **Cor BRL**: Padrão #2c5aa0 (azul)
- **Cor USD**: Padrão #0073aa (azul claro)
- **Cor dos Créditos**: Padrão #666 (cinza)

#### **Tipografia**
- **Controle completo** sobre fonte, tamanho, peso, etc.
- **Aplicado** a todos os preços

## 🚀 Como Usar

### 1. **No Editor do Elementor**

1. Abra uma página/template no Elementor
2. Procure por "DS Backgamom Credits" na categoria de widgets
3. Arraste o widget desejado para a página
4. Configure as opções conforme necessário

### 2. **Widget de Produto Individual**

**Melhor uso:**
- Templates de produto single
- Páginas de produto customizadas
- Popups de produto

**Exemplo de saída:**
```
R$ 100,00 (100 créditos) | US$ 20,00 (100 créditos)
```

### 3. **Widget da Loja**

**Melhor uso:**
- Archive templates (loja, categorias)
- Loops de produtos customizados
- Cards de produto

**Exemplo de saída (layout vertical):**
```
R$ 100,00 (100 créditos)
US$ 20,00 (100 créditos)
```

## 🎨 Exemplos de Configuração

### Configuração Básica (Ambas Moedas)
```
Moedas: BRL + USD
Créditos: Sim
Layout: Horizontal
```
**Resultado**: `R$ 100,00 (100 créditos) | US$ 20,00 (100 créditos)`

### Configuração Compacta (Loja)
```
Moedas: BRL + USD
Créditos: Não
Layout: Compacto
```
**Resultado**: `R$ 100,00 US$ 20,00`

### Configuração por País
```
Moedas: BRL + USD
Mostrar Apenas Principal: Sim
```
**Resultado**: 
- Brasileiros: `R$ 100,00 (100 créditos)`
- Outros: `US$ 20,00 (100 créditos)`

## 🔧 Personalização CSS

### Classes CSS Disponíveis

```css
/* Container principal */
.ds-elementor-price-widget
.ds-elementor-shop-price-widget

/* Layouts */
.layout-horizontal
.layout-vertical  
.layout-compact

/* Preços por moeda */
.ds-price-brl
.ds-price-usd

/* Créditos */
.ds-credits
```

### Exemplo de Customização

```css
/* Destacar preço BRL */
.ds-price-brl {
    font-size: 1.2em;
    font-weight: bold;
    color: #28a745;
}

/* Estilo compacto personalizado */
.layout-compact .ds-price {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    margin: 0 2px;
}
```

## 🔄 Integração com Sistema Existente

### Compatibilidade
- ✅ **Funciona** com preços configurados no produto
- ✅ **Usa** a mesma lógica de conversão de créditos
- ✅ **Respeita** configurações de moeda do plugin
- ✅ **Detecta** país do usuário automaticamente

### Fallbacks
- Se preço não configurado: mostra mensagem de erro
- Se produto não encontrado: mostra aviso
- Se Elementor não ativo: widgets não carregam

## 📱 Responsividade

Os widgets são **totalmente responsivos** e se adaptam a:
- **Desktop**: Layout conforme configurado
- **Tablet**: Mantém layout mas ajusta espaçamentos
- **Mobile**: Layout vertical automático quando necessário

## 🐛 Troubleshooting

### Widget não aparece
- Verificar se Elementor está ativo
- Confirmar que plugin está atualizado
- Limpar cache do Elementor

### Preços não aparecem
- Verificar se produto tem preços configurados
- Confirmar campos `_dsbc_price_brl` e `_dsbc_price_usd`
- Testar com produto diferente

### Créditos incorretos
- Verificar configuração de conversão no plugin
- Confirmar valor base do crédito nas configurações
- Testar cálculo manual

## 🎯 Casos de Uso Recomendados

### E-commerce Brasileiro
```
Widget Loja: Mostrar apenas BRL
Widget Produto: BRL + USD para comparação
```

### E-commerce Internacional  
```
Widget Loja: USD principal
Widget Produto: USD + BRL para brasileiros
```

### Marketplace Multi-região
```
Ambos widgets: Detecção automática por país
Layout: Vertical para melhor legibilidade
```