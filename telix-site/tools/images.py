#!/usr/bin/env python3
"""Otimiza as imagens do site original (recorte de molduras, redimensionamento, WebP) -> dist/media/."""
import os, json
from PIL import Image
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ORIG = os.environ.get('TX_ORIG', '/tmp/claude-0/-home-user-OutrosProjetos/f76200df-974d-571b-af64-fccf87577bbf/scratchpad/orig')
OUT = os.path.join(ROOT, 'dist', 'media'); os.makedirs(OUT, exist_ok=True)
# token: (arquivo original, nome de saída, recorte px, largura máx., formato, alt)
SPEC = {
 'IMG_CENTRAL': ('central.png', 'telix-central-de-atendimento', 0, 1400, 'webp', 'Equipe de atendentes da central de atendimento Télix trabalhando com headsets'),
 'IMG_CENTRAL_A': ('img/img_central_a.png', 'telix-atendente-call-center', 0, 1000, 'webp', 'Atendente sorridente com headset em central de atendimento'),
 'IMG_REG_SUP': ('img/img_reg_sup_a.png', 'telix-saude-suplementar-cartao-beneficiario', 0, 1000, 'webp', 'Entrega de cartão de beneficiário de plano de saúde'),
 'IMG_BG01': ('img/bg_01.jpg', 'telix-equipe-medica-regulacao', 0, 1200, 'webp', 'Médico e enfermeira consultando informações de paciente em tablet'),
 'IMG_SARH1': ('img/sarh_01.jpg', 'telix-regulacao-profissional-saude-headset', 0, 1100, 'webp', 'Profissional de saúde com headset atendendo na central de regulação'),
 'IMG_SARH2': ('img/sarh_02.jpg', 'telix-equipe-especializada-atendimento', 0, 1100, 'webp', 'Equipe de atendentes com headsets digitando em computadores'),
 'IMG_SARH3': ('img/sarh_03.jpg', 'telix-whatsapp-central-de-atendimento', 0, 1100, 'webp', 'Arte com ícone do WhatsApp e o texto WhatsApp Central de Atendimento'),
 'IMG_SARH4': ('img/sarh_04.jpg', 'telix-servicos-inclusos-regulacao', 0, 1100, 'webp', 'Médico com prancheta sobre mesa com notebook e estetoscópio'),
 'IMG_SARH5': ('img/sarh_05.jpg', 'telix-custo-beneficio-saude', 0, 1100, 'webp', 'Mão inserindo moeda com cruz médica em cofrinho, simbolizando economia em saúde'),
 'IMG_SOL1': ('img/solucoes01.jpg', 'telix-solucoes-central-de-atendimento', 12, 900, 'webp', 'Atendente sorridente com headset e colegas ao fundo'),
 'IMG_SOL2': ('img/solucoes02.jpg', 'telix-solucoes-sistema-de-atendimento', 12, 900, 'webp', 'Atendente ajustando o microfone do headset diante do computador'),
 'IMG_SOL3': ('img/solucoes03.jpg', 'telix-solucoes-pesquisa-saude', 12, 900, 'webp', 'Profissional de saúde com estetoscópio usando tablet'),
 'IMG_SOL4': ('img/solucoes04.jpg', 'telix-solucoes-sac-equipe', 12, 900, 'webp', 'Fileira de atendentes de SAC com headsets em computadores'),
 'IMG_SOL5': ('img/solucoes05.jpg', 'telix-equipe-maos-unidas-integridade', 0, 1600, 'webp', 'Mãos empilhadas simbolizando união e integridade da equipe'),
 'IMG_TRABALHE': ('img/back_trabalhe.jpg', 'telix-trabalhe-conosco-atendente', 0, 1600, 'webp', 'Atendente sorridente com headset em ambiente corporativo'),
 'IMG_TRABALHE_A': ('img/img_trabalhe_a.jpg', 'telix-trabalhe-conosco-equipe', 0, 1600, 'webp', 'Atendentes sorridentes com headsets em estações de trabalho'),
 'IMG_COMITE1': ('lgpd/img/comite-01.png', 'telix-comite-compliance', 0, 800, 'webp', 'Executivo tocando ícones virtuais de compliance'),
 'IMG_COMITE2': ('lgpd/img/comite-02.png', 'telix-comite-protecao-de-dados', 0, 800, 'webp', 'Tablet com cadeado digital representando proteção de dados'),
 'IMG_COMITE3': ('lgpd/img/comite-03.png', 'telix-comite-seguranca-e-qualidade', 0, 800, 'webp', 'Notebook com cadeado digital e circuitos representando segurança da informação'),
 'IMG_LOGO_PNG': ('img/logo-telix.png', 'telix-logo', 0, 1200, 'png', 'Logotipo Télix Comunicação e Relacionamento'),
}
LOGOS = {
 'LOGO_ARAGUAIA': 'img/unimed-araguaia.png', 'LOGO_CACERES': 'img/unimed-caceres.png', 'LOGO_CORUMBA': 'img/unimed-corumba.png', 'LOGO_DOURADOS': 'img/unimed-dourado.png',
 'LOGO_MARANHAO_SUL': 'img/unimed-imperatriz.png', 'LOGO_MATO_GROSSO': 'img/unimed-mato-grosso.png', 'LOGO_METROPOLITANA_AGRESTE': 'img/unimed-metropolitana.png',
 'LOGO_NORTE_MT': 'img/unimed-norte-mato-grosso.png', 'LOGO_PORTO_VELHO': 'img/unimed-porto_velho.png', 'LOGO_RONDONOPOLIS': 'img/unimed-rondonopolis.png',
 'LOGO_TRES_LAGOAS': 'img/unimed-tres-lagoas.png', 'LOGO_VALE_JAURU': 'img/unimed-vale-do-jauru.png', 'LOGO_SUL_PARA': 'img/Logos_Sul_Para.png',
 'LOGO_REGIONAL_SUL_GO': 'img/unimed-regional-sul.png', 'LOGO_VALE_SEPOTUBA': 'img/unimed-vale-do-sepotuba.png',
}
manifest = {}
for tok, (src, name, crop, maxw, fmt, alt) in SPEC.items():
    im = Image.open(os.path.join(ORIG, src))
    if crop:
        w, h = im.size; im = im.crop((crop, crop, w - crop, h - crop))
    if im.width > maxw:
        im = im.resize((maxw, round(im.height * maxw / im.width)), Image.LANCZOS)
    fn = f'{name}.{fmt}'
    p = os.path.join(OUT, fn)
    if fmt == 'webp':
        im = im.convert('RGBA') if im.mode in ('P', 'LA') else im
        im.save(p, 'WEBP', quality=80, method=6)
    else:
        im.save(p, 'PNG', optimize=True)
    manifest[tok] = {'file': fn, 'alt': alt, 'w': im.width, 'h': im.height}
for tok, src in LOGOS.items():
    im = Image.open(os.path.join(ORIG, src)).convert('RGBA')
    fn = 'cliente-' + os.path.basename(src).lower().replace('_', '-')
    im.save(os.path.join(OUT, fn), 'PNG', optimize=True)
    manifest[tok] = {'file': fn, 'alt': '', 'w': im.width, 'h': im.height}
json.dump(manifest, open(os.path.join(OUT, 'manifest.json'), 'w'), ensure_ascii=False, indent=1)
print(len(manifest), sum(os.path.getsize(os.path.join(OUT, v['file'])) for v in manifest.values()) // 1024, 'KB')
