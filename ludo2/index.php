<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ludo Novo</title>
  <link rel="stylesheet" href="../common.css" />
  <link rel="stylesheet" href="styles_new.css" />
  <link rel="manifest" href="../site.webmanifest">
  <link rel="icon" href="../images/icone.ico" type="image/x-icon">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>window.LUDO_API = 'api_ludo.php';</script>
</head>
<body>
  <header class="topbar">
    <h1>🎯 Ludo Novo</h1>
    <div class="controls">
      <a href="../" class="btn">← Voltar</a>
    </div>
  </header>

  <div class="container">
    <!-- Menu inicial -->
    <div class="menu-section" id="menuSection">
      <p class="muted">Tabuleiro diferente com novas mecânicas!</p>
      
      <div class="form-group">
        <label>Tabuleiro:</label>
        <select id="boardName" class="input">
          <option value="classico">Ludo Clássico</option>
          <option value="portais">Ludo com Portais</option>
          <option value="estrela">Ludo Estrela</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Jogadores:</label>
        <select id="playersCount" class="input">
          <option value="2">2 Jogadores</option>
          <option value="3">3 Jogadores</option>
          <option value="4">4 Jogadores</option>
        </select>
      </div>
      
      <div class="buttons">
        <button id="btnCriar" class="btn">Criar Jogo</button>
      </div>
      
      <div class="form-group">
        <label>Ou entre em uma sala:</label>
        <input type="text" id="roomCode" class="input" placeholder="Código da sala" maxlength="6" style="width:100%; max-width:300px;">
        <button id="btnEntrar" class="btn" style="margin-top:10px;">Entrar</button>
      </div>
    </div>

    <!-- Jogo -->
    <div class="game-section" id="gameSection" style="display: none;">
      <div class="game-header">
        <div class="game-info">
          <span>Sala: <span id="txtRoom">-</span></span>
          <span>Você: <span id="txtYou">-</span></span>
          <span>Turno: <span id="txtTurn">-</span></span>
          <span>Dado: <span id="txtDie">-</span></span>
        </div>
        <div class="game-controls">
          <button id="btnRoll" class="btn">Rolar Dado</button>
          <button id="btnRestart" class="btn warn">Reiniciar</button>
        </div>
      </div>

      <div class="game-content">
        <div class="board-area">
          <svg id="boardSvg" width="600" height="600" viewBox="-5 -5 110 110"></svg>
        </div>
        
        <div class="sidebar">
          <div class="status-area">
            <div id="status">Aguardando...</div>
            <div>Vivos: <span id="aliveList">-</span></div>
          </div>
          
          <div class="pieces-info">
            <h4>Peças:</h4>
            <div id="piecesInfo">-</div>
          </div>
          
          <div class="card" style="background: #0f1117; border: 1px solid var(--line); border-radius: 8px; padding: 12px; margin-top: 10px;">
            <h4 style="margin: 0 0 8px; font-size: 13px; color: var(--muted);">Regras do Ludo:</h4>
            <ul style="margin: 0; padding-left: 18px; font-size: 11px; color: var(--muted); line-height: 1.6;">
              <li>Tire 6 no dado para sair da base</li>
              <li>Tire 6 ou capture para jogar novamente</li>
              <li>Casas com ★ são seguras (não capturam)</li>
              <li>Casas roxas são portais - se parar exatamente nelas, entra no braço</li>
              <li>Braços (casas cinzas numeradas) são caminhos extras que retornam ao tabuleiro</li>
              <li>Complete a volta e entre na reta final</li>
              <li>Leve todas as 4 peças até a META</li>
            </ul>
          </div>
          
          <div class="chat-area">
            <h4>Chat:</h4>
            <div id="chatLog"></div>
            <div class="chat-input">
              <input type="text" id="chatMsg" placeholder="Mensagem..." maxlength="100">
              <button id="chatSend" class="btn">Enviar</button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="log-area">
        <h4>Log:</h4>
        <div id="log"></div>
      </div>
      
      <div class="share-area">
        <p><strong>Compartilhe:</strong> <a id="shareLink" href="#" target="_blank">Link da sala</a></p>
        <p id="linkSala" style="font-size: 11px; word-break: break-all;"></p>
      </div>
    </div>

    <footer class="footer">
      <span>© <?= date('Y') ?> • Seus Jogos</span>
      <span>Um oferecimento Martins Soluções WEB • <a href="https://momsys.com.br/home" target="_blank">momsys.com.br/home</a></span>
    </footer>
  </div>

  <script src="ludo.js"></script>
</body>
</html>
