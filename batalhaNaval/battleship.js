$(function(){
  const API = window.BS_API || 'api_battleship.php';

  let rows = 10, cols = 10;
  let room = null, token = null, me = null, version = 0, chatLastId = 0;
  let state = null; // visão do cliente

  // alvo atual (oponente selecionado para atirar)
  let target = null;

  const COLORS = { A:'Azul', B:'Vermelho', C:'Verde', D:'Amarelo' };

  // DOM
  const $own = $('#gridOwn'), $opp = $('#gridOpp'), $tabs = $('#opTabs');
  const $log = $('#log');
  const $txtRoom = $('#txtRoom'), $txtYou = $('#txtYou'), $txtTurn = $('#txtTurn'), $aliveList = $('#aliveList');
  const $share = $('#shareLink'), $linkSala = $('#linkSala');
  const $statusPlace = $('#statusPlace');
  const $chatLog = $('#chatLog'), $chatMsg = $('#chatMsg');

  function log(msg){ $log.prepend($('<div/>').text(msg)); }
  function api(payload){ return $.ajax({ url: API, method:'POST', data: payload, dataType:'json' }); }

  function gridEl($g, n){
    $g.css('--n', n).empty();
    for(let i=0;i<n*n;i++){
      $('<div class="cell"/>').attr('data-i', i).appendTo($g);
    }
  }

  /* ========== SALAS ========== */
  $('#btnCriar').on('click', function(){
    const size = ($('#gridSize').val()||'10x10').split('x').map(Number);
    const maxp = parseInt($('#playersCount').val(),10) || 2;
    rows = size[0]; cols = size[1];
    api({action:'create', size: rows+'x'+cols, maxp}).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao criar'); return; }
      room=res.room; token=res.token; me=res.you_side; version=res.state.version||0; state=res.state;
      chatLastId = res.last_chat_id || 0;
      enterGame();
      log('Sala criada. Posicione os navios e clique "Estou pronto".');
    }).fail(()=>alert('Erro de rede (create).'));
  });

  $('#btnEntrar').on('click', function(){
    const code = ($('#roomCode').val()||'').trim().toUpperCase();
    if(!code) return alert('Informe o código.');
    api({action:'join', room:code}).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao entrar'); return; }
      room=res.room; token=res.token; me=res.you_side; version=res.state.version||0; state=res.state;
      rows=state.rows; cols=state.cols;
      chatLastId = res.last_chat_id || 0;
      enterGame();
      log('Conectado. Posicione os navios e clique "Estou pronto".');
    }).fail(()=>alert('Erro de rede (join).'));
  });

  $('#btnRestart').on('click', function(){
    if(!room||!token) return;
    api({action:'restart', room, token}).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao reiniciar'); return; }
      state=res.state; version=state.version; chatLastId = res.last_chat_id || 0;
      buildBoards();
      buildOpponentTabs();
      log('Sala reiniciada.');
    });
  });

  /* ========== POLLING (estado + chat) ========== */
  let pollTimer=null;
  function poll(){
    if(!room) return;
    api({action:'poll', room, token, version, last_chat_id: chatLastId}).done(res=>{
      if(res && res.success){
        if(res.update){
          state = res.state; version = state.version;
          buildBoards();
          buildOpponentTabs();
        }
        // chat
        if(Array.isArray(res.chat) && res.chat.length){
          appendChat(res.chat);
          chatLastId = res.chat[res.chat.length-1].id;
        }
      }
    }).always(()=>{ pollTimer=setTimeout(poll, 900); });
  }

  function enterGame(){
    $txtRoom.text(room);
    gridEl($own, rows); gridEl($opp, cols);
    updateHUD();
    deepLink();
    if(pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, 300);
    bindHandlers();
    buildOpponentTabs();
  }

  function deepLink(){
    const url = new URL(window.location.href);
    url.searchParams.set('room', room||'');
    $share.attr('href', url.toString());
    $linkSala.text(url.toString());
    $('#roomCode').val(room||'');
  }

  /* ========== POSICIONAR / PRONTO ========== */
  $('#btnRand').on('click', function(){
    if(!room||!token) return;
    api({action:'place_random', room, token}).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao posicionar'); return; }
      state=res.state; version=state.version; buildBoards();
      log('Navios posicionados aleatoriamente.');
    });
  });

  $('#btnReady').on('click', function(){
    if(!room||!token) return;
    api({action:'ready', room, token}).done(res=>{
      if(!res.success){ alert(res.error||'Falha ao ficar pronto'); return; }
      state=res.state; version=state.version; buildBoards();
      log('Você está pronto.');
    });
  });

  /* ========== CHAT ========== */
  $('#chatSend').on('click', sendChat);
  $chatMsg.on('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); sendChat(); }});
  function sendChat(){
    const text = ($chatMsg.val()||'').trim();
    if(!text || !room || !token) return;
    api({action:'chat_send', room, token, text}).done(res=>{
      if(res && res.success){
        // mensagens virão no próximo poll; mas já ecoamos localmente:
        if(Array.isArray(res.chat) && res.chat.length){
          appendChat(res.chat);
          chatLastId = res.chat[res.chat.length-1].id;
        }
        $chatMsg.val('');
      }
    });
  }
  function appendChat(items){
    for(const m of items){
      const who = COLORS[m.who] || m.who;
      const $line = $('<div/>').html(
        `<span style="color:var(--${m.who||'A'})">[${who}]</span> <span class="muted small">${fmtTime(m.ts)}</span>: ${escapeHtml(m.text)}`
      );
      $chatLog.append($line);
    }
    $chatLog.scrollTop($chatLog[0].scrollHeight);
  }
  function fmtTime(ts){
    const d=new Date(ts*1000);
    return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
  }
  function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  /* ========== TIRO ========== */
  function bindHandlers(){
    $opp.off('click').on('click','.cell', function(){
      if(!state || !room || !token) return;
      if(!state.both_ready){ log('Aguardando todos prontos...'); return; }
      if(state.finished){ return; }
      if(state.turn !== me){ return; }
      if(!target){ log('Selecione um alvo acima.'); return; }
      if(!state.alive[target]){ log('Esse alvo já foi eliminado.'); return; }

      const i = +$(this).data('i');
      const r = Math.floor(i/cols), c = i%cols;

      api({action:'shoot', room, token, r, c, target}).done(res=>{
        if(!res.success){
          if(res.error) log('Falha: '+res.error);
          if(res.state){ state=res.state; version=state.version; buildBoards(); buildOpponentTabs(); }
          return;
        }
        const prevOppMask = state.opp_masks[target]; // antes do update (para animar hit)
        state=res.state; version=state.version;
        buildBoards();
        buildOpponentTabs();
        // se houve acerto na célula atual, explode:
        tryAnimateHit(prevOppMask, state.opp_masks[target], r, c);
      });
    });
  }

  function tryAnimateHit(prevMask, newMask, r, c){
    if(!prevMask || !newMask) return;
    if(newMask[r][c]==='H'){ // novo estado é hit
      const idx = r*cols + c;
      const $cell = $opp.find(`.cell[data-i="${idx}"]`);
      const $boom = $('<div class="explosion"></div>');
      $cell.append($boom);
      setTimeout(()=>{ $boom.remove(); }, 700);
    }
  }

  /* ========== RENDER ========== */
  function updateHUD(){
    $txtYou.text(COLORS[me] || me);
    $txtTurn.text(COLORS[state && state.turn || ''] || '—');
    $statusPlace.text(state && !state.both_ready
      ? (state.you_ready ? 'Você está pronto. Aguardando outros...' : 'Posicione e clique "Estou pronto".')
      : (state && state.finished ? `Fim. Vencedor: ${COLORS[state.winner]||state.winner}` : 'Em jogo.'));

    if(state && state.alive_order){
      const vivos = state.alive_order.filter(l=>state.alive[l]).map(l=>l).join(', ');
      $aliveList.text(vivos || '—');
    }
  }

  function buildBoards(){
    if(!state) return;
    updateHUD();

    // YOUR board (completo)
    if($own.children().length !== rows*cols){ gridEl($own, rows); }
    $own.find('.cell').each(function(){
      const i = +$(this).data('i');
      const r = Math.floor(i/rows), c = i%rows;
      const v = state.own[r][c]; // 'S','H','M','0'
      this.className = 'cell';
      if(v==='S') this.classList.add('own-ship');
      if(v==='H') this.classList.add('own-hit');
      if(v==='M') this.classList.add('own-miss');
    });

    // OPP board da aba selecionada (máscara por alvo)
    if(!target){
      // auto-seleciona primeiro vivo diferente de mim
      const order = state.alive_order || [];
      for(const l of order){ if(l!==me && state.alive[l]){ target = l; break; } }
    }
    renderOppBoard();
  }

  function renderOppBoard(){
    if(!state || !target) { $opp.empty(); return; }
    if($opp.children().length !== cols*rows){ gridEl($opp, cols); }
    const mask = state.opp_masks[target]; // 'H','M','?'
    $opp.find('.cell').each(function(){
      const i = +$(this).data('i');
      const r = Math.floor(i/cols), c = i%cols;
      const v = mask[r][c];
      this.className = 'cell';
      if(v==='H') this.classList.add('opp-hit');
      else if(v==='M') this.classList.add('opp-miss');
      else this.classList.add('opp-fog');
    });
  }

  function buildOpponentTabs(){
    if(!state) return;
    const order = state.alive_order || [];
    $tabs.empty();
    for(const l of order){
      if(l===me) continue;
      const $t = $('<div class="tab"/>').addClass(l).text(l);
      if(!state.alive[l]) $t.addClass('dead');
      if(l===target) $t.addClass('active');
      $t.on('click', function(){ target = l; renderOppBoard(); $('.tab').removeClass('active'); $(this).addClass('active'); });
      $tabs.append($t);
    }
  }

  // deep link ?room=CODE
  (function(){
    const params = new URLSearchParams(window.location.search);
    const code = params.get('room');
    if(code){ $('#roomCode').val(code); }
  })();
});
