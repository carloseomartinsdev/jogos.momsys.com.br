$(function(){
  const API = window.PONTINHO_API || 'api_pontinho.php';

  // Estado local
  let rows = 4, cols = 4;
  let room = null, myToken = null, mySide = null, version = 0;
  let state = null; // {rows,cols,max_players,current,score:{A,B,C,D},edges:{h:{},v:{}},boxes[][],finished,version,players{A..D}}

  const COLORS = {
    A: {name:'Azul', css:'A'},
    B: {name:'Vermelho', css:'B'},
    C: {name:'Verde', css:'C'},
    D: {name:'Amarelo', css:'D'},
  };

  // DOM
  const $board = $('#board');
  const $log = $('#log');
  const $scoreA = $('#scoreA'), $scoreB = $('#scoreB'), $scoreC = $('#scoreC'), $scoreD = $('#scoreD');
  const $vez = $('#vez'), $txtRoom = $('#txtRoom'), $txtYou = $('#txtYou');
  const $share = $('#shareLink'), $linkSala = $('#linkSala');

  function log(msg){ $log.prepend($('<div/>').text(msg)); }
  function apiCall(data){ return $.ajax({ url: API, method: 'POST', data, dataType: 'json' }); }

  /* ===== Sala ===== */
  $('#btnCriar').on('click', function(){
    const size = ($('#gridSize').val()||'4x4').split('x').map(Number);
    const maxp = parseInt($('#playersCount').val(), 10) || 2;
    rows = size[0]; cols = size[1];
    apiCall({ action:'create', size: rows+'x'+cols, maxp: maxp }).done(function(res){
      if(!res.success){ alert(res.error||'Falha ao criar'); return; }
      room=res.room; myToken=res.token; mySide=res.you_side; version=res.state.version||0; state=res.state;
      enterGameUI(); buildFromState(); log('Sala criada. Compartilhe o link.');
    }).fail(function(){ alert('Erro de rede (create).'); });
  });

  $('#btnEntrar').on('click', function(){
    const code = ($('#roomCode').val()||'').trim().toUpperCase();
    if(!code) return alert('Informe o código da sala.');
    apiCall({ action:'join', room: code }).done(function(res){
      if(!res.success){ alert(res.error||'Falha ao entrar'); return; }
      room=res.room; myToken=res.token; mySide=res.you_side; version=res.state.version||0; state=res.state;
      rows=res.state.rows; cols=res.state.cols;
      enterGameUI(); buildFromState(); log('Conectado à sala.');
    }).fail(function(){ alert('Erro de rede (join).'); });
  });

  $('#btnReiniciarSala').on('click', function(){
    if(!room||!myToken) return;
    apiCall({ action:'restart', room, token: myToken }).done(function(res){
      if(!res.success){ alert(res.error||'Falha ao reiniciar'); return; }
      state=res.state; version=res.state.version; buildFromState(); log('Sala reiniciada.');
    }).fail(function(){ alert('Erro de rede (restart).'); });
  });

  /* ===== Polling ===== */
  let pollTimer=null;
  function poll(){
    if(!room) return;
    apiCall({ action:'poll', room, version }).done(function(res){
      if(res && res.success && res.update){
        state=res.state; version=res.state.version; buildFromState();
      }
    }).always(function(){ pollTimer=setTimeout(poll, 800); });
  }
  function enterGameUI(){
    $('#txtRoom').text(room);
    $('#txtYou').text(COLORS[mySide] ? COLORS[mySide].name : '—');
    if(pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, 300);
  }

  /* ===== Render ===== */
  function buildFromState(){
    if(!state) return;
    updateHUD();
    buildGrid();
  }

  function updateHUD(){
    $txtRoom.text(room||'—');
    $vez.text(COLORS[state.current] ? COLORS[state.current].name : '—');

    // Placar
    $scoreA.text(state.score.A);
    $scoreB.text(state.score.B);
    $scoreC.text(state.score.C);
    $scoreD.text(state.score.D);

    // Mostra/Esconde C e D conforme max_players
    if(state.max_players >= 3){ $('.hideC').show(); } else { $('.hideC').hide(); }
    if(state.max_players >= 4){ $('.hideD').show(); } else { $('.hideD').hide(); }

    // Link
    const url = new URL(window.location.href);
    url.searchParams.set('room', room||'');
    $share.attr('href', url.toString());
    $linkSala.text(url.toString());

    // Outline por vez
    $board.removeClass('turnA turnB turnC turnD')
          .addClass('turn'+(state.current||'A'));
  }

  function posX(c, dotCols){ return (c/(dotCols-1))*100; }
  function posY(r, dotRows){ return (r/(dotRows-1))*100; }
  function keyH(r,c){ return 'h:'+r+':'+c; }
  function keyV(r,c){ return 'v:'+r+':'+c; }
  function getEdgeOwner(o, r, c){
    var bag = (o==='h') ? state.edges.h : state.edges.v;
    var k = (o==='h') ? keyH(r,c) : keyV(r,c);
    return (bag && bag[k]) ? bag[k] : null;
  }

  function buildGrid(){
    $board.empty();
    const $grid = $('<div class="grid"/>').appendTo($board);

    const dotRows = state.rows+1, dotCols = state.cols+1;

    // Pontos (decorativos, clique passa para o traço)
    for(let r=0;r<dotRows;r++){
      for(let c=0;c<dotCols;c++){
        $('<div class="dot cell"/>').css({
          left: posX(c,dotCols)+'%',
          top:  posY(r,dotRows)+'%'
        }).appendTo($grid);
      }
    }

    // Traços horizontais
    for(let r=0;r<dotRows;r++){
      for(let c=0;c<dotCols-1;c++){
        const $e = $('<div class="edge h"/>')
          .attr({'data-o':'h','data-r':r,'data-c':c})
          .css({
            left:  posX(c, dotCols) + '%',
            top:   posY(r, dotRows) + '%',
            width: (100/(dotCols-1)) + '%',
            transform: 'translateY(-50%)'
          })
          .appendTo($grid);

        const owner = getEdgeOwner('h', r, c);
        if(owner){ $e.addClass('on block').addClass('own'+owner); }
      }
    }

    // Traços verticais
    for(let r=0;r<dotRows-1;r++){
      for(let c=0;c<dotCols;c++){
        const $e = $('<div class="edge v"/>')
          .attr({'data-o':'v','data-r':r,'data-c':c})
          .css({
            left:  posX(c, dotCols) + '%',
            top:   posY(r, dotRows) + '%',
            height:(100/(dotRows-1)) + '%',
            transform: 'translateX(-50%)'
          })
          .appendTo($grid);

        const owner = getEdgeOwner('v', r, c);
        if(owner){ $e.addClass('on block').addClass('own'+owner); }
      }
    }

    // Boxes
    for(let br=0;br<state.rows;br++){
      for(let bc=0;bc<state.cols;bc++){
        const own = state.boxes[br][bc];
        const $b = $('<div class="box cell"/>')
          .attr({'data-br':br,'data-bc':bc})
          .css({
            left: posX(bc,dotCols)+'%',
            top:  posY(br,dotRows)+'%',
            width:(100/(dotCols-1))+'%',
            height:(100/(dotRows-1))+'%',
          })
          .appendTo($grid);
        if(own==='A') $b.addClass('a');
        if(own==='B') $b.addClass('b');
        if(own==='C') $b.addClass('c');
        if(own==='D') $b.addClass('d');
      }
    }

    bindHandlers();
  }

  function bindHandlers(){
    $('.edge').off('click').on('click', function(){
      if(!room || !myToken) return;
      if(state.finished) return;
      if(mySide !== state.current) return;

      const $e = $(this);
      if($e.hasClass('on')) return;

      const o = $e.data('o'), r = +$e.data('r'), c = +$e.data('c');

      apiCall({ action:'move', room, token: myToken, o, r, c }).done(function(res){
        if(!res.success){
          log('Rejeitado: ' + (res.error||'jogada inválida'));
          if(res.state){ state=res.state; version=res.state.version||version; buildFromState(); }
          return;
        }
        state=res.state; version=res.state.version; buildFromState();
      }).fail(function(){
        log('Erro de rede ao enviar jogada.');
      });
    });
  }

  // Deep link ?room=CODE
  (function(){
    const params = new URLSearchParams(window.location.search);
    const code = params.get('room');
    if(code){ $('#roomCode').val(code); }
  })();
});
