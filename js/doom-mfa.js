/**
 * Doom: reskin da tela de código 2FA (MFA) do login.
 * Esconde os 6 dígitos originais e renderiza caixas próprias, espelhando os
 * valores nos inputs reais + disparando eventos para o JS do core continuar
 * funcionando (avanço automático, validação e submit passam ilesos).
 * Sem [data-mfa-code-input] na página, não faz nada.
 */
(function () {
  function init() {
    var box = document.querySelector('[data-mfa-code-input]');
    if (!box || box.dataset.doomMfaDone) {
      return;
    }
    var reals = Array.prototype.slice.call(box.querySelectorAll('input.totp-code-digit'));
    if (!reals.length) {
      return;
    }
    box.dataset.doomMfaDone = '1';

    // Texto de instrução ("Por favor, insira o código...") cortado em cima:
    // normaliza com estilo inline (passa por cima de qualquer regra genérica).
    // Busca na página toda (não só no pai) pois o texto pode estar níveis acima.
    try {
      var els = document.querySelectorAll('p, div, span, label, h1, h2, h3, h4');
      for (var j = 0; j < els.length; j++) {
        var t = els[j].textContent || '';
        if (t.indexOf('insira') !== -1 && t.indexOf('digo') !== -1 && !els[j].querySelector('input')) {
          els[j].style.marginTop = '1rem';
          els[j].style.marginBottom = '.75rem';
          els[j].style.lineHeight = '1.6';
          els[j].style.overflow = 'visible';
          els[j].style.paddingTop = '.5rem';
          els[j].style.paddingBottom = '.5rem';
          els[j].style.textAlign = 'center';
          els[j].style.height = 'auto';
          els[j].style.maxHeight = 'none';
        }
      }
    } catch (e) {}

    var wrap = document.createElement('div');
    wrap.className = 'doom-mfa-box';
    var customs = reals.map(function (real, i) {
      var inp = document.createElement('input');
      inp.type = 'text';
      inp.className = 'doom-mfa-digit';
      inp.setAttribute('maxlength', '1');
      inp.setAttribute('inputmode', 'numeric');
      inp.setAttribute('autocomplete', i === 0 ? 'one-time-code' : 'off');
      inp.setAttribute('aria-label', real.getAttribute('aria-label') || ('Dígito ' + (i + 1)));
      wrap.appendChild(inp);
      return inp;
    });
    box.parentNode.insertBefore(wrap, box.nextSibling);
    box.classList.add('doom-mfa-custom');

    function fireInput(el) {
      try {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }
    function focusCustom(i) {
      if (customs[i]) {
        try {
          customs[i].focus();
          customs[i].select();
        } catch (e) {}
      }
    }
    customs.forEach(function (inp, i) {
      inp.addEventListener('input', function () {
        var v = inp.value.replace(/\D/g, '').slice(-1);
        inp.value = v;
        reals[i].value = v;
        fireInput(reals[i]);
        if (v && i < customs.length - 1) {
          setTimeout(function () { focusCustom(i + 1); }, 0);
        }
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !inp.value && i > 0) {
          e.preventDefault();
          reals[i - 1].value = '';
          fireInput(reals[i - 1]);
          focusCustom(i - 1);
        }
      });
      inp.addEventListener('paste', function (e) {
        e.preventDefault();
        var text = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '');
        for (var k = 0; k < text.length && i + k < customs.length; k++) {
          customs[i + k].value = text[k];
          reals[i + k].value = text[k];
          fireInput(reals[i + k]);
        }
        focusCustom(Math.min(i + text.length, customs.length - 1));
      });
    });
    setTimeout(function () { focusCustom(0); }, 100);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
