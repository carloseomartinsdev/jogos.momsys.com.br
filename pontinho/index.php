<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Pontinho Online</title>
  <link rel="stylesheet" href="../common.css" />
  <link rel="stylesheet" href="styles.css" />
  <link rel="manifest" href="../site.webmanifest">
  <link rel="icon" href="../images/icone.ico" type="image/x-icon">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    window.PONTINHO_API = 'api_pontinho.php';
  </script>
  <script src="pontinho_net.js"></script>
</head>
<body>
<div class="app">
  <header class="topbar">
    <h1>🔳 Pontinho Online</h1>
    <div class="controls">
      <a href="../" class="btn">← Voltar</a>
      <label class="lbl">Tamanho:</label>
      <select id="gridSize" class="input">
        <option value="3x3">3 × 3</option>
        <option value="4x4" selected>4 × 4</option>
        <option value="5x5">5 × 5</option>
        <option value="8x8">8 × 8</option>
      </select>

      <label class="lbl">Jogadores:</label>
      <select id="playersCount" class="input">
        <option value="2" selected>2</option>
        <option value="3">3</option>
        <option value="4">4</option>
      </select>

      <button id="btnCriar" class="btn">Criar sala</button>
      <input type="text" id="roomCode" class="input" placeholder="Código da sala" style="width:130px" />
      <button id="btnEntrar" class="btn">Entrar</button>
      <a id="shareLink" class="repo" href="#" target="_blank" rel="noopener">Compartilhar link</a>
    </div>

    <div class="score">
      <span class="pill">Sala: <b id="txtRoom">—</b></span>
      <span class="pill">Você: <b id="txtYou">—</b></span>
      <span class="pill">Vez: <b id="vez">—</b></span>
      <span class="pill azul">A (Azul): <b id="scoreA">0</b></span>
      <span class="pill vermelho">B (Vermelho): <b id="scoreB">0</b></span>
      <span class="pill verde hideC">C (Verde): <b id="scoreC">0</b></span>
      <span class="pill amarelo hideD">D (Amarelo): <b id="scoreD">0</b></span>
      <button id="btnReiniciarSala" class="btn warn">Reiniciar sala</button>
    </div>
  </header>

  <main class="board-wrap">
    <div id="board" class="board" aria-label="Jogo do Pontinho"></div>
    <aside class="side">
      <div class="card">
        <h3>Como jogar</h3>
        <ul>
          <li>Crie uma sala (2–4 jogadores) ou entre com o código.</li>
          <li>Clique no <b>traço</b> entre dois pontos para marcar.</li>
          <li>Fechou um quadrado? Ganha 1 ponto e joga novamente.</li>
          <li>Turnos rotacionam entre A → B → C → D (apenas jogadores presentes).</li>
        </ul>
        <div class="small muted">Link da sala: <span id="linkSala" style="word-break:break-all"></span></div>
      </div>
      <div class="card">
        <h3>Log</h3>
        <div id="log" class="log"></div>
      </div>
    </aside>
  </main>

  <footer class="footer">
    <span>© <?= date('Y') ?> • Seus Jogos</span>
    <span>Um oferecimento Martins Soluções WEB • <a href="https://momsys.com.br/home" target="_blank">momsys.com.br/home</a></span>
  </footer>
</div>
</body>
</html>
