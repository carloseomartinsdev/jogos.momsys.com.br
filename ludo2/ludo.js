$(function(){
  const API = window.LUDO_API || 'api_ludo.php';

  let room=null, token=null, me=null, version=0, lastChatId=0;
  let state=null;           // estado recebido do servidor
  let board=null;           // grafo (nodes/edges/coords)
  let highlight=[];         // {pieceIdx, toType, toId, path[]} movimentos válidos

  const COLORS = { A:'Azul', B:'Vermelho', C:'Verde', D:'Amarelo' };
  const $svg = $('#boardSvg');
  const $log = $('#log');
  const $txtRoom=$('#txtRoom'), $txtYou=$('#txtYou'), $txtTurn=$('#txtTurn'), $txtDie=$('#txtDie'), $aliveList=$('#aliveList');
  const $share = $('#shareLink'), $linkSala=$('#linkSala');
  const $status = $('#status'); const $piecesInfo=$('#piecesInfo');
  const $chatLog=$('#chatLog'), $chatMsg=$('#chatMsg');

  function log(msg){ $log.prepend($('<div/>').text(msg)); }
  function api(payload){ return $.ajax({url:API,method:'POST',data:payload,dataType:'json'}); }
  function deepLink(){
    const url=new URL(window.location.href); url.searchParams.set('room',room||'');
    $share.attr('href',url.toString()); $linkSala.text(url.toString()); $('#roomCode').val(room||'');
  }

  // ===== criar / entrar =====
  $('#btnCriar').on('click', ()=>{
    const boardName = $('#boardName').val();
    const maxp = parseInt($('#playersCount').val(),10) || 2;
    api({action:'create', board:boardName, maxp}).done(res=>{
      if(!res.success){ return alert(res.error||'Falha ao criar'); }
      room=res.room; token=res.token; me=res.you_side;
      version=(res.state&&res.state.version)||0; lastChatId=res.last_chat_id||0;
      applyState(res); enterGame(); log('Sala criada. Role o dado quando for sua vez.');
    }).fail(()=>alert('Erro de rede (create).'));
  });

  $('#btnEntrar').on('click', ()=>{
    const code=($('#roomCode').val()||'').trim().toUpperCase();
    if(!code) return alert('Informe o código.');
    api({action:'join', room:code}).done(res=>{
      if(!res.success){ return alert(res.error||'Falha ao entrar'); }
      room=res.room; token=res.token; me=res.you_side;
      version=(res.state&&res.state.version)||0; lastChatId=res.last_chat_id||0;
      applyState(res); enterGame(); log('Conectado. Aguarde sua vez e role o dado.');
    }).fail(()=>alert('Erro de rede (join).'));
  });

  $('#btnRestart').on('click', ()=>{
    if(!room||!token) return;
    api({action:'restart', room, token}).done(res=>{
      if(!res.success){ return alert(res.error||'Falha ao reiniciar'); }
      applyState(res); version=res.state.version; lastChatId=res.last_chat_id||0;
      drawBoard(); log('Partida reiniciada.');
    });
  });



  // ===== poll =====
  let pollTimer=null;
  function poll(){
    if(!room) return;
    api({action:'poll', room, token, version, last_chat_id:lastChatId}).done(res=>{
      if(res && res.success){
        if(res.update){ applyState(res); version=res.state.version; drawBoard(); }
        if(Array.isArray(res.chat)&&res.chat.length){
          appendChat(res.chat); lastChatId=res.chat[res.chat.length-1].id;
        }
      }
    }).always(()=>{ pollTimer=setTimeout(poll,900); });
  }

  function enterGame(){
    $('#menuSection').hide();
    $('#gameSection').show();
    $txtRoom.text(room); deepLink();
    if(pollTimer) clearTimeout(pollTimer); 
    pollTimer=setTimeout(poll,300);
    bindSVGHandlers();
    drawBoard();
  }

  function applyState(res){
    state = res.state;
    board = res.board;
    $txtYou.text(COLORS[res.you_side||me]||me);
    $txtTurn.text(COLORS[state.turn]||state.turn);
    $txtDie.text(state.last_die||'—');
    const vivos = state.order.filter(p=>state.players[p] && !state.finished[p]).join(', ') || '—';
    $aliveList.text(vivos);
    $status.text(state.finished_all ? `Fim! Vencedor: ${COLORS[state.winner]||state.winner}` :
      (state.turn===me ? 'Sua vez.' : 'Aguarde...'));
    renderPiecesInfo();
    highlight = state.legal || [];
  }

  function renderPiecesInfo(){
    if(!state) return;
    let html='';
    for(const L of state.order){
      if(!state.players[L]) continue;
      const pcs = state.pieces[L];
      const fin = state.finished_count[L]||0;
      html+=`<div><b style="color:var(--${L})">${L}</b>: `;
      html+=pcs.map((p,idx)=>{
        if(p.pos==='BASE') return `#${idx+1}=base`;
        if(p.pos==='META') return `#${idx+1}=meta`;
        return `#${idx+1}=${p.pos}`;
      }).join(' | ');
      html+=` &nbsp; | &nbsp; meta: ${fin}/4</div>`;
    }
    $piecesInfo.html(html||'<i>—</i>');
  }

  // ===== chat =====
  $('#chatSend').on('click', sendChat);
  $chatMsg.on('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); sendChat(); }});
  function sendChat(){
    const text=($chatMsg.val()||'').trim();
    if(!text||!room||!token) return;
    api({action:'chat_send', room, token, text}).done(res=>{
      if(res && res.success){
        if(Array.isArray(res.chat)&&res.chat.length){
          appendChat(res.chat); lastChatId=res.chat[res.chat.length-1].id;
        }
        $chatMsg.val('');
      }
    });
  }
  function appendChat(items){
    for(const m of items){
      const who=COLORS[m.who]||m.who;
      const $line=$('<div/>').html(
        `<span style="color:var(--${m.who||'A'})">[${who}]</span> <span class="muted small">${fmtTime(m.ts)}</span>: ${escapeHtml(m.text)}`
      );
      $chatLog.append($line);
    }
    $chatLog.scrollTop($chatLog[0].scrollHeight);
  }
  function fmtTime(ts){ const d=new Date(ts*1000); return d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}); }
  function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

  // ===== dado / jogada =====
  $('#btnRoll').on('click', ()=>{
    if(!room||!token) return;
    api({action:'roll', room, token}).done(res=>{
      if(!res.success){ if(res.error) log(res.error); return; }
      applyState(res); version=res.state.version; drawBoard();
      log(`Dado: ${res.state.last_die}`);
    });
  });

  function bindSVGHandlers(){
    $svg.on('click','.dest', function(){
      if(state.turn!==me) return;
      const pieceIdx = parseInt($(this).attr('data-piece'),10);
      const toType = $(this).attr('data-to-type'); // NODE | HOME | START
      const toId   = $(this).attr('data-to');
      api({action:'move', room, token, piece:pieceIdx, toType, toId}).done(res=>{
        if(!res.success){ if(res.error) log(res.error); return; }
        applyState(res); version=res.state.version; drawBoard();
      });
    });
  }

  // ===== desenho do grafo =====
  function drawBoard(){
    if(!board||!state) return;
    $svg.empty();
    // edges
    for(const e of board.edges){
      const a=board.nodeMap[e.a], b=board.nodeMap[e.b];
      if(!a||!b) continue;
      const cls = e.type==='portal' ? 'edge portal':'edge';
      $svg.append(svgLine(a.x,a.y,b.x,b.y,cls));
    }

    // nodes
    for(const n of board.nodes){
      let cls='node';
      if(n.type==='segura') cls+=' safe';
      if(n.type==='portal'||n.type==='ponte') {
        cls+=' portal';
        if(n.portalType==='entrada') cls+=' portal-entrada';
        else if(n.portalType==='saida') cls+=' portal-saida';
        else if(n.portalType==='braco') cls+=' portal-braco';
      }
      if(n.type==='inicio:A') cls+=' startA';
      if(n.type==='inicio:B') cls+=' startB';
      if(n.type==='inicio:C') cls+=' startC';
      if(n.type==='inicio:D') cls+=' startD';
      if(n.type==='braco') cls+=' braco';
      if(n.type==='home') {
        // Detecta qual jogador pela ID do nó
        if(n.id.startsWith('H_A')) cls+=' homeA';
        else if(n.id.startsWith('H_B')) cls+=' homeB';
        else if(n.id.startsWith('H_C')) cls+=' homeC';
        else if(n.id.startsWith('H_D')) cls+=' homeD';
      }
      if(n.type==='meta:A') cls+=' metaA';
      if(n.type==='meta:B') cls+=' metaB';
      if(n.type==='meta:C') cls+=' metaC';
      if(n.type==='meta:D') cls+=' metaD';
      $svg.append(svgNode(n,cls));
    }

    // destinos válidos (highlight)
    if(Array.isArray(highlight)){
      for(const h of highlight){
        if(h.toType==='NODE'){
          const n = board.nodeMap[h.toId]; if(!n) continue;
          $svg.append(svgDest(n.x,n.y,h.pieceIdx,'NODE',h.toId));
        }else if(h.toType==='HOME'){
          const n = board.nodeMap[h.toId]; if(!n) continue;
          $svg.append(svgDest(n.x,n.y,h.pieceIdx,'HOME',h.toId));
        }else if(h.toType==='START'){
          const n = board.nodeMap[h.toId]; if(!n) continue;
          $svg.append(svgDest(n.x,n.y,h.pieceIdx,'START',h.toId));
        }
      }
    }

    // peças (sobrepõe)
    const R = 2.2;
    for(const L of state.order){
      if(!state.players[L]) continue;
      const pcs = state.pieces[L];
      for(let i=0;i<pcs.length;i++){
        const p=pcs[i];
        if(p.pos==='BASE'){
          // desenha sombra na base do jogador (coordenada no board.startBases[L])
          const base = board.startBases[L];
          if(base){ $svg.append(svgPiece(base.x,base.y,R,`${L} shadow`)); }
        } else if(p.pos==='META'){
          const m=board.metaNodes[L]; if(m){ $svg.append(svgPiece(m.x,m.y,R,`${L}`)); }
        } else {
          const n=board.nodeMap[p.pos]; if(n){ $svg.append(svgPiece(n.x,n.y,R,`${L}`)); }
        }
      }
    }
  }

  function svgLine(x1,y1,x2,y2,cls){ 
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('class', cls);
    line.setAttribute('x1', x1);
    line.setAttribute('y1', y1);
    line.setAttribute('x2', x2);
    line.setAttribute('y2', y2);
    return line;
  }
  function svgNode(n,cls){
    const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    g.setAttribute('class', cls);
    g.setAttribute('transform', `translate(${n.x} ${n.y})`);
    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    rect.setAttribute('x', '-2.5');
    rect.setAttribute('y', '-2.5');
    rect.setAttribute('width', '5');
    rect.setAttribute('height', '5');
    rect.setAttribute('rx', '0.5');
    g.appendChild(rect);
    if(n.label){
      const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      text.setAttribute('y', '1.2');
      text.setAttribute('text-anchor', 'middle');
      text.textContent = n.label;
      g.appendChild(text);
    }
    return g;
  }
  function svgDest(x,y,pieceIdx,toType,toId){
    const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    g.setAttribute('class', 'dest');
    g.setAttribute('transform', `translate(${x} ${y})`);
    g.setAttribute('data-piece', pieceIdx);
    g.setAttribute('data-to-type', toType);
    g.setAttribute('data-to', toId);
    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
    rect.setAttribute('x', '-3.5');
    rect.setAttribute('y', '-3.5');
    rect.setAttribute('width', '7');
    rect.setAttribute('height', '7');
    rect.setAttribute('rx', '1');
    g.appendChild(rect);
    return g;
  }
  function svgPiece(x,y,r,cls){ 
    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    circle.setAttribute('class', `piece ${cls}`);
    circle.setAttribute('cx', x);
    circle.setAttribute('cy', y);
    circle.setAttribute('r', r);
    return circle;
  }

  // deep link
  (function(){
    const params=new URLSearchParams(window.location.search);
    const code=params.get('room'); if(code) $('#roomCode').val(code);
  })();
});
