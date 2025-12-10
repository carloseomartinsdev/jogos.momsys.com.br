# Padronização de Layout - Jogos

## Resumo das Alterações

Foi criado um arquivo CSS comum (`common.css`) para padronizar o layout de todos os jogos do site.

### Arquivo Criado

- **`common.css`** - Arquivo CSS centralizado com estilos padrão para todos os jogos

### Características do Layout Padrão

#### Cores e Tema
- Fundo escuro com gradiente: `#0e0f12` → `#0b0c10`
- Painel: `#171a21`
- Texto: `#e5e7eb`
- Accent (azul): `#3b82f6`
- Sucesso (verde): `#22c55e`
- Aviso (laranja): `#f59e0b`
- Perigo (vermelho): `#ef4444`

#### Componentes Padronizados

1. **Topbar** - Cabeçalho com título e controles
2. **Botões** - `.btn`, `.btn.warn`, `.btn.ok`, `.btn.subtle`
3. **Inputs** - `.input` com estilo escuro
4. **Pills** - `.pill` para exibir informações de status
5. **Cards** - `.card` para seções de conteúdo
6. **Footer** - Rodapé com informações do site
7. **Log** - `.log` para histórico de eventos

#### Fonte
- **Inter** (Google Fonts) como fonte principal
- Fallback: system-ui, Segoe UI, Roboto, Arial

### Jogos Atualizados

Todos os jogos foram atualizados para usar o layout padrão:

1. ✅ **Jogo da Velha** (`jogodavelha/public/index.html`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji #️⃣
   - Footer adicionado

2. ✅ **Damas** (`damas/index.php`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji ♟️
   - Footer adicionado

3. ✅ **Pontinho** (`pontinho/index.php`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji 🔳
   - Footer adicionado

4. ✅ **Batalha Naval 1** (`batalhanaval/index.php`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji 🚢
   - Footer adicionado

5. ✅ **Batalha Naval 2** (`batalhanaval2/index.php`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji ⚓
   - Footer adicionado

6. ✅ **Ludo** (`ludo2/index.php`)
   - Adicionado `common.css`
   - Topbar padronizado com emoji 🎯
   - Footer adicionado

### Página Principal

A página `index.php` foi atualizada para:
- Corrigir link do Batalha Naval 1 (era `/batalhanaval1/`, agora `/batalhanaval/`)
- Remover referências ao "Ludo Clássico" (não existe)
- Manter apenas "Ludo" apontando para `ludo2/`

### Como Usar

Para adicionar novos jogos ou páginas, basta:

1. Incluir o `common.css` antes do CSS específico do jogo:
```html
<link rel="stylesheet" href="../common.css" />
<link rel="stylesheet" href="styles.css" />
```

2. Usar a estrutura HTML padrão:
```html
<header class="topbar">
  <h1>🎮 Nome do Jogo</h1>
  <div class="controls">
    <a href="../" class="btn">← Voltar</a>
    <!-- outros controles -->
  </div>
</header>

<!-- conteúdo do jogo -->

<footer class="footer">
  <span>© <?= date('Y') ?> • Seus Jogos</span>
  <span>Um oferecimento Martins Soluções WEB • <a href="https://momsys.com.br/home" target="_blank">momsys.com.br/home</a></span>
</footer>
```

### Benefícios

- ✅ Visual consistente em todos os jogos
- ✅ Manutenção centralizada de estilos
- ✅ Responsividade padronizada
- ✅ Identidade visual unificada
- ✅ Fácil adição de novos jogos
