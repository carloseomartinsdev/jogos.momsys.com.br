const API = 'api.php';
const createBtn = document.getElementById('create');
const joinBtn = document.getElementById('join');
const roomInput = document.getElementById('roomCode');
const boardEl = document.getElementById('board');
const resetBtn = document.getElementById('reset');
const youEl = document.getElementById('you');
const roomEl = document.getElementById('room');
const turnEl = document.getElementById('turn');
const winnerEl = document.getElementById('winner');
const msgEl = document.getElementById('msg');

let yourMark = null;
let room = null;
let state = { board: Array(9).fill(null), turn: 'X', winner: null };

function render() {
  boardEl.querySelectorAll('button').forEach((btn) => {
    const i = +btn.dataset.i;
    btn.textContent = state.board[i] ?? '';
    btn.disabled = !!state.winner || state.board[i] !== null || yourMark !== state.turn || !room;
  });
  turnEl.textContent = `Vez: ${state.turn ?? '-'}`;
  youEl.textContent = `Você: ${yourMark ?? '-'}`;
  roomEl.textContent = `Sala: ${room ?? '-'}`;
  if (state.winner) {
    winnerEl.textContent = state.winner === 'draw' ? 'Empate!' : `Vencedor: ${state.winner}`;
  } else {
    winnerEl.textContent = '';
  }
}

async function call(action, payload={}) {
  const form = new URLSearchParams({ action, clientId, ...payload });
  const res = await fetch(API, { method: 'POST', body: form });
  const data = await res.json();
  if (!res.ok || data.error) throw new Error(data.error || 'Erro na API');
  return data;
}

createBtn.onclick = async () => {
  msgEl.textContent = '';
  try {
    const out = await call('create_room', {});
    room = out.room;
    yourMark = out.mark;
    location.hash = '#' + room;
    poll(); // inicia polling
    await refreshState();
  } catch(e) { msgEl.textContent = e.message; }
  render();
};

joinBtn.onclick = async () => {
  msgEl.textContent = '';
  const code = roomInput.value.trim().toUpperCase();
  if (!code) return;
  try {
    const out = await call('join_room', { room: code });
    room = out.room;
    yourMark = out.mark;
    location.hash = '#' + room;
    poll();
    await refreshState();
  } catch(e) { msgEl.textContent = e.message; }
  render();
};

boardEl.addEventListener('click', async (e) => {
  if (e.target.tagName !== 'BUTTON' || room == null) return;
  const i = +e.target.dataset.i;
  try {
    await call('play', { room, index: i });
    await refreshState();
  } catch(e) { msgEl.textContent = e.message; }
});

resetBtn.onclick = async () => {
  if (!room) return;
  try {
    await call('reset', { room });
    await refreshState();
  } catch(e) { msgEl.textContent = e.message; }
};

async function refreshState() {
  if (!room) return;
  try {
    const out = await call('state', { room });
    state = out.state;
  } catch(e) {
    msgEl.textContent = e.message;
  }
  render();
}

// polling a cada 1200ms
let pollTimer = null;
function poll() {
  if (pollTimer) clearInterval(pollTimer);
  pollTimer = setInterval(refreshState, 1200);
}

// auto-join via hash
window.addEventListener('load', async () => {
  const hash = location.hash.replace('#','').toUpperCase();
  if (hash) {
    roomInput.value = hash;
    try {
      const out = await call('join_room', { room: hash });
      room = out.room;
      yourMark = out.mark;
      poll();
    } catch(e) { msgEl.textContent = e.message; }
  }
  await refreshState();
});

render();
