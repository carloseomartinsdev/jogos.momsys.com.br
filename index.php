<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Jogos • Home</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="index.css" />
  <link rel="manifest" href="site.webmanifest">
  <link rel="icon" href="images/icone.ico" type="image/x-icon">
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <span class="logo">🎮</span>
      <h1>Jogos</h1>
    </div>
    <nav class="nav">
      <a class="link" href="./jogodavelha/public/">Jogo da Velha</a>
      <a class="link" href="./damos/">Damas</a>
      <a class="link" href="./pontinho/">Pontinho</a>
      <a class="link" href="./batalhanaval1/">Batalha Naval 1</a>
      <a class="link" href="./batalhanaval2/">Batalha Naval 2</a>
      <a class="link" href="./ludoclassico/">Ludo Clássico</a>
      <a class="link" href="./ludo2/">Ludo Novo</a>
    </nav>
  </header>

  <main class="hero">
    <h2>Bem-vindo! Escolha um jogo para começar</h2>
    <p class="subtitle">Divirta-se com jogos clássicos direto no navegador.</p>

    <section class="grid">
      <a class="card" href="./jogodavelha/public/">
        <div class="icon">#️⃣</div>
        <h3>Jogo da Velha</h3>
        <p>Desafie um amigo ou pratique estratégias nesse clássico 3×3.</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./damos/">
        <div class="icon">♟️</div>
        <h3>Damas (Online)</h3>
        <p>Crie uma sala e jogue contra outra pessoa pela internet.</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./pontinho/">
        <div class="icon">🔳</div>
        <h3>Pontinho (Online)</h3>
        <p>Feche quadrados, marque pontos e garanta a vitória!</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./batalhanaval1/">
        <div class="icon">🚢</div>
        <h3>Batalha Naval 1</h3>
        <p>Posicione seus navios e afunde a frota inimiga!</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./batalhanaval2/">
        <div class="icon">⚓</div>
        <h3>Batalha Naval 2</h3>
        <p>Nova versão do clássico jogo naval com melhorias!</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./ludoclassico/">
        <div class="icon">🎲</div>
        <h3>Ludo Clássico</h3>
        <p>O tradicional jogo de tabuleiro para até 4 jogadores!</p>
        <span class="btn">Jogar</span>
      </a>

      <a class="card" href="./ludo2/">
        <div class="icon">🎯</div>
        <h3>Ludo Novo</h3>
        <p>Ludo com tabuleiro diferente e novas mecânicas!</p>
        <span class="btn">Jogar</span>
      </a>
    </section>
  </main>

  <footer class="footer">
    <span>© <?= date('Y') ?> • Seus Jogos</span>
    <span>Um oferecimento Martins Soluções WEB • <a href="https://momsys.com.br/home" target="_blank">momsys.com.br/home</a></span>
  </footer>
</body>
</html>
