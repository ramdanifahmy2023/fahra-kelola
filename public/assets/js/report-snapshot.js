(() => {
  window.createReportSnapshot = ({content, scope, status}) => {
    let sequence = 0, requested = '', loaded = '';
    return {
      begin(key, label) {
        const token = ++sequence;
        requested = key;
        if (loaded !== key) {
          content.replaceChildren();
          loaded = '';
          if (scope) scope.textContent = label;
        }
        content.setAttribute('aria-busy', 'true');
        return {
          current: () => token === sequence,
          commit(markup) {
            if (token !== sequence) return false;
            const focused = content.contains(document.activeElement) ? document.activeElement : null;
            const link = focused?.getAttribute('href');
            const focusKey = focused?.dataset.focusKey;
            const disclosure = focused?.closest('details[data-disclosure]')?.dataset.disclosure;
            const open = [...content.querySelectorAll('details[open][data-disclosure]')].map(el => el.dataset.disclosure);
            if (content.innerHTML !== markup) {
              content.innerHTML = markup;
              content.querySelectorAll('details[data-disclosure]').forEach(el => { el.open = open.includes(el.dataset.disclosure); });
              const replacement = [...content.querySelectorAll('a,button,summary')].find(el => focusKey ? el.dataset.focusKey === focusKey : link && el.getAttribute('href') === link)
                || [...content.querySelectorAll('details[data-disclosure]')].find(el => el.dataset.disclosure === disclosure)?.querySelector('summary');
              if (focused && replacement) replacement.focus({preventScroll: true});
            }
            loaded = requested;
            content.setAttribute('aria-busy', 'false');
            return true;
          },
          fail() {
            if (token !== sequence) return false;
            content.setAttribute('aria-busy', 'false');
            if (status) status.textContent = loaded === requested
              ? 'Pembaruan gagal. Angka terakhir untuk pilihan ini tetap ditampilkan.'
              : 'Angka belum tersedia untuk pilihan ini.';
            return true;
          }
        };
      }
    };
  };
})();
