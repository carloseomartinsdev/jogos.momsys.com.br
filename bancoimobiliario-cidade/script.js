class CityGame {
  constructor() {
    this.currentPlayer = 1;
    this.totalPlayers = 2;
    this.players = {};
    this.properties = {};
    this.playerPosition = { x: 1, y: 0 };
    this.direction = 'right';
    this.gameStarted = false;
    this.isMoving = false;
    this.pathCells = [];
    this.hasPlayerMoved = false;
    this.isAnimatingOtherPlayer = false;
    
    // Online game properties
    this.isOnline = false;
    this.roomId = null;
    this.playerId = null;
    this.playerName = null;
    this.pollInterval = null;
    
    // Referência global para debug
    window.cityGame = this;
    
    this.bindSetupEvents();
  }
  
  setupGame(playerCount) {
    this.totalPlayers = playerCount;
    this.players = {};
    
    for (let i = 1; i <= playerCount; i++) {
      this.players[i] = {
        money: 50000,
        properties: [],
        position: { x: 1, y: 0 },
        transactions: [{ type: 'Saldo Inicial', amount: 50000, balance: 50000, time: new Date().toLocaleTimeString() }]
      };
    }
    
    this.playerPosition = { ...this.players[this.currentPlayer].position };
    
    document.getElementById('setupScreen').style.display = 'none';
    document.getElementById('gameArea').style.display = 'grid';
    
    this.initializeCity();
    this.updatePlayerPosition();
    this.updateDisplay();
    this.bindEvents();
    this.gameStarted = true;
  }
  
  isStreet(row, col) {
    // Verificar se está dentro dos limites
    if (row < 0 || row >= 15 || col < 0 || col >= 13) return false;
    
    const cityMatrix = [
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R']
    ];
    return cityMatrix[row][col] === 'R';
  }
  
  isIntersection(row, col) {
    // Cruzamentos são onde ruas horizontais e verticais se encontram
    const horizontalStreets = [0, 4, 7, 11, 14];
    const verticalStreets = [0, 3, 6, 9, 12];
    
    return horizontalStreets.includes(row) && verticalStreets.includes(col);
  }

  initializeCity() {
    console.log('Inicializando cidade...');
    const grid = document.getElementById('cityGrid');
    
    if (!grid) {
      console.error('Grid não encontrado!');
      return;
    }
    
    const cityMatrix = [
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','Q','Q','R','Q','Q','R','Q','Q','R','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','Q','Q','Q','Q','Q','R','Q','Q','Q','Q','Q','R'],
      ['R','R','R','R','R','R','R','R','R','R','R','R','R']
    ];
    
    for (let row = 0; row < 15; row++) {
      for (let col = 0; col < 13; col++) {
        const cell = document.createElement('div');
        cell.dataset.row = row;
        cell.dataset.col = col;
        
        if (cityMatrix[row][col] === 'R') {
          if (this.isIntersection(row, col)) {
            cell.className = 'intersection';
          } else {
            cell.className = 'street';
          }
        } else {
          const propertyId = `${row}-${col}`;
          const propertyData = this.getPropertyData(row, col);
          
          cell.className = `property ${propertyData.colorClass}`;
          cell.textContent = propertyData.icon;
          cell.dataset.propertyId = propertyId;
          cell.addEventListener('click', () => this.showPropertyModal(this.properties[propertyId]));
          
          this.properties[propertyId] = {
            id: propertyId,
            name: propertyData.name,
            icon: propertyData.icon,
            price: propertyData.price,
            rent: propertyData.rent,
            color: propertyData.color,
            owner: null
          };
          
          if (Object.keys(this.properties).length <= 5) {
            console.log('Propriedade criada:', propertyId, this.properties[propertyId]);
          }
        }
        
        grid.appendChild(cell);
      }
    }
    
    console.log('Cidade inicializada com', Object.keys(this.properties).length, 'propriedades');
    this.updatePlayerPosition();
  }





  rollDice() {
    if (this.isMoving) {
      console.log('Já está em movimento');
      return;
    }
    
    // Check if it's player's turn in online mode
    if (this.isOnline && this.currentPlayer !== this.playerId) {
      alert('Não é sua vez!');
      return;
    }
    
    console.log('Rolando dados para jogador:', this.currentPlayer);
    
    const dice1 = Math.floor(Math.random() * 6) + 1;
    const dice2 = Math.floor(Math.random() * 6) + 1;
    const totalSteps = dice1 + dice2;
    
    console.log('Dados:', dice1, dice2, 'Total:', totalSteps);
    
    document.getElementById('moveDice1').textContent = dice1;
    document.getElementById('moveDice2').textContent = dice2;
    
    this.animatedMove(totalSteps);
  }

  async animatedMove(steps) {
    console.log('Iniciando movimento de', steps, 'passos');
    this.isMoving = true;
    this.pathCells = [];
    
    for (let i = 0; i < steps; i++) {
      console.log('Passo', i + 1, 'de', steps, 'Posição atual:', this.playerPosition);
      
      // Verificar se chegou em cruzamento antes de mover
      if (this.isIntersection(this.playerPosition.y, this.playerPosition.x) && i > 0) {
        const directionRoll = await this.showDirectionModal();
        this.changeDirection(directionRoll);
      }
      
      // Mover uma casa
      this.moveOneStep();
      console.log('Nova posição:', this.playerPosition);
      
      // Atualizar posição do jogador atual no objeto players
      if (this.players[this.currentPlayer]) {
        this.players[this.currentPlayer].position = { ...this.playerPosition };
      }
      
      // Destacar caminho
      this.highlightPath();
      
      // Atualizar posição de todos os jogadores para manter visibilidade
      this.updatePlayerPosition();
      
      // Aguardar animação
      await this.sleep(400);
    }
    

    
    // Limpar destaque após movimento
    setTimeout(() => {
      this.clearPathHighlight();
      this.isMoving = false;
      this.hasPlayerMoved = true; // Marcar que jogador se moveu
      console.log('Movimento concluído, verificando propriedades...');
      
      // Verificar propriedade após movimento
      console.log('Verificando propriedades...');
      console.log('Posição atual:', this.playerPosition);
      console.log('Total propriedades:', Object.keys(this.properties).length);
      
      if (Object.keys(this.properties).length === 0) {
        console.warn('Nenhuma propriedade disponível! Reinicializando cidade...');
        this.initializeCity();
      }
      
      const propertyAtPosition = this.getPropertyAtPosition();
      const adjacentProperty = this.getAdjacentProperty();
      
      console.log('Propriedade na posição:', propertyAtPosition);
      console.log('Propriedade adjacente:', adjacentProperty);
      
      const property = propertyAtPosition || adjacentProperty;
      if (property) {
        console.log('Propriedade encontrada:', property);
        if (property.owner && property.owner !== this.currentPlayer) {
          this.payRent(property);
        } else {
          this.showPropertyModal(property);
        }
      } else {
        console.log('Nenhuma propriedade encontrada');
        // Verificar manualmente algumas posições próximas
        for (let dy = -1; dy <= 1; dy++) {
          for (let dx = -1; dx <= 1; dx++) {
            const checkX = this.playerPosition.x + dx;
            const checkY = this.playerPosition.y + dy;
            const propId = `${checkY}-${checkX}`;
            if (this.properties[propId]) {
              console.log(`Propriedade encontrada em ${propId}:`, this.properties[propId]);
            }
          }
        }
      }
      
      // Turno só muda quando jogador clicar em "Passar Vez"
      
      // Sincronizar movimento com outros jogadores
      if (this.isOnline) {
        this.updateGameState({
          started: true,
          currentPlayer: this.currentPlayer,
          totalPlayers: this.totalPlayers,
          players: this.players,
          properties: this.properties,
          playerPosition: this.playerPosition,
          direction: this.direction,
          lastMovement: {
            playerId: this.currentPlayer,
            path: this.pathCells.map(cell => ({ x: parseInt(cell.dataset.col), y: parseInt(cell.dataset.row) })),
            timestamp: Date.now()
          }
        });
      }
    }, 1000);
    
    this.updateDisplay();
  }

  moveOneStep() {
    let newX = this.playerPosition.x;
    let newY = this.playerPosition.y;
    
    switch (this.direction) {
      case 'right':
        newX++;
        break;
      case 'down':
        newY++;
        break;
      case 'left':
        newX--;
        break;
      case 'up':
        newY--;
        break;
    }
    
    // Verificar limites e se é uma rua válida
    if (newX >= 0 && newX < 13 && newY >= 0 && newY < 15 && this.isStreet(newY, newX)) {
      this.playerPosition.x = newX;
      this.playerPosition.y = newY;
    }
    // Se não for válido, o peão para onde está
  }

  isValidDirection(roll) {
    const x = this.playerPosition.x;
    const y = this.playerPosition.y;
    const directions = ['right', 'down', 'left', 'up'];
    const currentIndex = directions.indexOf(this.direction);
    
    // Ordem de tentativa: direita, esquerda, em frente
    const tryDirections = [
      directions[(currentIndex + 1) % 4], // Direita
      directions[(currentIndex + 3) % 4], // Esquerda
      this.direction                       // Em frente
    ];
    
    let targetDirection;
    if (roll === 1 || roll === 4) {
      targetDirection = directions[(currentIndex + 3) % 4]; // Esquerda
    } else if (roll === 2 || roll === 5) {
      targetDirection = this.direction; // Frente
    } else {
      targetDirection = directions[(currentIndex + 1) % 4]; // Direita
    }
    
    // Verificar se a direção do dado é válida
    if (this.checkDirectionValid(x, y, targetDirection)) {
      return true;
    }
    
    // Se não for válida, tentar na ordem: direita, esquerda, em frente
    for (const direction of tryDirections) {
      if (this.checkDirectionValid(x, y, direction)) {
        // Atualizar direção para a primeira válida encontrada
        this.direction = direction;
        return true;
      }
    }
    
    return false;
  }
  
  checkDirectionValid(x, y, direction) {
    let newX = x, newY = y;
    switch (direction) {
      case 'right': newX++; break;
      case 'down': newY++; break;
      case 'left': newX--; break;
      case 'up': newY--; break;
    }
    
    return newX >= 0 && newX < 13 && newY >= 0 && newY < 15 && this.isStreet(newY, newX);
  }
  
  changeDirection(roll) {
    const directions = ['right', 'down', 'left', 'up'];
    const currentIndex = directions.indexOf(this.direction);
    
    if (roll === 1 || roll === 4) {
      // Esquerda
      this.direction = directions[(currentIndex + 3) % 4];
    } else if (roll === 2 || roll === 5) {
      // Frente (manter direção)
      // não muda
    } else if (roll === 3 || roll === 6) {
      // Direita
      this.direction = directions[(currentIndex + 1) % 4];
    }
  }

  updateSinglePlayerPosition(playerId) {
    document.querySelectorAll(`.player-${playerId}`).forEach(p => p.remove());
    
    const cells = document.querySelectorAll('.city-grid > div');
    const player = this.players[playerId];
    if (!player) return;
    
    const position = player.position || this.playerPosition;
    const targetCell = cells[position.y * 13 + position.x];
    
    if (targetCell) {
      const playerElement = document.createElement('div');
      playerElement.className = `player player-${playerId}`;
      targetCell.appendChild(playerElement);
    }
  }

  updatePlayerPosition() {
    console.log('Atualizando posições dos jogadores:', this.players);
    document.querySelectorAll('.player').forEach(p => p.remove());
    
    const cells = document.querySelectorAll('.city-grid > div');
    
    // Mostrar todos os jogadores com validação
    Object.keys(this.players).forEach(playerId => {
      const player = this.players[playerId];
      if (!player || !player.position) {
        console.warn(`Jogador ${playerId} sem dados válidos:`, player);
        return;
      }
      
      const position = player.position;
      // Validar coordenadas
      if (position.x < 0 || position.x >= 13 || position.y < 0 || position.y >= 15) {
        console.warn(`Posição inválida para jogador ${playerId}:`, position);
        return;
      }
      
      const cellIndex = position.y * 13 + position.x;
      const targetCell = cells[cellIndex];
      
      if (targetCell) {
        const playerElement = document.createElement('div');
        playerElement.className = `player player-${playerId}`;
        targetCell.appendChild(playerElement);
        console.log(`Peão do jogador ${playerId} criado na posição:`, position);
      } else {
        console.error(`Célula não encontrada para jogador ${playerId} no índice ${cellIndex}`);
      }
    });
  }

  selectProperty(propertyId, price) {
    const property = this.properties[propertyId];
    const details = document.getElementById('propertyDetails');
    const buyBtn = document.getElementById('buyProperty');
    
    if (property.owner) {
      details.innerHTML = `
        <strong>Propriedade: ${propertyId}</strong><br>
        Preço: R$ ${property.price}<br>
        Aluguel: R$ ${property.rent}<br>
        Dono: Jogador ${property.owner}
      `;
      buyBtn.style.display = 'none';
    } else {
      details.innerHTML = `
        <strong>Propriedade: ${propertyId}</strong><br>
        Preço: R$ ${property.price}<br>
        Aluguel: R$ ${property.rent}<br>
        Status: À venda
      `;
      buyBtn.style.display = 'block';
      buyBtn.onclick = () => this.buyProperty(propertyId);
    }
  }

  buyProperty(propertyId) {
    const property = this.properties[propertyId];
    const player = this.players[this.currentPlayer];
    
    if (player.money >= property.price) {
      player.money -= property.price;
      property.owner = this.currentPlayer;
      player.properties.push(propertyId);
      
      // Atualizar visual da propriedade
      const propertyElement = document.querySelector(`[data-property-id="${propertyId}"]`);
      if (propertyElement) {
        propertyElement.className = 'property owned';
        propertyElement.textContent = `P${this.currentPlayer}`;
      }
      
      this.updateDisplay();
      document.getElementById('buyProperty').style.display = 'none';
    } else {
      alert('Dinheiro insuficiente!');
    }
  }

  nextPlayer() {
    console.log('Passando turno do jogador', this.currentPlayer, 'posição atual:', this.playerPosition);
    
    this.currentPlayer = (this.currentPlayer % this.totalPlayers) + 1;
    
    // NÃO atualizar playerPosition aqui - cada jogador mantém sua própria posição
    console.log('Novo jogador:', this.currentPlayer);
    
    this.updateDisplay();
    
    // Sincronizar com jogo online sempre que houver mudança de turno
    if (this.isOnline) {
      this.updateGameState({
        started: true,
        currentPlayer: this.currentPlayer,
        totalPlayers: this.totalPlayers,
        players: this.players,
        properties: this.properties,
        playerPosition: this.playerPosition,
        direction: this.direction
      });
    }
  }

  updateDisplay() {
    if (!this.gameStarted) return;
    
    // Atualizar jogador da sessão
    this.updateSessionPlayer();
    
    // Atualizar vez do jogador
    const currentPlayerTurnEl = document.getElementById('currentPlayerTurn');
    if (currentPlayerTurnEl) {
      currentPlayerTurnEl.textContent = `Jogador ${this.currentPlayer}`;
    }
    
    // Atualizar informações de todos os jogadores
    this.updateAllPlayersInfo();
  }
  
  updateSessionPlayer() {
    const sessionPlayerEl = document.getElementById('sessionPlayer');
    if (sessionPlayerEl) {
      const sessionPlayerId = this.isOnline ? (this.playerId || '?') : 1;
      sessionPlayerEl.textContent = `Jogador ${sessionPlayerId}`;
      console.log('Atualizando jogador da sessão:', sessionPlayerId, 'Online:', this.isOnline, 'PlayerID:', this.playerId);
    }
  }
  
  updateAllPlayersInfo() {
    const allPlayersEl = document.getElementById('allPlayersData');
    if (!allPlayersEl) return;
    
    let playersHtml = '';
    Object.keys(this.players).forEach(playerId => {
      const player = this.players[playerId];
      const isActive = parseInt(playerId) === this.currentPlayer;
      const position = player.position || { x: 1, y: 0 };
      
      playersHtml += `
        <div class="player-card ${isActive ? 'active' : ''}">
          <div class="player-name">Jogador ${playerId}</div>
          <div class="player-stats">
            Dinheiro: R$ ${player.money || 0}<br>
            Posição: ${position.x}, ${position.y}<br>
            Propriedades: ${player.properties ? player.properties.length : 0}
          </div>
        </div>
      `;
    });
    
    allPlayersEl.innerHTML = playersHtml;
  }

  bindSetupEvents() {
    this.isOnline = true;
    
    document.getElementById('createRoom').addEventListener('click', () => {
      this.createRoom();
    });
    
    document.getElementById('joinRoom').addEventListener('click', () => {
      this.joinRoom();
    });
    
    document.getElementById('startOnlineGame').addEventListener('click', () => {
      this.startOnlineGame();
    });
  }
  
  switchMode(mode) {
    const localBtn = document.getElementById('localMode');
    const onlineBtn = document.getElementById('onlineMode');
    const localSetup = document.getElementById('localSetup');
    const onlineSetup = document.getElementById('onlineSetup');
    
    if (mode === 'local') {
      localBtn.classList.add('active');
      onlineBtn.classList.remove('active');
      localSetup.style.display = 'block';
      onlineSetup.style.display = 'none';
      this.isOnline = false;
    } else {
      onlineBtn.classList.add('active');
      localBtn.classList.remove('active');
      onlineSetup.style.display = 'block';
      localSetup.style.display = 'none';
      this.isOnline = true;
    }
  }
  
  async createRoom() {
    const playerName = document.getElementById('playerName').value.trim();
    const maxPlayers = parseInt(document.getElementById('playerCount').value);
    
    if (!playerName) {
      alert('Digite seu nome!');
      return;
    }
    
    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'create_room',
          maxPlayers: maxPlayers
        })
      });
      
      const data = await response.json();
      if (data.success) {
        this.roomId = data.roomId;
        this.playerName = playerName;
        await this.joinRoom(data.roomId);
      }
    } catch (error) {
      alert('Erro ao criar sala: ' + error.message);
    }
  }
  
  async joinRoom(roomId = null) {
    const playerName = document.getElementById('playerName').value.trim();
    const inputRoomId = roomId || document.getElementById('roomId').value.trim().toUpperCase();
    
    if (!playerName) {
      alert('Digite seu nome!');
      return;
    }
    
    if (!inputRoomId) {
      alert('Digite o código da sala!');
      return;
    }
    
    try {
      const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'join_room',
          roomId: inputRoomId,
          playerName: playerName
        })
      });
      
      const data = await response.json();
      if (data.success) {
        this.roomId = inputRoomId;
        this.playerId = data.playerId;
        this.playerName = playerName;
        console.log('Jogador conectado como:', this.playerId);
        this.updateSessionPlayer();
        this.showRoomInfo(data.room);
        this.startPolling();
      } else {
        alert(data.error);
      }
    } catch (error) {
      alert('Erro ao entrar na sala: ' + error.message);
    }
  }
  
  showRoomInfo(room) {
    document.getElementById('currentRoomId').textContent = room.id;
    document.getElementById('roomInfo').style.display = 'block';
    
    const playersList = document.getElementById('playersList');
    playersList.innerHTML = '';
    
    room.players.forEach(player => {
      const div = document.createElement('div');
      div.className = 'player-item';
      div.textContent = `Jogador ${player.id}: ${player.name}`;
      playersList.appendChild(div);
    });
    
    // Show start button only for player 1
    if (this.playerId === 1 && room.players.length >= 2) {
      document.getElementById('startOnlineGame').style.display = 'block';
    }
  }
  
  startPolling() {
    this.pollInterval = setInterval(async () => {
      try {
        const response = await fetch(`api.php?roomId=${this.roomId}`);
        const data = await response.json();
        
        if (data.success) {
          this.showRoomInfo(data.room);
          
          if (data.room.gameState.started && !this.gameStarted) {
            this.setupOnlineGame(data.room.gameState);
          } else if (this.gameStarted && !this.isMoving) {
            // Só sincronizar se não estiver em movimento
            this.syncGameState(data.room.gameState);
          }
        }
      } catch (error) {
        console.error('Polling error:', error);
      }
    }, 2000); // Aumentar intervalo para 2 segundos
  }
  
  async startOnlineGame() {
    // Obter número real de jogadores da sala
    const response = await fetch(`api.php?roomId=${this.roomId}`);
    const data = await response.json();
    const actualPlayerCount = data.room.players.length;
    
    // Inicializar jogadores com dados válidos
    const players = {};
    for (let i = 1; i <= actualPlayerCount; i++) {
      players[i] = {
        money: 50000,
        properties: [],
        position: { x: 1, y: 0 }
      };
    }
    
    const gameState = {
      started: true,
      currentPlayer: 1,
      totalPlayers: actualPlayerCount,
      players: players,
      properties: {},
      playerPosition: { x: 1, y: 0 },
      direction: 'right'
    };
    
    await this.updateGameState(gameState);
  }
  
  setupOnlineGame(gameState) {
    clearInterval(this.pollInterval);
    document.getElementById('setupScreen').style.display = 'none';
    document.getElementById('gameArea').style.display = 'grid';
    
    this.totalPlayers = gameState.totalPlayers || 2;
    this.currentPlayer = gameState.currentPlayer || 1;
    this.playerPosition = gameState.playerPosition || { x: 1, y: 0 };
    this.direction = gameState.direction || 'right';
    
    // Atualizar jogador da sessão imediatamente
    this.updateSessionPlayer();
    
    // Initialize players properly
    this.players = {};
    for (let i = 1; i <= this.totalPlayers; i++) {
      this.players[i] = {
        money: 50000,
        properties: [],
        position: { x: 1, y: 0 },
        transactions: [{ type: 'Saldo Inicial', amount: 50000, balance: 50000, time: new Date().toLocaleTimeString() }]
      };
      
      // Se existir dados do gameState, usar eles
      if (gameState.players && gameState.players[i]) {
        this.players[i] = { ...this.players[i], ...gameState.players[i] };
      }
    }
    
    console.log('Jogadores inicializados:', this.players);
    console.log('Propriedades após inicialização:', Object.keys(this.properties).length);
    
    this.playerPosition = { ...this.players[this.currentPlayer].position };
    
    this.initializeCity();
    this.updateDisplay();
    this.bindEvents();
    this.gameStarted = true;
    
    this.startPolling();
  }
  
  async updateGameState(gameState) {
    if (!this.isOnline) return;
    
    try {
      await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update_game',
          roomId: this.roomId,
          gameState: gameState
        })
      });
    } catch (error) {
      console.error('Error updating game state:', error);
    }
  }
  
  syncGameState(gameState) {
    let needsUpdate = false;
    
    if (gameState.currentPlayer !== this.currentPlayer) {
      console.log('Sincronizando turno:', this.currentPlayer, '->', gameState.currentPlayer);
      this.currentPlayer = gameState.currentPlayer;
      
      // NÃO sobrescrever playerPosition aqui
      console.log('Mantendo posição atual:', this.playerPosition);
      
      needsUpdate = true;
    }
    
    if (gameState.players && Object.keys(gameState.players).length > 0) {
      // Mesclar dados dos jogadores em vez de sobrescrever completamente
      Object.keys(gameState.players).forEach(playerId => {
        if (gameState.players[playerId]) {
          if (!this.players[playerId]) {
            this.players[playerId] = {
              money: 50000,
              properties: [],
              position: { x: 1, y: 0 }
            };
          }
          
          // Só atualizar posição se não for o jogador atual em movimento
          if (gameState.players[playerId].position && playerId != this.currentPlayer) {
            this.players[playerId].position = { ...gameState.players[playerId].position };
          }
          
          if (gameState.players[playerId].money !== undefined) {
            this.players[playerId].money = gameState.players[playerId].money;
          }
          if (gameState.players[playerId].properties) {
            this.players[playerId].properties = [...gameState.players[playerId].properties];
          }
        }
      });
      needsUpdate = true;
    }
    
    if (gameState.properties && Object.keys(gameState.properties).length > 0) {
      // Só sobrescrever se o gameState tiver propriedades
      this.properties = gameState.properties;
      this.updatePropertiesDisplay();
    }
    
    // Sincronizar movimento de outros jogadores
    if (gameState.lastMovement && gameState.lastMovement.playerId !== this.playerId && !this.isMoving) {
      this.animateOtherPlayerMovement(gameState.lastMovement);
    }
    
    if (needsUpdate) {
      this.updateDisplay();
      
      // Só atualizar posições se não estiver animando movimento de outro jogador
      if (!this.isAnimatingOtherPlayer) {
        this.updatePlayerPosition();
      }
      
      // Verificar se os peões foram criados corretamente
      setTimeout(() => {
        if (!this.isAnimatingOtherPlayer) {
          const visiblePlayers = document.querySelectorAll('.player').length;
          const expectedPlayers = Object.keys(this.players).length;
          if (visiblePlayers < expectedPlayers) {
            console.warn(`Peões faltando: ${visiblePlayers}/${expectedPlayers}. Restaurando...`);
            this.updatePlayerPosition();
          }
        }
      }, 100);
    }
  }
  
  updatePropertiesDisplay() {
    Object.keys(this.properties).forEach(propertyId => {
      const property = this.properties[propertyId];
      const element = document.querySelector(`[data-property-id="${propertyId}"]`);
      if (element && property.owner) {
        element.className = 'property owned';
        element.textContent = `P${property.owner}`;
      }
    });
  }
  
  bindEvents() {
    document.getElementById('rollDice').addEventListener('click', () => this.rollDice());
    document.getElementById('showStatement').addEventListener('click', () => this.showBankStatement());
    document.getElementById('showProperties').addEventListener('click', () => this.showPlayerProperties());
    document.getElementById('passTurn').addEventListener('click', () => this.passTurn());
  }
  
  showPlayerProperties() {
    const sessionPlayerId = this.isOnline ? this.playerId : 1;
    const player = this.players[sessionPlayerId];
    
    if (!player || !player.properties || player.properties.length === 0) {
      alert('Você não possui propriedades ainda.');
      return;
    }
    
    const modal = document.createElement('div');
    modal.className = 'property-modal';
    
    const content = document.createElement('div');
    content.className = 'property-content';
    content.style.maxWidth = '500px';
    
    let propertiesHtml = '';
    let totalValue = 0;
    
    player.properties.forEach(propertyId => {
      const property = this.properties[propertyId];
      if (property) {
        totalValue += property.price;
        propertiesHtml += `
          <div style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #2a2e38;">
            <div>
              <div style="font-weight: 600;">${property.icon} ${property.name}</div>
              <div style="font-size: 12px; color: #9aa4b2;">Grupo: ${property.color}</div>
            </div>
            <div style="text-align: right;">
              <div style="color: #22c55e; font-weight: 600;">R$ ${property.price}</div>
              <div style="font-size: 12px; color: #9aa4b2;">Aluguel: R$ ${property.rent}</div>
            </div>
          </div>
        `;
      }
    });
    
    content.innerHTML = `
      <h3>🏠 Minhas Propriedades - Jogador ${sessionPlayerId}</h3>
      <div style="margin: 15px 0; padding: 10px; background: #0f1117; border-radius: 8px;">
        <div style="font-weight: 600; color: #22c55e;">Total de Propriedades: ${player.properties.length}</div>
        <div style="font-weight: 600; color: #3b82f6;">Valor Total: R$ ${totalValue}</div>
      </div>
      <div style="max-height: 300px; overflow-y: auto;">
        ${propertiesHtml}
      </div>
      <div style="margin-top: 20px; text-align: center;">
        <button class="btn btn-secondary" id="closePropertiesBtn">Fechar</button>
      </div>
    `;
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    document.getElementById('closePropertiesBtn').addEventListener('click', () => {
      document.body.removeChild(modal);
    });
  }
  
  passTurn() {
    if (this.isMoving) return;
    
    if (this.isOnline && this.currentPlayer !== this.playerId) {
      alert('Não é sua vez!');
      return;
    }
    
    // Adicionar verificação se jogador já se moveu
    if (!this.hasPlayerMoved) {
      alert('Você precisa rolar os dados primeiro!');
      return;
    }
    
    this.hasPlayerMoved = false; // Reset para próximo turno
    this.nextPlayer();
  }
  
  showBankStatement() {
    const sessionPlayerId = this.isOnline ? this.playerId : 1;
    const player = this.players[sessionPlayerId];
    if (!player || !player.transactions) {
      alert('Nenhuma informação de extrato disponível');
      return;
    }
    
    const modal = document.createElement('div');
    modal.className = 'property-modal';
    
    const content = document.createElement('div');
    content.className = 'property-content';
    content.style.maxWidth = '500px';
    
    let transactionsHtml = '';
    player.transactions.slice(-10).reverse().forEach(t => {
      const color = t.amount >= 0 ? '#22c55e' : '#ef4444';
      transactionsHtml += `
        <div style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #2a2e38;">
          <div>
            <div style="font-weight: 600;">${t.type}</div>
            <div style="font-size: 12px; color: #9aa4b2;">${t.time}</div>
          </div>
          <div style="text-align: right;">
            <div style="color: ${color}; font-weight: 600;">${t.amount >= 0 ? '+' : ''}R$ ${t.amount}</div>
            <div style="font-size: 12px; color: #9aa4b2;">Saldo: R$ ${t.balance}</div>
          </div>
        </div>
      `;
    });
    
    content.innerHTML = `
      <h3>💳 Extrato Bancário - Jogador ${sessionPlayerId}</h3>
      <div style="margin: 15px 0; padding: 10px; background: #0f1117; border-radius: 8px;">
        <div style="font-weight: 600; color: #22c55e;">Saldo Atual: R$ ${player.money}</div>
      </div>
      <div style="max-height: 300px; overflow-y: auto;">
        ${transactionsHtml || '<div style="text-align: center; color: #9aa4b2;">Nenhuma transação encontrada</div>'}
      </div>
      <div style="margin-top: 20px; text-align: center;">
        <button class="btn btn-secondary" id="closeStatementBtn">Fechar</button>
      </div>
    `;
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    document.getElementById('closeStatementBtn').addEventListener('click', () => {
      document.body.removeChild(modal);
    });
  }
  
  highlightPath() {
    const cells = document.querySelectorAll('.city-grid > div');
    const currentCell = cells[this.playerPosition.y * 13 + this.playerPosition.x];
    
    if (currentCell && (currentCell.classList.contains('street') || currentCell.classList.contains('intersection'))) {
      currentCell.classList.add('path-highlight');
      this.pathCells.push(currentCell);
    }
  }
  
  clearPathHighlight() {
    this.pathCells.forEach(cell => {
      cell.classList.remove('path-highlight');
    });
    this.pathCells = [];
  }
  
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
  
  async animateOtherPlayerMovement(movement) {
    if (!movement.path || movement.path.length === 0) return;
    
    this.isAnimatingOtherPlayer = true;
    console.log('Animando movimento do jogador', movement.playerId);
    
    // Animar peão se movendo pelo caminho
    for (let i = 0; i < movement.path.length; i++) {
      const position = movement.path[i];
      
      // Atualizar posição do jogador que se moveu
      this.players[movement.playerId].position = { x: position.x, y: position.y };
      
      // Destacar caminho
      const cells = document.querySelectorAll('.city-grid > div');
      const cellIndex = position.y * 13 + position.x;
      const cell = cells[cellIndex];
      
      if (cell && (cell.classList.contains('street') || cell.classList.contains('intersection'))) {
        cell.classList.add('path-highlight');
      }
      
      // Atualizar posição visual do peão
      this.updatePlayerPosition();
      
      await this.sleep(300);
    }
    
    // Limpar destaque após animação
    setTimeout(() => {
      document.querySelectorAll('.path-highlight').forEach(cell => {
        cell.classList.remove('path-highlight');
      });
      this.isAnimatingOtherPlayer = false;
    }, 1000);
  }
  
  getPropertyData(row, col) {
    const properties = [
      // Marrom - Casas simples
      {name: 'Casa Simples', icon: '🏠', price: 600, rent: 50, color: 'Marrom', colorClass: 'brown'},
      {name: 'Casa com Jardim', icon: '🏡', price: 600, rent: 50, color: 'Marrom', colorClass: 'brown'},
      
      // Azul Claro - Casas de praia
      {name: 'Resort de Praia', icon: '🏖️', price: 1000, rent: 80, color: 'Azul Claro', colorClass: 'light-blue'},
      {name: 'Casa de Praia', icon: '🏠', price: 1000, rent: 80, color: 'Azul Claro', colorClass: 'light-blue'},
      {name: 'Chalé Costeiro', icon: '🏡', price: 1200, rent: 100, color: 'Azul Claro', colorClass: 'light-blue'},
      
      // Rosa - Lojas e comércio
      {name: 'Loja de Conveniência', icon: '🏪', price: 1400, rent: 110, color: 'Rosa', colorClass: 'pink'},
      {name: 'Edifício Comercial', icon: '🏢', price: 1400, rent: 110, color: 'Rosa', colorClass: 'pink'},
      {name: 'Shopping Center', icon: '🏬', price: 1600, rent: 130, color: 'Rosa', colorClass: 'pink'},
      
      // Laranja - Prédios residenciais
      {name: 'Apartamento Residencial', icon: '🏢', price: 1800, rent: 140, color: 'Laranja', colorClass: 'orange'},
      {name: 'Condomínio Fechado', icon: '🏢', price: 1800, rent: 140, color: 'Laranja', colorClass: 'orange'},
      {name: 'Edifício Residencial', icon: '🏢', price: 2000, rent: 160, color: 'Laranja', colorClass: 'orange'},
      
      // Vermelho - Hotéis e resorts
      {name: 'Hotel de Luxo', icon: '🏨', price: 2200, rent: 180, color: 'Vermelho', colorClass: 'red'},
      {name: 'Resort Executivo', icon: '🏨', price: 2200, rent: 180, color: 'Vermelho', colorClass: 'red'},
      {name: 'Complexo Turístico', icon: '🏖️', price: 2400, rent: 200, color: 'Vermelho', colorClass: 'red'},
      
      // Amarelo - Indústrias
      {name: 'Fábrica Têxtil', icon: '🏭', price: 2600, rent: 220, color: 'Amarelo', colorClass: 'yellow'},
      {name: 'Indústria Química', icon: '🏭', price: 2600, rent: 220, color: 'Amarelo', colorClass: 'yellow'},
      {name: 'Complexo Industrial', icon: '🏭', price: 2800, rent: 240, color: 'Amarelo', colorClass: 'yellow'},
      
      // Verde - Parques e áreas verdes
      {name: 'Parque Ecológico', icon: '🌳', price: 3000, rent: 260, color: 'Verde', colorClass: 'green'},
      {name: 'Reserva Natural', icon: '🏞️', price: 3000, rent: 260, color: 'Verde', colorClass: 'green'},
      {name: 'Floresta Urbana', icon: '🌲', price: 3200, rent: 280, color: 'Verde', colorClass: 'green'},
      
      // Azul - Arranha-céus e escritórios
      {name: 'Torre Corporativa', icon: '🏢', price: 3500, rent: 300, color: 'Azul', colorClass: 'blue'},
      {name: 'Centro Empresarial', icon: '🏬', price: 4000, rent: 350, color: 'Azul', colorClass: 'blue'}
    ];
    
    const index = (row * 13 + col) % properties.length;
    return properties[index];
  }
  
  getPropertyAtPosition() {
    const x = this.playerPosition.x;
    const y = this.playerPosition.y;
    const propertyId = `${y}-${x}`;
    
    if (this.properties[propertyId]) {
      console.log('Found property at current position:', propertyId);
      return this.properties[propertyId];
    }
    return null;
  }
  

  
  getAdjacentProperty() {
    const x = this.playerPosition.x;
    const y = this.playerPosition.y;
    
    const adjacentPositions = [
      { x: x + 1, y: y },
      { x: x - 1, y: y },
      { x: x, y: y + 1 },
      { x: x, y: y - 1 },
      { x: x + 1, y: y + 1 },
      { x: x - 1, y: y - 1 },
      { x: x + 1, y: y - 1 },
      { x: x - 1, y: y + 1 }
    ];
    
    for (const pos of adjacentPositions) {
      if (pos.x >= 0 && pos.x < 13 && pos.y >= 0 && pos.y < 15) {
        const propertyId = `${pos.y}-${pos.x}`;
        if (this.properties[propertyId]) {
          return this.properties[propertyId];
        }
      }
    }
    
    return null;
  }
  
  isPlayerAdjacentToProperty(propertyId) {
    const [propRow, propCol] = propertyId.split('-').map(Number);
    const playerX = this.playerPosition.x;
    const playerY = this.playerPosition.y;
    
    // Verificar se está na mesma posição ou adjacente
    if (playerX === propCol && playerY === propRow) {
      return true;
    }
    
    const adjacentPositions = [
      { x: propCol + 1, y: propRow },
      { x: propCol - 1, y: propRow },
      { x: propCol, y: propRow + 1 },
      { x: propCol, y: propRow - 1 },
      { x: propCol + 1, y: propRow + 1 },
      { x: propCol - 1, y: propRow - 1 },
      { x: propCol + 1, y: propRow - 1 },
      { x: propCol - 1, y: propRow + 1 }
    ];
    
    return adjacentPositions.some(pos => pos.x === playerX && pos.y === playerY);
  }
  
  showPropertyModal(property) {
    if (!property) return;
    
    const modal = document.createElement('div');
    modal.className = 'property-modal';
    
    const ownerText = property.owner ? `Jogador ${property.owner}` : 'Disponível';
    const statusColor = property.owner ? '#ef4444' : '#22c55e';
    const canBuy = !property.owner && this.gameStarted && this.isPlayerAdjacentToProperty(property.id);
    
    const content = document.createElement('div');
    content.className = 'property-content';
    content.innerHTML = `
      <h3>${property.icon} ${property.name}</h3>
      <div class="property-info">
        <div><span class="label">Grupo:</span><span class="value">${property.color}</span></div>
        <div><span class="label">Preço:</span><span class="value">R$ ${property.price}</span></div>
        <div><span class="label">Aluguel:</span><span class="value">R$ ${property.rent}</span></div>
        <div><span class="label">Status:</span><span class="value" style="color: ${statusColor}">${ownerText}</span></div>
      </div>
      <div class="property-buttons">
        ${canBuy ? `<button class="btn btn-success" id="buyPropertyBtn">Comprar por R$ ${property.price}</button>` : ''}
        <button class="btn btn-secondary" id="closePropertyBtn">Fechar</button>
      </div>
    `;
    
    modal.appendChild(content);
    document.body.appendChild(modal);
    
    // Event listeners
    document.getElementById('closePropertyBtn').addEventListener('click', () => {
      document.body.removeChild(modal);
    });
    
    if (canBuy) {
      document.getElementById('buyPropertyBtn').addEventListener('click', () => {
        this.buyPropertyFromModal(property.id);
        document.body.removeChild(modal);
      });
    }
  }
  
  payRent(property) {
    const currentPlayerData = this.players[this.currentPlayer];
    const ownerData = this.players[property.owner];
    const rentAmount = property.rent;
    
    console.log(`Cobrando aluguel: Jogador ${this.currentPlayer} (R$ ${currentPlayerData.money}) paga R$ ${rentAmount} para Jogador ${property.owner} (R$ ${ownerData.money})`);
    
    if (currentPlayerData.money >= rentAmount) {
      currentPlayerData.money -= rentAmount;
      ownerData.money += rentAmount;
      
      console.log(`Após pagamento: Jogador ${this.currentPlayer} = R$ ${currentPlayerData.money}, Jogador ${property.owner} = R$ ${ownerData.money}`);
      
      // Registrar transações
      if (!currentPlayerData.transactions) {
        currentPlayerData.transactions = [{ type: 'Saldo Inicial', amount: 50000, balance: 50000, time: new Date().toLocaleTimeString() }];
      }
      if (!ownerData.transactions) {
        ownerData.transactions = [{ type: 'Saldo Inicial', amount: 50000, balance: 50000, time: new Date().toLocaleTimeString() }];
      }
      
      currentPlayerData.transactions.push({
        type: `Aluguel - ${property.name}`,
        amount: -rentAmount,
        balance: currentPlayerData.money,
        time: new Date().toLocaleTimeString()
      });
      
      ownerData.transactions.push({
        type: `Recebido - ${property.name}`,
        amount: rentAmount,
        balance: ownerData.money,
        time: new Date().toLocaleTimeString()
      });
      
      console.log('Transação registrada para jogador', this.currentPlayer, ':', currentPlayerData.transactions[currentPlayerData.transactions.length - 1]);
      console.log('Transação registrada para jogador', property.owner, ':', ownerData.transactions[ownerData.transactions.length - 1]);
      
      // Mostrar mensagem de aluguel
      const message = document.createElement('div');
      message.style.cssText = `
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
        background: #ef4444; color: white; padding: 10px 20px;
        border-radius: 8px; z-index: 1000; font-weight: 600;
      `;
      message.textContent = `Você pagou R$ ${rentAmount} de aluguel para o Jogador ${property.owner}!`;
      document.body.appendChild(message);
      
      setTimeout(() => {
        if (document.body.contains(message)) {
          document.body.removeChild(message);
        }
      }, 3000);
      
      this.updateDisplay();
      this.updateAllPlayersInfo();
      
      // Sync with online game
      if (this.isOnline) {
        this.updateGameState({
          started: true,
          currentPlayer: this.currentPlayer,
          totalPlayers: this.totalPlayers,
          players: this.players,
          properties: this.properties,
          playerPosition: this.playerPosition,
          direction: this.direction
        });
      }
    } else {
      alert(`Você não tem dinheiro suficiente para pagar o aluguel de R$ ${rentAmount}!`);
    }
  }
  
  async buyPropertyFromModal(propertyId) {
    const property = this.properties[propertyId];
    const player = this.players[this.currentPlayer];
    
    if (player.money >= property.price) {
      player.money -= property.price;
      property.owner = this.currentPlayer;
      player.properties.push(propertyId);
      
      // Registrar transação
      if (!player.transactions) player.transactions = [];
      player.transactions.push({
        type: `Compra - ${property.name}`,
        amount: -property.price,
        balance: player.money,
        time: new Date().toLocaleTimeString()
      });
      
      // Atualizar visual da propriedade
      const propertyElement = document.querySelector(`[data-property-id="${propertyId}"]`);
      if (propertyElement) {
        propertyElement.className = 'property owned';
        propertyElement.textContent = `P${this.currentPlayer}`;
      }
      
      this.updateDisplay();
      
      // Sync with online game
      if (this.isOnline) {
        await this.updateGameState({
          started: true,
          currentPlayer: this.currentPlayer,
          totalPlayers: this.totalPlayers,
          players: this.players,
          properties: this.properties,
          playerPosition: this.playerPosition,
          direction: this.direction
        });
      }
      
      // Mostrar mensagem de sucesso
      const message = document.createElement('div');
      message.style.cssText = `
        position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
        background: #22c55e; color: white; padding: 10px 20px;
        border-radius: 8px; z-index: 1000; font-weight: 600;
      `;
      message.textContent = `Propriedade comprada por R$ ${property.price}!`;
      document.body.appendChild(message);
      
      setTimeout(() => {
        if (document.body.contains(message)) {
          document.body.removeChild(message);
        }
      }, 2000);
    } else {
      alert('Dinheiro insuficiente!');
    }
  }
  
  showDirectionModal() {
    return new Promise((resolve) => {
      const rollDice = () => {
        const modal = document.createElement('div');
        modal.className = 'direction-modal';
        
        const content = document.createElement('div');
        content.className = 'direction-content';
        content.innerHTML = `
          <h3>🚗 Cruzamento!</h3>
          <p>Rolando dado de direção...</p>
          <div class="direction-dice" id="modalDice">🎲</div>
        `;
        
        modal.appendChild(content);
        document.body.appendChild(modal);
        
        // Rolar automaticamente após 1 segundo
        setTimeout(() => {
          const roll = Math.floor(Math.random() * 6) + 1;
          
          if (!this.isValidDirection(roll)) {
            document.getElementById('modalDice').textContent = roll;
            
            const invalidText = document.createElement('p');
            invalidText.style.cssText = 'margin: 10px 0; font-size: 16px; font-weight: 600; color: #ef4444;';
            invalidText.textContent = 'Direção inválida! Rolando novamente...';
            content.appendChild(invalidText);
            
            setTimeout(() => {
              document.body.removeChild(modal);
              rollDice(); // Rolar novamente
            }, 1500);
            return;
          }
          
          const direction = (roll === 1 || roll === 4) ? 'Esquerda ⬅️' : 
                           (roll === 2 || roll === 5) ? 'Em Frente ⬆️' : 'Direita ➡️';
          
          document.getElementById('modalDice').textContent = roll;
          
          const directionText = document.createElement('p');
          directionText.style.cssText = 'margin: 10px 0; font-size: 16px; font-weight: 600; color: #22c55e;';
          directionText.textContent = direction;
          content.appendChild(directionText);
          
          setTimeout(() => {
            document.body.removeChild(modal);
            resolve(roll);
          }, 1500);
        }, 1000);
      };
      
      rollDice();
    });
  }
}

// Inicializar jogo quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
  const game = new CityGame();
  
  // Função para garantir que os peões sejam sempre visíveis
  setInterval(() => {
    if (game && game.gameStarted && !game.isMoving) {
      const players = document.querySelectorAll('.player');
      if (players.length === 0 && Object.keys(game.players).length > 0) {
        console.log('Peões desapareceram, restaurando...');
        game.updatePlayerPosition();
      }
    }
  }, 3000);
});