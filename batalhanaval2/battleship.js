$(function(){
  const API = window.BS_API || 'api_battleship.php';

  const rows = 20, cols = 20; // fixo
  let room = null, token = null, me = null, version = 0, chatLastId = 0;
  let state = null;

  let currentView = 'GLOBAL'; // 'GLOBAL' ou 'A'|'B'|'C'|'D'

  const COLORS = { A:'Azul', B:'Vermelho', C:'Verde', D:'Amarelo' };

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

  // ==== SALAS ====
  $('#btnCriar').on('click', function(){
    const maxp = parseInt($('#playersCount').val(),10) || 2;
    api({action:'create', maxp}).done(res=>{
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
      buildBoards(); buildTabs();
      log('Sala reiniciada.');
    });
  });

  // ==== POLLING ====
  let pollTimer=null;
  function poll(){
    if(!room) return;
    api({action:'poll', room, token, version, last_chat_id: chatLastId}).done(res=>{
      if(res && res.success){
        if(res.update){
          state = res.state; version = state.version;
          buildBoards(); buildTabs();
        }
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
    buildTabs();
  }

  function deepLink(){
    const url = new URL(window.location.href);
    url.searchParams.set('room', room||'');
    $share.attr('href', url.toString());
    $linkSala.text(url.toString());
    $('#roomCode').val(room||'');
  }

  // ==== POSICIONAR / PRONTO ====
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

  // ==== CHAT ====
  $('#chatSend').on('click', sendChat);
  $chatMsg.on('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); sendChat(); }});
  function sendChat(){
    const text = ($chatMsg.val()||'').trim();
    if(!text || !room || !token) return;
    api({action:'chat_send', room, token, text}).done(res=>{
      if(res && res.success){
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
  function fmtTime(ts){ const d=new Date(ts*1000); return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }
  function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  // ==== TIRO GLOBAL ====
  function bindHandlers(){
    $opp.off('click').on('click','.cell', function(){
      if(!state || !room || !token) return;
      if(!state.both_ready){ log('Aguardando todos prontos...'); return; }
      if(state.finished){ return; }
      if(state.turn !== me){ return; }

      const i = +$(this).data('i');
      const r = Math.floor(i/cols), c = i%cols;

      // bloqueia tiro em terra
      if(state.terrain && state.terrain[r] && state.terrain[r][c]==='X'){
        log('Não é possível atirar em terra.');
        return;
      }

      api({action:'shoot', room, token, r, c}).done(res=>{
        if(!res.success){
          if(res.error) log('Falha: '+res.error);
          if(res.state){ state=res.state; version=state.version; buildBoards(); buildTabs(); }
          return;
        }
        // guardar global mask anterior pra animar
        const prevGlobal = state.global_mask;
        state=res.state; version=state.version;
        buildBoards(); buildTabs();

        // anima se a célula virou H no global
        tryAnimateGlobalHit(prevGlobal, state.global_mask, r, c);
      });
    });
  }

  function tryAnimateGlobalHit(prev, next, r, c){
    if(!prev || !next) return;
    if(next[r][c]==='H' && prev[r][c]!=='H'){
      const idx=r*cols+c;
      const $cell = $opp.find(`.cell[data-i="${idx}"]`);
      const $boom = $('<div class="explosion"></div>');
      $cell.append($boom);
      setTimeout(()=>{$boom.remove();},700);
    }
  }

  // ==== RENDER ====
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

    // seu tabuleiro (com ilhas)
    if($own.children().length !== rows*cols){ gridEl($own, rows); }
    $own.find('.cell').each(function(){
      const i = +$(this).data('i');
      const r = Math.floor(i/rows), c = i%rows;
      this.className = 'cell';
      if(state.terrain && state.terrain[r] && state.terrain[r][c]==='X'){
        this.classList.add('terrain-land'); this.style.cursor='not-allowed'; return;
      }
      const v = state.own[r][c];
      if(v==='S') this.classList.add('own-ship');
      if(v==='H') this.classList.add('own-hit');
      if(v==='M') this.classList.add('own-miss');
    });

    renderOppBoard();
  }

  function renderOppBoard(){
    if(!state) { $opp.empty(); return; }
    if($opp.children().length !== cols*rows){ gridEl($opp, cols); }

    let mask;
    if(currentView==='GLOBAL'){ mask = state.global_mask; }
    else { mask = state.opp_masks[currentView]; }

    $opp.find('.cell').each(function(){
      const i = +$(this).data('i');
      const r = Math.floor(i/cols), c = i%cols;
      this.className = 'cell';

      if(state.terrain && state.terrain[r] && state.terrain[r][c]==='X'){
        this.classList.add('terrain-land'); this.style.cursor='not-allowed'; return;
      }
      const v = mask[r][c];
      if(v==='H') this.classList.add('opp-hit');
      else if(v==='M') this.classList.add('opp-miss');
      else this.classList.add('opp-fog');
    });
  }

  function buildTabs(){
    if(!state) return;
    const order = state.alive_order || [];
    $tabs.find('.tab').not('[data-view="GLOBAL"]').remove();
    for(const l of order){
      if(l===me) continue;
      const $t = $('<div class="tab"/>').addClass(l).text(l).attr('data-view', l);
      if(!state.alive[l]) $t.addClass('dead');
      $t.on('click', function(){
        $('.tab').removeClass('active'); $(this).addClass('active');
        currentView = l; renderOppBoard();
      });
      $tabs.append($t);
    }
    // garante GLOBAL ativo quando apropriado
    if(currentView!=='GLOBAL' && !$tabs.find(`.tab[data-view="${currentView}"]`).length){
      currentView='GLOBAL'; $tabs.find(`.tab[data-view="GLOBAL"]`).addClass('active');
      renderOppBoard();
    } else if(currentView==='GLOBAL'){
      $tabs.find(`.tab[data-view="GLOBAL"]`).addClass('active');
    }
  }

  // deep link
  (function(){
    const params = new URLSearchParams(window.location.search);
    const code = params.get('room'); if(code){ $('#roomCode').val(code); }
  })();
});
