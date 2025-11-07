<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ludo Novo • Jogo</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div id="app">
    <div id="menu" class="screen">
      <h1>🎯 Ludo Novo</h1>
      <p>Tabuleiro diferente com novas mecânicas!</p>
      <div class="menu-buttons">
        <button onclick="createGame()">Criar Jogo</button>
        <button onclick="showJoinForm()">Entrar em Jogo</button>
      </div>
      <div id="join-form" style="display:none;">
        <input type="text" id="room-code" placeholder="Código da sala" maxlength="6">
        <button onclick="joinGame()">Entrar</button>
      </div>
    </div>

    <div id="game" class="screen" style="display:none;">
      <div class="game-header">
        <div class="game-info">
          <span id="room-display">Sala: </span>
          <span id="turn-display">Turno: </span>
        </div>
        <div class="game-controls">
          <button id="roll-btn" onclick="rollDice()" disabled>Rolar Dado</button>
          <button onclick="restartGame()">Reiniciar</button>
          <button onclick="backToMenu()">Voltar</button>
        </div>
      </div>

      <div class="game-content">
        <div class="board-container">
          <svg id="board" width="600" height="600"></svg>
        </div>
        
        <div class="sidebar">
          <div class="dice-area">
            <div id="dice-result">🎲</div>
            <div id="game-status">Aguardando jogadores...</div>
          </div>
          
          <div class="chat-area">
            <div id="chat-messages"></div>
            <div class="chat-input">
              <input type="text" id="chat-text" placeholder="Digite uma mensagem..." maxlength="100">
              <button onclick="sendChat()">Enviar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="ludo.js"></script>
</body>
</html>
