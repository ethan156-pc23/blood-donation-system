(function () {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Mobile sidebar
  const sb = $('#sidebar'), ov = $('#overlay'), mb = $('#menuBtn');
  const toggle = open => { sb && sb.classList.toggle('open', open); ov && ov.classList.toggle('show', open); };
  mb && mb.addEventListener('click', () => toggle(!sb.classList.contains('open')));
  ov && ov.addEventListener('click', () => toggle(false));

  // Modals: [data-open="id"] opens, [data-close] / backdrop / Esc closes. data-fill='{"name":"x"}' pre-fills form fields.
  const closeAll = () => $$('.modal').forEach(m => (m.hidden = true));
  document.addEventListener('click', e => {
    const o = e.target.closest('[data-open]');
    if (o) {
      const m = document.getElementById(o.dataset.open);
      if (!m) return;
      if (o.dataset.fill) {
        const data = JSON.parse(o.dataset.fill);
        Object.entries(data).forEach(([k, v]) => { const f = m.querySelector('[name="' + k + '"]'); if (f) f.value = v ?? ''; });
      }
      m.hidden = false;
      const first = m.querySelector('input:not([type=hidden]),select'); first && first.focus();
    } else if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) closeAll();
  });
  document.addEventListener('keydown', e => e.key === 'Escape' && closeAll());

  // Filters that submit on change
  $$('[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

  // Show / hide password
  $$('[data-toggle-pw]').forEach(b => b.addEventListener('click', () => {
    const i = b.parentElement.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password';
  }));

  // 6-box security code input
  const otp = $('#otp');
  if (otp) {
    const boxes = $$('input', otp), out = $('#codeValue');
    const sync = () => { out.value = boxes.map(b => b.value).join(''); boxes.forEach(b => b.classList.toggle('filled', !!b.value)); };
    boxes.forEach((b, i) => {
      b.addEventListener('input', () => { b.value = b.value.replace(/\D/g, '').slice(-1); sync(); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); });
      b.addEventListener('keydown', e => { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) { boxes[i - 1].focus(); boxes[i - 1].value = ''; sync(); } });
      b.addEventListener('paste', e => {
        const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
        if (!t) return; e.preventDefault();
        boxes.forEach((x, k) => (x.value = t[k] || '')); sync(); (boxes[Math.min(t.length, 5)]).focus();
      });
    });
    boxes[0].focus();
    $('#codeForm').addEventListener('submit', e => { sync(); if (out.value.length !== 6) { e.preventDefault(); (boxes.find(b => !b.value) || boxes[0]).focus(); } });
  }

  // Resend countdown
  const rb = $('#resendBtn');
  if (rb && +rb.dataset.left > 0) {
    let left = +rb.dataset.left;
    const t = setInterval(() => {
      left--; if (left <= 0) { clearInterval(t); rb.disabled = false; rb.textContent = 'Resend verification code'; }
      else rb.textContent = 'Resend verification code (' + left + 's)';
    }, 1000);
  }

  // Auto-dismiss simple success flashes
  $$('.content > .flash:not(.err):not(.info)').forEach(f => setTimeout(() => (f.style.display = 'none'), 5000));
})();
