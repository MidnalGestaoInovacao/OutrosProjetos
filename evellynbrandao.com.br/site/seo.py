# -*- coding: utf-8 -*-
"""SEO sem plugin: meta description, Open Graph, Twitter Cards e JSON-LD (schema.org) por página/matéria.
As tags são emitidas no início do conteúdo (HTML estático, lido por buscadores e redes sociais) e, no carregamento,
o eb.js move meta/link para o <head>."""
import json, html as H, re

SITE = "https://evellynbrandao.com.br"
SITE_NAME = "Évellyn Brandão"
PERSON = {
    "@type": "Person", "@id": SITE + "/#person", "name": "Évellyn Brandão",
    "jobTitle": "CEO e Fundadora", "worksFor": {"@id": SITE + "/#org"},
    "url": SITE + "/sobre/", "sameAs": ["https://www.linkedin.com/in/evellynbrandao/"],
    "description": "Executiva com 10 anos de mercado financeiro, 8 deles no Itaú Unibanco. CEO e Fundadora da BS Agro Capital.",
    "knowsAbout": ["Crédito rural", "Plano Safra", "CPR", "Barter", "Fiagro", "Estruturação de operações de crédito", "Agronegócio", "Negociação", "Liderança de equipes comerciais"],
    "alumniOf": [{"@type": "CollegeOrUniversity", "name": "PUCRS - Pontifícia Universidade Católica do Rio Grande do Sul"}, {"@type": "CollegeOrUniversity", "name": "Faculdade Ciências da Vida"}],
    "address": {"@type": "PostalAddress", "addressLocality": "Goiânia", "addressRegion": "GO", "addressCountry": "BR"},
}
ORG = {
    "@type": "Organization", "@id": SITE + "/#org", "name": "BS Agro Capital",
    "url": SITE + "/bs-agro-capital/", "founder": {"@id": SITE + "/#person"}, "foundingDate": "2026-02",
    "description": "Soluções financeiras para o agronegócio: originação e estruturação de operações de crédito conectando produtores rurais e empresas do agro a bancos, cooperativas, fundos de investimento e diferentes fontes de capital.",
    "address": {"@type": "PostalAddress", "addressLocality": "Anápolis", "addressRegion": "GO", "addressCountry": "BR"},
    "email": "contato@evellynbrandao.com.br", "areaServed": "BR",
    "contactPoint": [
        {"@type": "ContactPoint", "contactType": "customer service", "email": "contato@evellynbrandao.com.br", "availableLanguage": ["pt-BR", "en", "es"]},
        {"@type": "ContactPoint", "contactType": "Encarregado de Proteção de Dados (DPO)", "email": "dpo@evellynbrandao.com.br"},
        {"@type": "ContactPoint", "contactType": "Ouvidoria e Compliance", "email": "ouvidoria@evellynbrandao.com.br"},
    ],
}

def _clean(s, n=160):
    s = re.sub(r"<[^>]+>", " ", s or ""); s = H.unescape(re.sub(r"\s+", " ", s)).strip()
    return (s[: n - 1].rsplit(" ", 1)[0] + "…") if len(s) > n else s

def global_jsonld(logo_url, og_url):
    data = {"@context": "https://schema.org", "@graph": [
        dict(PERSON, image=og_url),
        dict(ORG, logo={"@type": "ImageObject", "url": logo_url}),
        {"@type": "WebSite", "@id": SITE + "/#website", "url": SITE + "/", "name": SITE_NAME, "inLanguage": "pt-BR",
         "publisher": {"@id": SITE + "/#person"},
         "potentialAction": {"@type": "SearchAction", "target": SITE + "/?s={search_term_string}", "query-input": "required name=search_term_string"}},
    ]}
    return '<script type="application/ld+json">%s</script>' % json.dumps(data, ensure_ascii=False)

def head_links(favicon_url, logo_url):
    return ('<link rel="icon" type="image/png" sizes="512x512" href="%s"><link rel="apple-touch-icon" sizes="180x180" href="%s">'
            '<link rel="shortcut icon" href="%s"><meta name="theme-color" content="#050505"><meta name="msapplication-TileColor" content="#050505">'
            '<meta name="msapplication-TileImage" content="%s">' % (favicon_url, favicon_url, favicon_url, favicon_url))

def seo_block(title, description, path, image, kind="website", article=None, breadcrumbs=None, keywords=None):
    """Retorna um bloco wp:html com as tags de SEO. path: caminho absoluto iniciando por '/'."""
    url = SITE + path
    desc = _clean(description)
    t = H.escape(title, quote=True); d = H.escape(desc, quote=True)
    tags = ['<!-- SEO -->',
            '<meta name="description" content="%s">' % d,
            '<meta property="og:locale" content="pt_BR"><meta property="og:type" content="%s">' % ("article" if kind == "article" else "website"),
            '<meta property="og:site_name" content="%s">' % SITE_NAME,
            '<meta property="og:title" content="%s"><meta property="og:description" content="%s"><meta property="og:url" content="%s">' % (t, d, url),
            '<meta property="og:image" content="%s"><meta property="og:image:width" content="%d"><meta property="og:image:height" content="%d">' % (image, 1200, 675 if kind == "article" else 630),
            '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="%s"><meta name="twitter:description" content="%s"><meta name="twitter:image" content="%s">' % (t, d, image)]
    if keywords:
        tags.append('<meta name="keywords" content="%s">' % H.escape(", ".join(keywords), quote=True))
    graph = []
    if kind == "article" and article:
        tags.append('<meta property="article:published_time" content="%s"><meta property="article:author" content="%s">' % (article["date"], SITE + "/sobre/"))
        graph.append({"@type": "Article", "@id": url + "#article", "headline": title, "description": desc, "image": [image],
                      "datePublished": article["date"], "dateModified": article.get("modified", article["date"]),
                      "author": {"@id": SITE + "/#person"}, "publisher": {"@id": SITE + "/#org"},
                      "mainEntityOfPage": {"@type": "WebPage", "@id": url}, "inLanguage": "pt-BR",
                      "articleSection": article.get("section", ""), "keywords": ", ".join(article.get("tags", []))})
    else:
        graph.append({"@type": "WebPage", "@id": url, "url": url, "name": title, "description": desc, "inLanguage": "pt-BR",
                      "isPartOf": {"@id": SITE + "/#website"}, "about": {"@id": SITE + "/#person"}, "primaryImageOfPage": {"@type": "ImageObject", "url": image}})
    if breadcrumbs:
        graph.append({"@type": "BreadcrumbList", "itemListElement": [
            {"@type": "ListItem", "position": i + 1, "name": n, "item": SITE + p} for i, (n, p) in enumerate(breadcrumbs)]})
    tags.append('<script type="application/ld+json">%s</script>' % json.dumps({"@context": "https://schema.org", "@graph": graph}, ensure_ascii=False))
    return "<!-- wp:html -->\n" + "\n".join(tags) + "\n<!-- /wp:html -->\n"
