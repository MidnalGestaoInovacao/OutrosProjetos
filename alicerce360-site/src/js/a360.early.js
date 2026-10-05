/* Alicerce360 · executado no topo do <body> (cabeçalho) para evitar "piscar":
   aplica classe de JS, preferências de acessibilidade e o padrão do Consent Mode. */
(function () {
  var h = document.documentElement;
  h.classList.add('a3-js');
  try {
    var a = JSON.parse(localStorage.getItem('a3_a11y') || '{}');
    if (a.scale) h.style.setProperty('--a11y-scale', a.scale);
    ['contrast', 'gray', 'links', 'font', 'spacing', 'guide', 'cursor', 'motion', 'noimg'].forEach(function (k) {
      if (a[k]) h.classList.add('a11y-' + k);
    });
  } catch (e) { /* armazenamento indisponível */ }
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag('consent', 'default', {
    ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
    analytics_storage: 'denied', functionality_storage: 'granted', security_storage: 'granted',
    wait_for_update: 500
  });
  try {
    var c = JSON.parse(localStorage.getItem('a3_consent') || 'null');
    if (c && c.cats) {
      window.gtag('consent', 'update', {
        analytics_storage: c.cats.analytics ? 'granted' : 'denied',
        ad_storage: c.cats.marketing ? 'granted' : 'denied',
        ad_user_data: c.cats.marketing ? 'granted' : 'denied',
        ad_personalization: c.cats.marketing ? 'granted' : 'denied'
      });
    }
  } catch (e) { /* sem consentimento salvo */ }
})();
