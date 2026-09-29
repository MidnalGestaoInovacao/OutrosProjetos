# -*- coding: utf-8 -*-
"""SEO sem plugin: meta description, Open Graph, Twitter Cards e JSON-LD (schema.org) por página/matéria.
As tags são emitidas no início do conteúdo (HTML estático, lido por buscadores e redes sociais) e, no
carregamento, o bs.js as move para o <head>. Se um plugin de SEO (ex.: Yoast) for instalado, as tags
dele prevalecem e as nossas duplicadas são descartadas pelo script."""
import json, html as H, re

SITE = "https://bsagro.agr.br"
SITE_NAME = "BS Agro Capital"
DESCRIPTION = ("A BS Agro Capital é especializada na estruturação de soluções financeiras para produtores rurais e empresas "
               "do agronegócio, inclusive em cenários que exigem maior complexidade financeira.")

def org(emails, logo_url, image_url, dpo_nome="", linkedin=""):
    return {
        "@type": ["FinancialService", "Organization"], "@id": SITE + "/#org", "name": SITE_NAME,
        "description": DESCRIPTION, "url": SITE + "/", "logo": {"@type": "ImageObject", "url": logo_url}, "image": image_url,
        "telephone": "+5562996892488", "taxID": "66.308.795/0001-08", "email": emails["contato"],
        "address": {"@type": "PostalAddress", "streetAddress": "Av. 136, 960 - Setor Marista", "addressLocality": "Goiânia",
                    "addressRegion": "GO", "postalCode": "74180-140", "addressCountry": "BR"},
        "areaServed": "BR",
        "serviceType": ["Estruturação de crédito para o agronegócio", "Crédito Plano Safra: custeio, investimento e comercialização",
                        "CPR (Cédula de Produto Rural)", "Crédito estruturado via fundos de investimento",
                        "Crédito com garantia de imóvel rural e urbano", "Assessoria financeira em Recuperação Judicial"],
        "sameAs": ["https://www.instagram.com/bsagro.capital/", "https://www.facebook.com/bsagrooficial"],
        "contactPoint": [
            {"@type": "ContactPoint", "contactType": "customer service", "email": emails["contato"], "telephone": "+5562996892488", "availableLanguage": ["pt-BR", "en", "es"]},
            {"@type": "ContactPoint", "contactType": "Encarregado pelo Tratamento de Dados Pessoais (LGPD)", "email": emails["dpo"], "name": dpo_nome or None},
            {"@type": "ContactPoint", "contactType": "Ouvidoria, compliance e anticorrupção", "email": emails["ouvidoria"]},
        ],
    }

def _clean(s, n=158):
    s = re.sub(r"<[^>]+>", " ", s or "")
    s = H.unescape(re.sub(r"\s+", " ", s)).strip()
    return (s[: n - 1].rsplit(" ", 1)[0] + "…") if len(s) > n else s

def head_links(icon512, icon32, apple):
    return ('<link rel="icon" type="image/png" sizes="32x32" href="%s"><link rel="icon" type="image/png" sizes="512x512" href="%s">'
            '<link rel="apple-touch-icon" href="%s"><meta name="theme-color" content="#0d3527">' % (icon32, icon512, apple))

def _sem_nulos(x):
    if isinstance(x, dict):
        return {k: _sem_nulos(v) for k, v in x.items() if v is not None}
    if isinstance(x, list):
        return [_sem_nulos(v) for v in x if v is not None]
    return x

def global_jsonld(emails, dpo_nome, linkedin, logo_url, image_url, search_url):
    data = {"@context": "https://schema.org", "@graph": [
        dict(org(emails, logo_url, image_url, dpo_nome, linkedin),
             founder={"@type": "Person", "name": "Évellyn Brandão", "sameAs": [linkedin]} if linkedin else None),
        {"@type": "WebSite", "@id": SITE + "/#website", "url": SITE + "/", "name": SITE_NAME, "inLanguage": "pt-BR",
         "publisher": {"@id": SITE + "/#org"},
         "potentialAction": {"@type": "SearchAction", "target": search_url, "query-input": "required name=search_term_string"}},
    ]}
    return '<script type="application/ld+json">%s</script>' % json.dumps(_sem_nulos(data), ensure_ascii=False)

def seo_block(title, description, url, image, kind="website", article=None, breadcrumbs=None, keywords=None, robots=None, faq=None, image_size=(1200, 630)):
    """Bloco wp:html com as tags de SEO. url: endereço absoluto da página. breadcrumbs: [(nome, url_absoluta)]."""
    desc = _clean(description)
    t = H.escape(title, quote=True); d = H.escape(desc, quote=True)
    tags = ['<meta name="bs-title" content="%s">' % t,
            '<meta name="description" content="%s">' % d,
            '<meta property="og:locale" content="pt_BR"><meta property="og:type" content="%s">' % ("article" if kind == "article" else "website"),
            '<meta property="og:site_name" content="%s">' % SITE_NAME,
            '<meta property="og:title" content="%s"><meta property="og:description" content="%s"><meta property="og:url" content="%s">' % (t, d, url),
            '<meta property="og:image" content="%s"><meta property="og:image:width" content="%d"><meta property="og:image:height" content="%d">' % (image, image_size[0], image_size[1]),
            '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="%s"><meta name="twitter:description" content="%s"><meta name="twitter:image" content="%s">' % (t, d, image)]
    if keywords:
        tags.append('<meta name="keywords" content="%s">' % H.escape(", ".join(keywords), quote=True))
    if robots:
        tags.append('<meta name="robots" content="%s">' % H.escape(robots, quote=True))
    graph = []
    if kind == "article" and article:
        tags.append('<meta property="article:published_time" content="%s"><meta property="article:section" content="%s">' % (article["date"], H.escape(article.get("section", ""), quote=True)))
        graph.append({"@type": "Article", "@id": url + "#article", "headline": title, "description": desc, "image": [image],
                      "datePublished": article["date"], "dateModified": article.get("modified", article["date"]),
                      "author": {"@type": "Organization", "name": "Equipe BS Agro Capital", "url": SITE + "/"},
                      "publisher": {"@id": SITE + "/#org"}, "mainEntityOfPage": {"@type": "WebPage", "@id": url},
                      "inLanguage": "pt-BR", "articleSection": article.get("section", ""), "keywords": ", ".join(article.get("tags", []))})
    else:
        graph.append({"@type": "WebPage", "@id": url, "url": url, "name": title, "description": desc, "inLanguage": "pt-BR",
                      "isPartOf": {"@id": SITE + "/#website"}, "about": {"@id": SITE + "/#org"},
                      "primaryImageOfPage": {"@type": "ImageObject", "url": image}})
    if breadcrumbs:
        graph.append({"@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": i + 1, "name": n, "item": u} for i, (n, u) in enumerate(breadcrumbs)]})
    if faq:
        graph.append({"@type": "FAQPage", "@id": url + "#faq", "mainEntity": [
            {"@type": "Question", "name": _clean(q, 300), "acceptedAnswer": {"@type": "Answer", "text": _clean(a, 1000)}} for q, a in faq]})
    tags.append('<script type="application/ld+json">%s</script>' % json.dumps({"@context": "https://schema.org", "@graph": graph}, ensure_ascii=False))
    return "<!-- wp:html -->\n" + "\n".join(tags) + "\n<!-- /wp:html -->\n"
