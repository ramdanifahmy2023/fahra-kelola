(() => {
  window.createFinanceProfitView = ({root, request}) => {
    const $ = id => document.getElementById('finance-profit-' + id);
    if (!$('content')) return null;
    const snapshot = window.createReportSnapshot({content:$('content'),scope:$('scope'),status:$('status')});
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = value => value === null || value === undefined ? 'Belum diketahui' : new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
    const day = value => new Date(value+'T00:00:00+07:00').toLocaleDateString('id-ID',{dateStyle:'medium',timeZone:'Asia/Jakarta'});
    return {
      async load(filters) {
        const selected = {...filters}, cash = selected.basis === 'cash';
        const basis = cash ? 'CASH · dana dilepas' : 'ACCRUAL · pesanan dibuat';
        const names = [...document.getElementById('finance-shops').options].filter(o => !selected.shops || selected.shops.split(',').includes(o.value)).map(o => o.textContent);
        const label = `${names.length === 1 ? names[0] : names.length + ' toko'} · ${day(selected.start)} sampai ${day(selected.end)} WIB · ${basis}`;
        const query = new URLSearchParams(selected);
        const state = snapshot.begin(query.toString(),label);
        $('error').hidden = true; $('status').textContent = 'Memuat laporan untuk pilihan ini…';
        try {
          const data = await request('profitLoss?' + query);
          if (!state.current()) return;
          const stores = data.stores || [], totals = data.totals || {};
          const missingCost = stores.reduce((sum,s) => sum + Number(s.incomplete_cost_orders || 0) + Number(s.orphan_income_orders || 0),0);
          const missingAds = stores.filter(s => !s.ads?.complete).length;
          const revenueLabel = cash ? 'Penghasilan bersih Shopee' : 'Nilai pesanan selesai';
          const metric = (label,value,note='') => `<article><span>${esc(label)}</span><strong>${esc(money(value))}</strong>${note ? `<small>${esc(note)}</small>` : ''}</article>`;
          const warnings = [cash ? `Rincian dana lengkap untuk ${stores.filter(s=>s.coverage?.complete).length}/${stores.length} toko.` : 'Kelengkapan pesanan tersimpan belum dapat diverifikasi otomatis.',missingCost ? `${missingCost} pesanan belum memiliki HPP lengkap.` : '',missingAds ? `Data iklan ${missingAds} toko belum lengkap.` : ''].filter(Boolean).join(' ');
          const shopCards = stores.map(store => {
            const missing = Number(store.incomplete_cost_orders || 0) + Number(store.orphan_income_orders || 0);
            const costHref = root.dataset.base + '/panel/finance?' + new URLSearchParams({...selected,tab:'cost',cost_status:'all',cost_shop:String(store.id)});
            const rows = [
              [revenueLabel,money(store.revenue),cash ? `${Number(store.revenue_days || 0)}/${Number(store.coverage?.expected_days || 0)} hari · ${Number(store.revenue_orders || 0)} rincian` : `${Number(store.revenue_orders || 0)} pesanan selesai tersimpan; cakupan belum terverifikasi`],
              ['HPP',money(store.cost),missing ? `${missing} pesanan belum lengkap · ${Number(store.missing_cost_units || 0)} unit belum diketahui` : `${Number(store.cost_orders || 0)} pesanan dengan rincian item lengkap`],
              [cash ? 'Sisa setelah HPP' : 'Laba kotor tercatat',money(store.gross_profit),'Pendapatan dikurangi HPP yang lengkap'],
              ...(cash ? [['Selisih income dan net income Shopee',money(store.source_difference),'Selisih sumber, belum dikelompokkan sebagai biaya tertentu']] : []),
              ['Biaya iklan',money(store.ads?.amount),`Cakupan ${Number(store.ads?.days || 0)}/${Number(data.range.days)} hari`],
              ['Hasil setelah HPP & iklan',money(store.after_ads),'Belum termasuk seluruh biaya platform dan kerugian retur'],
              ['Kerugian retur','Belum diketahui','Nilai barang retur yang menjadi kerugian belum tersedia'],
              ['Laba bersih','Belum dihitung',store.net_profit_reason || 'Komponen biaya belum lengkap']
            ];
            return `<article class="finance-profit-shop"><h3>${esc(store.name)}</h3>${missing ? `<a class="finance-text-button" data-focus-key="profit-hpp-${Number(store.id)}" href="${esc(costHref)}">Lengkapi HPP · ${missing} pesanan</a>` : ''}<dl>${rows.map(([title,value,note]) => `<div><dt>${esc(title)}</dt><dd>${esc(value)}</dd><small>${esc(note)}</small></div>`).join('')}</dl></article>`;
          }).join('');
          const markup = `<p class="finance-profit-caveat">${esc(warnings)}</p><div class="finance-profit-metrics finance-profit-overview">${metric(revenueLabel,totals.revenue)}${metric('HPP tercatat',totals.cost)}${metric('Hasil setelah HPP & iklan',totals.after_ads,'Belum merupakan laba bersih')}</div><p class="finance-help">Laba bersih belum dihitung. Biaya platform dan kerugian retur belum lengkap.</p><details class="finance-profit-breakdown" data-disclosure="profit-breakdown"><summary data-focus-key="profit-breakdown">Rincian perhitungan &amp; ${stores.length} toko</summary><div class="finance-profit-metrics">${metric(cash ? 'Sisa setelah HPP' : 'Laba kotor tercatat',totals.gross_profit)}${metric('Biaya iklan',totals.ads,'Pemakaian iklan, bukan top up')}</div><div class="finance-profit-shops">${shopCards || '<p class="finance-empty">Tidak ada toko pada pilihan ini.</p>'}</div></details>`;
          if (state.commit(markup)) { $('scope').textContent = label; $('status').textContent = 'Data tersimpan · ' + basis; }
        } catch (error) {
          if (!state.fail()) return;
          $('error').querySelector('p').textContent = error.message; $('error').hidden = false;
        }
      }
    };
  };
})();
