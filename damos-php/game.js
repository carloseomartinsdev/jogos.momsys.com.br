/* Damas Online com sincronização via PHP (polling) */
$(function(){
  const API = window.CHECKERS_API || 'api.php';
  const ROWS = 8, COLS = 8;

  // Estado local
  let board = [];
  let current = 'r';
  let scores = { r: 12, b: 12 };
  let selected = null;

  // Rede
  let room = null;            // código da sala
  let myColor = null;         // 'r' ou 'b'
  let myToken = null;         // token do jogador
  let version = 0;            // versão do estado
  let pollTimer = null;

  const $board = $('#board');
  const $turno = $('#turnoAtual');
  const $scoreR = $('#scoreR'), $scoreB = $('#scoreB');
  const $log = $('#log');
  const $txtRoom = $('#txtRoom');
  const $txtYou = $('#txtYou');
  const $lobby = $('#lobby');
  const $game = $('#gameArea');
  const $share = $('#shareLink');

  // ---------- UI base ----------
  function log(msg){ $log.prepend($('<div/>').text(msg)); }
  function isDark(r,c){ return (r+c)%2 === 1; }
  function owner(piece){ return piece ? piece.toLowerCase() : null; }
  function isKing(p){ return p==='R' || p==='B'; }

  function resetLocal(){
    selected = null;
    render();
  }

  // ---------- Networking ----------
  function apiCall(data){
    return $.ajax({
      url: API,
      method: 'POST',
      data: data,
      dataType: 'json'
    });
  }

  function createRoom(){
    apiCall({ action:'create' }).done(res=>{
      if(!res.success){ alert(res.error||'Falha'); return; }
      room = res.room;
      myColor = res.you_color;   // 'r'
      myToken = res.token;       // token do host
      version = res.state.version || 0;
      setStateFromServer(res.state);

      enterGameUI();
      log('Sala criada. Aguardando o jogador Preto entrar...');
    }).fail(()=>alert('Erro ao criar sala.'));
  }

  function joinRoom(code){
    apiCall({ action:'join', room: code }).done(res=>{
      if(!res.success){ $('#joinInfo').text(res.error||'Falha ao entrar.'); return; }
      room = res.room;
      myColor = res.you_color; // 'b'
      myToken = res.token;
      version = res.state.version || 0;
      setStateFromServer(res.state);

      enterGameUI();
      log('Conectado. Você é o Preto.');
    }).fail(()=>$('#joinInfo').text('Erro de rede.'));
  }

  function restartRoom(){
    if(!room || !myToken){ return; }
    apiCall({ action:'restart', room, token: myToken }).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao reiniciar'); return; }
      setStateFromServer(res.state);
      version = res.state.version;
      render();
      log('Sala reiniciada.');
    }).fail(()=>alert('Erro de rede.'));
  }

  function poll(){
    if(!room){ return; }
    apiCall({ action:'poll', room, version }).done(res=>{
      if(!res.success){ return; }
      if(res.update){
        version = res.state.version;
        setStateFromServer(res.state);
        render();
      }
    }).always(()=>{
      // agenda próximo poll
      pollTimer = setTimeout(poll, 800);
    });
  }

  function sendMove(sr,sc,tr,tc){
    if(!room || !myToken) return;
    apiCall({
      action:'move',
      room,
      token: myToken,
      move: JSON.stringify({ sr, sc, tr, tc })
    }).done(res=>{
      if(!res.success){
        // Ex.: jogada inválida (servidor valida também)
        log('Servidor rejeitou a jogada: ' + (res.error||'inválida'));
        // atualiza estado do servidor (se outro move ocorreu no meio tempo)
        if(res.state){ setStateFromServer(res.state); version = res.state.version; render(); }
        return;
      }
      version = res.state.version;
      setStateFromServer(res.state);
      render();
    }).fail(()=>log('Erro de rede ao enviar jogada.'));
  }

  function enterGameUI(){
    $lobby.addClass('hidden');
    $game.removeClass('hidden');
    $txtRoom.text(room);
    $txtYou.text(myColor==='r'?'Vermelho':'Preto');
    const url = new URL(window.location.href);
    url.searchParams.set('room', room);
    $share.attr('href', url.toString());
    // iniciar polling
    if(pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, 300);
  }

  // ---------- Regras do jogo ----------
  function initialBoard(){
    const b = Array.from({length:ROWS}, ()=>Array(COLS).fill(null));
    for(let r=0;r<3;r++){
      for(let c=0;c<COLS;c++){
        if(isDark(r,c)) b[r][c] = 'b';
      }
    }
    for(let r=ROWS-3;r<ROWS;r++){
      for(let c=0;c<COLS;c++){
        if(isDark(r,c)) b[r][c] = 'r';
      }
    }
    return b;
  }

  function setStateFromServer(state){
    board = state.board;
    current = state.current;
    scores = state.scores;
    selected = null;
    updateHUD();
  }

  function updateHUD(){
    $('#turnoAtual').text(current==='r'?'Vermelho':'Preto');
    $scoreR.text(scores.r); $scoreB.text(scores.b);
  }

  function render(){
    $board.empty();
    const mustCapture = playerHasCapture(current, board);

    for(let r=0;r<ROWS;r++){
      for(let c=0;c<COLS;c++){
        const $sq = $('<div/>').addClass('square')
          .toggleClass('dark', isDark(r,c))
          .toggleClass('light', !isDark(r,c))
          .attr({'data-r':r, 'data-c':c});
        const piece = board[r][c];
        if(piece){
          const $p = $('<div/>').addClass('piece')
            .addClass(owner(piece)==='r'?'red':'black')
            .toggleClass('selected', selected && selected.r===r && selected.c===c)
            .appendTo($sq);
          if(isKing(piece)){
            $('<div/>').addClass('crown').html('&#9812;').appendTo($p);
          }
        }
        $board.append($sq);
      }
    }

    // Destaques de movimentos possíveis apenas se:
    //  - é minha vez
    //  - tenho uma peça selecionada minha
    if(selected && myColor === current){
      const moves = legalMoves(selected.r, selected.c, board, playerHasCapture(current, board));
      moves.forEach(m=>{
        const idx = m.tr*COLS + m.tc;
        const $sq = $board.children().eq(idx);
        $sq.addClass(m.capture?'hl-capture':'hl-move');
      });
    }

    bindBoardHandlers();
  }

  function bindBoardHandlers(){
    $('.square').off('click').on('click', function(){
      const r = +$(this).data('r');
      const c = +$(this).data('c');
      onSquareClick(r,c);
    });
  }

  function onSquareClick(r,c){
    // Só permite mexer se é minha vez
    if(myColor !== current){ return; }

    const piece = board[r][c];
    // Seleciona uma peça minha
    if(piece && owner(piece)===myColor){
      selected = { r, c };
      render();
      return;
    }

    // Se tenho uma selecionada, tentar mover
    if(selected){
      const mustCapture = playerHasCapture(current, board);
      const moves = legalMoves(selected.r, selected.c, board, mustCapture);
      const mv = moves.find(m=>m.tr===r && m.tc===c);
      if(mv){
        // Envia para o servidor validar, aplicar e propagar
        sendMove(selected.r, selected.c, mv.tr, mv.tc);
      }else{
        selected = null;
        render();
      }
    }
  }

  function dirs(piece){
    if(isKing(piece)) return [[-1,-1],[-1,1],[1,-1],[1,1]];
    return owner(piece)==='r' ? [[-1,-1],[-1,1]] : [[1,-1],[1,1]];
  }
  function inside(r,c){ return r>=0 && r<ROWS && c>=0 && c<COLS; }

  function legalMoves(r,c,state,mustCaptureOnly){
    const piece = state[r][c];
    if(!piece) return [];
    const me = owner(piece);
    const d = dirs(piece);
    const list = [];

    // Capturas
    d.forEach(([dr,dc])=>{
      const r1=r+dr, c1=c+dc;
      const r2=r+2*dr, c2=c+2*dc;
      if(inside(r2,c2) && state[r1][c1] && owner(state[r1][c1])!==me && !state[r2][c2]){
        list.push({tr:r2, tc:c2, capture:{r:r1,c:c1}});
      }
    });
    if(list.length>0) return list;

    if(!mustCaptureOnly){
      d.forEach(([dr,dc])=>{
        const r1=r+dr, c1=c+dc;
        if(inside(r1,c1) && !state[r1][c1]){
          list.push({tr:r1, tc:c1, capture:null});
        }
      });
    }
    return list;
  }

  function playerHasCapture(player,state){
    for(let r=0;r<ROWS;r++){
      for(let c=0;c<COLS;c++){
        const p = state[r][c];
        if(p && owner(p)===player){
          const ms = legalMoves(r,c,state,false);
          if(ms.some(x=>x.capture)) return true;
        }
      }
    }
    return false;
  }

  // ---------- Boot: link com ?room=CODE entra direto ----------
  $('#btnCriar').on('click', createRoom);
  $('#btnEntrar').on('click', ()=>{
    const code = ($('#roomCode').val()||'').trim().toUpperCase();
    if(!code) return $('#joinInfo').text('Informe o código da sala.');
    joinRoom(code);
  });
  $('#btnReiniciarSala').on('click', restartRoom);
  $('#btnNovoLocal').on('click', resetLocal);

  // Deep-link
  const urlParams = new URLSearchParams(window.location.search);
  const r = urlParams.get('room');
  if(r){ $('#roomCode').val(r); }

});
