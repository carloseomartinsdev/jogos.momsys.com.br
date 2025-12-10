<?php
// index.php
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Damas Online</title>
  <link rel="stylesheet" href="../common.css" />
  <link rel="stylesheet" href="styles.css" />
  <link rel="manifest" href="../site.webmanifest">
  <link rel="icon" href="../images/icone.ico" type="image/x-icon">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    // Config do backend
    window.CHECKERS_API = 'api.php';
  </script>
  <script src="game.js"></script>
</head>
<body>
<div class="container">
  <header class="topbar">
    <h1>♟️ Damas Online</h1>
    <div class="controls">
      <a href="../" class="btn">← Voltar</a>
      <button id="btnNovoLocal" class="btn subtle" title="Reinicia apenas localmente (debug)">Reset Local</button>
      <a class="repo" href="#" id="shareLink" target="_blank" rel="noopener">Compartilhar sala</a>
    </div>
  </header>

  <section class="lobby" id="lobby">
    <div class="card">
      <h2>Criar sala</h2>
      <p>Você será o <strong>Vermelho</strong>.</p>
      <button id="btnCriar" class="btn">Criar nova sala</button>
      <div class="small muted">Após criar, compartilhe o link com seu amigo.</div>
      <div id="createInfo" class="small"></div>
    </div>

    <div class="card">
      <h2>Entrar na sala</h2>
      <label class="lbl">Código da sala</label>
      <input type="text" id="roomCode" class="input" placeholder="Ex.: AB12CD" />
      <button id="btnEntrar" class="btn">Entrar como Preto</button>
      <div id="joinInfo" class="small"></div>
    </div>
  </section>

  <section id="gameArea" class="game hidden">
    <div class="hud">
      <div class="pill">Sala: <span id="txtRoom"></span></div>
      <div class="pill">Você é: <span id="txtYou"></span></div>
      <div class="pill"><strong>Vez:</strong> <span id="turnoAtual">—</span></div>
      <div class="pill"><strong>Placar</strong> — Vermelho: <span id="scoreR">12</span> | Preto: <span id="scoreB">12</span></div>
      <button id="btnReiniciarSala" class="btn warn">Reiniciar sala</button>
    </div>

    <div class="board-wrap">
      <div id="board" class="board" aria-label="Tabuleiro de damas"></div>
      <aside class="side">
        <div class="help">
          <h3>Como jogar</h3>
          <ul>
            <li>Envie o link da sala para o amigo.</li>
            <li>Movimentos válidos ficam destacados.</li>
            <li>Captura é obrigatória; múltiplas capturas são suportadas.</li>
            <li>Damas (coroa) movem para frente e para trás.</li>
          </ul>
        </div>
        <div id="log" class="log"></div>
      </aside>
    </div>
  </section>

  <footer class="footer">
    <span>© <?= date('Y') ?> • Seus Jogos</span>
    <span>Um oferecimento Martins Soluções WEB • <a href="https://momsys.com.br/home" target="_blank">momsys.com.br/home</a></span>
  </footer>
</div>
</body>
</html>
