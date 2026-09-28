// Auto-hide toast notifications
(function () {
  function run() {
    const toasts = document.querySelectorAll('.toast');
    if (!toasts.length) return;

    // Trigger toast setelah UI animation (login/signup section) selesai.
    // class "active" di container ditoggle dari js/login.js.
    const container = document.getElementById('container');

    const trigger = () => {
      toasts.forEach((t) => {
        // reset agar re-trigger aman
        t.classList.remove('hide');
        t.classList.add('show');

        const duration = t.classList.contains('success') ? 2200 : 2600;
        setTimeout(() => {
          t.classList.remove('show');
          t.classList.add('hide');
        }, duration);
      });
    };

    // Jika container sedang dianimasikan (transisi), tunggu selesai.
    // Durasi di CSS/login.css transisi 0.6s.
    if (container && container.classList.contains('active')) {
      // rata-rata setelah 650ms animasi selesai
      setTimeout(trigger, 650);
      return;
    }

    // fallback: trigger langsung setelah DOM siap
    trigger();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();

