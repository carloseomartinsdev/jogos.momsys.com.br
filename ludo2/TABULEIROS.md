# Tabuleiros do Ludo

## 🎲 Ludo Clássico (Recomendado)

**Arquivo:** `classico.json`

### Características
- ✅ Tabuleiro tradicional em formato de cruz
- ✅ 52 casas no caminho principal (13 por lado)
- ✅ 4 casas seguras (★) - uma em cada lado
- ✅ 6 casas na reta final de cada jogador
- ✅ 4 bases (uma para cada jogador)
- ✅ Centro com META para cada cor

### Regras Clássicas
1. **Sair da Base:** Tire 6 no dado
2. **Movimento:** Ande o número de casas do dado
3. **Captura:** Caia na mesma casa de um oponente (exceto casas seguras ★)
4. **Jogar Novamente:** Tire 6 ou capture um oponente
5. **Reta Final:** Após completar a volta, entre na sua reta final
6. **Vitória:** Leve todas as 4 peças até a META

### Layout
```
        [C]
         |
    [B]--+--[D]
         |
        [A]
```

- **A (Azul)**: Base inferior, entrada em N0
- **B (Vermelho)**: Base esquerda, entrada em N13
- **C (Verde)**: Base superior, entrada em N26
- **D (Amarelo)**: Base direita, entrada em N38

---

## 🔄 Oito (Alternativo)

**Arquivo:** `oito.json`

### Características
- ⚡ Tabuleiro em formato de "8"
- ⚡ 2 anéis conectados
- ⚡ Portais que teleportam entre anéis
- ⚡ Casas seguras em pontos estratégicos
- ⚡ Caminho mais curto e dinâmico

### Diferenças
- Não é circular tradicional
- Tem portais (⤴⤵) que saltam entre anéis
- Menos casas no total
- Mais estratégico e rápido

### Quando Usar
- Para partidas mais rápidas
- Para experimentar mecânicas diferentes
- Para jogadores experientes

---

## 🏝️ Anel com Ilhas (Experimental)

**Arquivo:** `anel_ilhas.json`

### Características
- 🌊 Tabuleiro circular com ilhas
- 🌊 Obstáculos no caminho
- 🌊 Rotas alternativas
- 🌊 Mais complexo

### Status
⚠️ Em desenvolvimento

---

## Comparação

| Característica | Clássico | Oito | Anel Ilhas |
|----------------|----------|------|------------|
| Casas principais | 52 | ~14 | ~30 |
| Portais | ❌ | ✅ | ✅ |
| Casas seguras | 4 | 2 | Várias |
| Complexidade | Baixa | Média | Alta |
| Duração | Longa | Curta | Média |
| Fidelidade ao Ludo | 100% | 60% | 40% |

---

## Recomendações

### Para Iniciantes
👉 **Ludo Clássico** - Regras tradicionais, fácil de entender

### Para Veteranos
👉 **Oito** - Mais estratégia, partidas rápidas

### Para Experimentar
👉 **Anel com Ilhas** - Mecânicas únicas

---

## Como Funciona o Sistema

### Estrutura do Tabuleiro
Cada tabuleiro é um grafo com:
- **Nodes (nós)**: Casas do tabuleiro
- **Edges (arestas)**: Conexões entre casas
- **Types (tipos)**: normal, segura, portal, ponte, home, meta

### Tipos de Nós
- `normal`: Casa comum (pode capturar)
- `segura`: Casa segura ★ (não captura)
- `portal`: Teleporta para outro nó
- `ponte`: Conexão especial
- `home`: Reta final do jogador
- `meta:X`: Chegada final do jogador X
- `inicio:X`: Entrada do jogador X

### Caminhos
- **Main Path**: Caminho circular principal
- **Home Path**: Reta final de cada jogador (6 casas)
- **Home Entrance**: Ponto de entrada na reta final

### Mecânicas
1. Peças começam na BASE (fora do tabuleiro)
2. Com 6, entram no `inicio:X`
3. Seguem o caminho principal
4. Ao passar pela `homeEntrance`, podem entrar na reta final
5. Chegam na META após percorrer a reta final

---

## Criando Seu Próprio Tabuleiro

### Estrutura JSON
```json
{
  "name": "meu_tabuleiro",
  "nodes": [
    {"id":"N1", "x":50, "y":50, "type":"normal", "edges":["N2"]}
  ],
  "edges": [
    {"a":"N1", "b":"N2"}
  ],
  "homeEntrances": {"A":"N1", "B":"N13", "C":"N26", "D":"N38"},
  "homePaths": {
    "A": ["H_A1","H_A2","H_A3","H_A4","H_A5","H_A6"]
  },
  "startBases": {"A":{"x":50,"y":95}},
  "metaNodes": {"A":{"x":50,"y":55}}
}
```

### Dicas
- Use coordenadas de 0 a 100 (viewBox do SVG)
- Mantenha distâncias uniformes entre nós
- Marque casas seguras com `type:"segura"`
- Crie um caminho circular para o clássico
- Teste com 2 jogadores primeiro

---

## Suporte

Para dúvidas ou sugestões de novos tabuleiros, consulte a documentação da API em `api_ludo.php`.
