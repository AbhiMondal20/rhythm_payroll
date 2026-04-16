function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const ma = document.getElementById('mainArea');
  const ov = document.getElementById('mobOverlay');

  if (window.innerWidth <= 1024) {
    sb.classList.toggle('mob-open');
    ov.style.display = sb.classList.contains('mob-open') ? 'block' : 'none';
  } else {
    sb.classList.toggle('hidden');
    ma.classList.toggle('full');
  }
}

function closeSidebar() {
  const sb = document.getElementById('sidebar');
  const ov = document.getElementById('mobOverlay');
  sb.classList.remove('mob-open');
  ov.style.display = 'none';
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'flex';
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

function closeModalBg(e, id) {
  const el = document.getElementById(id);
  if (e.target === el) {
    closeModal(id);
  }
}

function showToast(msg) {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toastMsg');
  if (!toast || !toastMsg) return;

  toastMsg.textContent = msg;
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3000);
}