# Blocos de conteúdo reutilizáveis (HTML) — mantêm consistência visual entre páginas
import html as _h
def sec(inner, cls="", id=""):
    return '<section class="trix-section %s"%s><div class="trix-container">%s</div></section>' % (cls, (' id="%s"' % id) if id else "", inner)
def head(kicker, title, lead="", center=False):
    return '<div class="trix-section__head%s trix-reveal">%s<h2>%s</h2>%s</div>' % (" trix-section__head--center" if center else "", ('<span class="trix-kicker">%s</span>' % kicker) if kicker else "", title, ('<p class="lead">%s</p>' % lead) if lead else "")
def _cta(it):
    """Texto do link do cartão; o genérico "Saiba mais" ganha o destino para leitores de tela e buscadores."""
    cta = it.get("cta", "Saiba mais")
    if cta == "Saiba mais":
        import re as _re
        cta += '<span class="trix-sr"> sobre %s</span>' % _h.unescape(_re.sub(r"<[^>]+>", "", it["title"]))
    return cta
def cards(items, cols=3, tilt=True, dark=False):
    out = ['<div class="trix-grid trix-grid--%d">' % cols]
    for it in items:
        icon = it.get("icon"); url = it.get("url"); badge = it.get("badge")
        out.append('<article class="trix-card%s%s trix-reveal">%s%s<h3>%s</h3><p>%s</p>%s</article>' % (
            " trix-tilt" if tilt else "", " trix-card--dark" if dark else "",
            ('<span class="trix-card__badge">%s</span>' % badge) if badge else "",
            ('<div class="trix-card__icon">{{icon:%s}}</div>' % icon) if icon else "",
            it["title"], it["text"], ('<a class="trix-card__link" href="%s">%s</a>' % (url, _cta(it))) if url else ""))
    out.append('</div>'); return "".join(out)
def checks(items):
    return '<ul class="trix-check">' + "".join("<li>%s</li>" % i for i in items) + "</ul>"
def feature(title, text, img, alt, rev=False, kicker="", extra="", plain=False):
    return ('<div class="trix-feature%s trix-reveal"><div class="trix-feature__media%s"><img src="%s" alt="%s" loading="lazy" decoding="async"></div><div>%s<h3>%s</h3>%s%s</div></div>'
            % (" trix-feature--rev" if rev else "", " trix-feature__media--plain" if plain else "", img, _h.escape(alt), ('<span class="trix-kicker">%s</span>' % kicker) if kicker else "", title, text, extra))
def stats(items):
    def one(n, s, l):
        anim = ' data-count="%s" data-suffix="%s"' % (n, s) if str(n).replace(".", "", 1).isdigit() else ""
        return '<div class="trix-stat"><span class="trix-stat__n"><strong%s>%s%s</strong></span><span class="trix-stat__l">%s</span></div>' % (anim, n, s, l)
    return '<div class="trix-stats trix-reveal">' + "".join(one(n, s, l) for n, s, l in items) + '</div>'
def cta(title, text, primary=("Fale com um consultor", "/contato/"), secondary=None):
    btns = '<a class="trix-btn trix-btn--primary" href="%s">%s</a>' % (primary[1], primary[0])
    if secondary: btns += '<a class="trix-btn trix-btn--ghost" href="%s">%s</a>' % (secondary[1], secondary[0])
    return '<div class="trix-cta trix-reveal"><div><h2>%s</h2><p>%s</p></div><div class="trix-actions">%s</div></div>' % (title, text, btns)
def faq(items):
    return '<div class="trix-acc trix-reveal">' + "".join('<details><summary>%s</summary><div>%s</div></details>' % (q, a) for q, a in items) + '</div>'
def steps(items):
    return '<ol class="trix-timeline trix-reveal">' + "".join('<li><time>%s</time><strong>%s</strong><p>%s</p></li>' % (t, h, p) for t, h, p in items) + '</ol>'
def table(headers, rows):
    return '<div class="trix-table-wrap"><table class="trix-table"><thead><tr>%s</tr></thead><tbody>%s</tbody></table></div>' % ("".join("<th>%s</th>" % h for h in headers), "".join("<tr>%s</tr>" % "".join("<td>%s</td>" % c for c in r) for r in rows))
def arch(items):
    return '<div class="trix-grid trix-grid--4">' + "".join('<div class="trix-stat"><span class="trix-stat__l">%s</span><span style="display:block;font-weight:600;margin-top:4px">%s</span></div>' % (k, v) for k, v in items) + '</div>'
def pills(items):
    return "".join('<span class="trix-pill">%s</span>' % i for i in items)
def contact_band():
    return sec(cta("Vamos conversar sobre o seu projeto?", "Atendimento ágil também é uma marca da Trix. Conte o seu desafio e receba um diagnóstico sem compromisso.", ("Falar com um consultor", "/contato/"), ("WhatsApp", "https://wa.me/5561992324516")), "trix-section--tight")
