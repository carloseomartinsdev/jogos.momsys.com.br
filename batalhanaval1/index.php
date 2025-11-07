<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Batalha Naval Online (2–4 jogadores)</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="manifest" href="../site.webmanifest">
  <link rel="icon" href="../images/icone.ico" type="image/x-icon">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>window.BS_API = 'api_battleship.php';</script>
  <script src="battleship.js"></script>
</head>
<body>
<div class="app">
  <header class="topbar">
    <h1>Batalha Naval</h1>
    <div class="controls">
      <label class="lbl">Tamanho:</label>
      <select id="gridSize" class="input">
        <option value="10x10" selected>10 × 10</option>
        <option value="8x8">8 × 8</option>
        <option value="12x12">12 × 12</option>
      </select>

      <label class="lbl">Jogadores:</label>
      <select id="playersCount" class="input">
        <option value="2" selected>2</option>
        <option value="3">3</option>
        <option value="4">4</option>
      </select>

      <button id="btnCriar" class="btn">Criar sala</button>
      <input id="roomCode" class="input" placeholder="Código da sala" style="width:140px" />
      <button id="btnEntrar" class="btn">Entrar</button>
      <a id="shareLink" class="repo" href="#" target="_blank" rel="noopener">Compartilhar link</a>
    </div>

    <div class="score">
      <span class="pill">Sala: <b id="txtRoom">—</b></span>
      <span class="pill">Você: <b id="txtYou">—</b></span>
      <span class="pill">Vez: <b id="txtTurn">—</b></span>
      <span class="pill">Vivos: <b id="aliveList">—</b></span>
      <button id="btnRestart" class="btn warn">Reiniciar sala</button>
    </div>
  </header>

  <main class="wrap">
    <section class="panel">
      <h3>Seu tabuleiro</h3>
      <div class="actions">
        <button id="btnRand" class="btn">Aleatorizar navios</button>
        <button id="btnReady" class="btn ok">Estou pronto</button>
        <span id="statusPlace" class="muted"></span>
      </div>
      <div id="gridOwn" class="grid" aria-label="Seu tabuleiro"></div>
    </section>

    <section class="panel">
      <h3>Alvos</h3>
      <div id="opTabs" class="tabs"></div>
      <div class="muted small">Escolha o oponente acima e clique na célula para atirar (apenas na sua vez).</div>
      <div id="gridOpp" class="grid" aria-label="Tabuleiro do oponente"></div>
    </section>

    <aside class="side">
      <div class="card">
        <h3>Como jogar</h3>
        <ul>
          <li>Crie sala (2–4 jogadores) e compartilhe o link/código.</li>
          <li>Aleatorize seus navios e clique <b>Estou pronto</b>.</li>
          <li>Na sua vez, escolha um <b>alvo</b> (jogador vivo) e atire.</li>
          <li>Acertou joga novamente; errou passa a vez.</li>
          <li>Vence quem permanecer com navios quando todos os outros forem destruídos.</li>
        </ul>
        <div class="small muted">Link: <span id="linkSala" style="word-break:break-all"></span></div>
      </div>

      <div class="card chat">
        <h3>Chat da sala</h3>
        <div id="chatLog" class="chat-log"></div>
        <div class="chat-input">
          <input id="chatMsg" placeholder="Escreva e Enter..." />
          <button id="chatSend" class="btn">Enviar</button>
        </div>
      </div>

      <div class="card">
        <h3>Log</h3>
        <div id="log" class="log"></div>
      </div>
    </aside>
  </main>
</div>
</body>
</html>
