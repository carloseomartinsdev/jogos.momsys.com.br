# Troubleshooting - Tabuleiro em Branco

## Passos para Diagnosticar

### 1. Teste o SVG Básico
Abra: `http://localhost/jogos.momsys.com.br/ludo2/test_svg.html`

**O que você deve ver:**
- Círculos coloridos (vermelho, azul, verde, laranja)
- Linhas amarelas
- Após 2 segundos, um círculo laranja adicional

**Se NÃO aparecer nada:**
- Problema no navegador ou servidor
- Verifique se o servidor PHP está rodando

### 2. Teste a API
Abra: `http://localhost/jogos.momsys.com.br/ludo2/test_api.html`

Clique em "Testar Create"

**O que você deve ver:**
```json
{
  "success": true,
  "room": "ABC123",
  "token": "...",
  "board": { ... },
  "state": { ... }
}
```

**Se der erro:**
- Verifique se o diretório `rooms/` existe e tem permissão de escrita
- Verifique se o arquivo `boards/oito.json` existe
- Veja o erro no console do navegador (F12)

### 3. Teste o Jogo Real
Abra: `http://localhost/jogos.momsys.com.br/ludo2/`

1. Abra o Console do Navegador (F12 → Console)
2. Clique em "Criar Jogo"
3. Observe os logs no console

**Logs esperados:**
```
Criando sala: {boardName: "oito", maxp: 2}
Resposta create: {success: true, ...}
applyState chamado: {...}
Teste visual SVG...
Elementos de teste adicionados: 4
drawBoard chamado: {board: true, state: true, svg: 1}
Desenhando tabuleiro com 38 nós
```

### 4. Verifique o HTML do SVG
No console, digite:
```javascript
$('#boardSvg').html()
```

**Deve retornar:** Uma string longa com elementos `<line>`, `<g>`, `<circle>`, etc.

**Se retornar vazio:** O problema está na função `drawBoard()`

### 5. Verifique se os Elementos Estão Sendo Criados
No console, digite:
```javascript
$('#boardSvg').children().length
```

**Deve retornar:** Um número > 0 (provavelmente 100+)

**Se retornar 0:** Os elementos não estão sendo adicionados

### 6. Verifique o ViewBox
No console, digite:
```javascript
$('#boardSvg').attr('viewBox')
```

**Deve retornar:** `"0 0 100 100"`

### 7. Inspecione o SVG
1. Clique com botão direito no SVG
2. Selecione "Inspecionar Elemento"
3. Veja se há elementos `<line>`, `<g>`, `<circle>` dentro do `<svg>`

## Problemas Comuns

### Problema: SVG aparece mas está vazio
**Causa:** Elementos não estão sendo criados ou estão fora do viewBox
**Solução:** Verifique os logs no console

### Problema: Erro "board não encontrado"
**Causa:** Arquivo `boards/oito.json` não existe
**Solução:** Verifique se o arquivo existe no caminho correto

### Problema: Erro de permissão
**Causa:** Diretório `rooms/` sem permissão de escrita
**Solução:** 
```bash
chmod 777 rooms/
```

### Problema: Elementos criados mas não visíveis
**Causa:** CSS ocultando elementos ou cores muito escuras
**Solução:** Teste com `test_svg.html` que tem cores vibrantes

### Problema: jQuery não carregado
**Causa:** CDN do jQuery offline ou bloqueado
**Solução:** Verifique no console se há erro de carregamento

## Comandos Úteis no Console

```javascript
// Ver estado atual
console.log('Board:', board);
console.log('State:', state);

// Forçar redesenho
drawBoard();

// Limpar e testar
$('#boardSvg').empty();
$('#boardSvg').append('<circle cx="50" cy="50" r="10" fill="red"/>');

// Ver todos os elementos
$('#boardSvg').children().each((i, el) => console.log(el));
```

## Próximos Passos

1. Execute os testes na ordem acima
2. Anote qual teste falhou
3. Copie os erros do console
4. Verifique os logs que adicionamos no código

## Arquivos de Teste Criados

- `test_svg.html` - Teste básico de renderização SVG
- `test_api.html` - Teste da API PHP
- Este arquivo - Guia de troubleshooting

## Logs Adicionados

O código agora tem logs extensivos em:
- `applyState()` - Quando o estado é atualizado
- `drawBoard()` - Quando o tabuleiro é desenhado
- `svgLine()`, `svgNode()`, `svgPiece()` - Criação de elementos
- `enterGame()` - Transição menu → jogo
- `$('#btnCriar').on('click')` - Criação de sala

Todos os logs começam com o nome da função para fácil identificação.
