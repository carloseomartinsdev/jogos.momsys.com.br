<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ludo Novo • Jogo</title>
  <link rel="stylesheet" href="styles_new.css" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <div class="container">
    <!-- Menu inicial -->
    <div class="menu-section">
      <h1>🎯 Ludo Novo</h1>
      <p>Tabuleiro diferente com novas mecânicas!</p>
      
      <div class="form-group">
        <label>Tabuleiro:</label>
        <select id="boardName">
          <option value="oito">Oito</option>
          <option value="anel_ilhas">Anel com Ilhas</option>
        </select>
      </div>
      
      <div class="form-group">
        <label>Jogadores:</label>
        <select id="playersCount">
          <option value="2">2 Jogadores</option>
          <option value="3">3 Jogadores</option>
          <option value="4">4 Jogadores</option>
        </select>
      </div>
      
      <div class="buttons">
        <button id="btnCriar">Criar Jogo</button>
      </div>
      
      <div class="form-group">
        <label>Ou entre em uma sala:</label>
        <input type="text" id="roomCode" placeholder="Código da sala" maxlength="6">
        <button id="btnEntrar">Entrar</button>
      </div>
    </div>

    <!-- Jogo -->
    <div class="game-section" style="display: block;">
      <div class="game-header">
        <div class="game-info">
          <span>Sala: <span id="txtRoom">-</span></span>
          <span>Você: <span id="txtYou">-</span></span>
          <span>Turno: <span id="txtTurn">-</span></span>
          <span>Dado: <span id="txtDie">-</span></span>
        </div>
        <div class="game-controls">
          <button id="btnRoll">Rolar Dado</button>
          <button id="btnRestart">Reiniciar</button>
        </div>
      </div>

      <div class="game-content">
        <div class="board-area">
          <svg id="boardSvg" width="500" height="500" viewBox="0 0 100 100"></svg>
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
          
          <div class="chat-area">
            <h4>Chat:</h4>
            <div id="chatLog"></div>
            <div class="chat-input">
              <input type="text" id="chatMsg" placeholder="Mensagem..." maxlength="100">
              <button id="chatSend">Enviar</button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="log-area">
        <h4>Log:</h4>
        <div id="log"></div>
      </div>
      
      <div class="share-area">
        <p>Compartilhe: <a id="shareLink" href="#" target="_blank">Link da sala</a></p>
        <p id="linkSala"></p>
      </div>
    </div>
  </div>

  <script src="ludo.js"></script>
</body>
</html>
