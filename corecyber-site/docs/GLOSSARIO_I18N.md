# Translation guide — CoreCyber website (pt-BR → en and es)

CoreCyber is a Brazilian cybersecurity SaaS platform made by EBAEM (Brasília). The site sells it to Brazilian and Latin American companies and public bodies. Tone: warm, clear, confident, persuasive but honest; short sentences; natural (not literal) — write as a native marketing/legal copywriter would. Legal/policy texts: precise and formal but readable.

## Hard rules (a validator checks them)
1. Output = a JSON object with EXACTLY the same keys as the input batch; every value translated; valid JSON (UTF-8, no trailing commas).
2. Values may contain HTML. Keep every tag, in the same order and nesting, with the same attributes (href, class, id, target, rel, style, lang, data-noi18n). Translate only human text. Do not add or remove tags. Inline <b>, <strong>, <a>, <span>, <small>, <code>, <br> must stay.
3. Keep placeholders like {n}, {p}, {wa} exactly.
4. Never translate: CoreCyber, EBAEM, Cy (the mascot), FingerPrint, Alicerce360, GLPI, Telegram, Slack, WhatsApp, Google, Microsoft, OpenAI, FreeLLMAPI, VLibras, ISO/IEC codes, CVE/CVSS/EPSS/KEV/SBOM/SARIF/OWASP/NVD/MITRE ATT&CK, URLs, e-mails, protocol codes (e.g. LGPD-2026-000045, CASE-2026-0141, REL-2026-004512), document codes (POL-CCY-...).
5. Brazilian laws keep their Portuguese names/numbers: "Lei 13.709/2018", "LGPD", "Código Penal, art. 154-A", "Lei 12.737/2012", "Lei 14.133/2021", "CDC, art. 49", "Marco Civil da Internet", "ANPD", "Resolução CD/ANPD nº 15/2024". You may add a short gloss on first appearance inside the same string when helpful (e.g. EN "LGPD (Brazil's General Data Protection Law)"; ES "LGPD (Ley General de Protección de Datos de Brasil)") — but only in running text, not in short labels/badges.
6. Money stays in Brazilian reais: keep "R$". Number formatting: EN uses 3,951 / 99.92% / 1,890; ES uses 3.951 / 99,92 % / 1.890.
7. Do not invent content; do not drop sentences. Keep it roughly the same length (buttons/labels short).
8. No square brackets in visible text, no emoji.

## Glossary (EN | ES)
- ativo(s) monitorado(s) → monitored asset(s) | activo(s) monitoreado(s)
- superfície de ataque (externa) → (external) attack surface | superficie de ataque (externa)
- varredura (não invasiva) → (non-invasive) scan | escaneo (no invasivo)
- laudo → report (assessment report) | informe
- diagnóstico gratuito de exposição → free exposure assessment | diagnóstico gratuito de exposición
- prova de conceito → proof of concept (POC) | prueba de concepto (POC)
- chamado → ticket | ticket
- caso de incidente → incident case | caso de incidente
- correção remota → remote fix | corrección remota
- agente → agent | agente
- Declaração de Aplicabilidade → Statement of Applicability (SoA) | Declaración de Aplicabilidad (SoA)
- controles → controls | controles
- titular (de dados) → data subject | titular (de los datos)
- encarregado (DPO) → Data Protection Officer (DPO) | encargado de protección de datos (DPO)
- controlador / operador → controller / processor | responsable / encargado del tratamiento
- Portal do Titular → Data Subject Portal | Portal del Titular
- Canal de Integridade → Integrity Channel | Canal de Integridad
- Ouvidoria → Ombudsman office | Defensoría (Ouvidoria)
- Central de Confiança → Trust Center | Centro de Confianza
- Política de Comercialização → Sales Policy | Política de Comercialización
- Termos de Uso → Terms of Use | Términos de Uso
- Divulgação Responsável → Responsible Disclosure | Divulgación Responsable
- Área do Cliente → Customer Area | Área del Cliente
- plano Essencial / Profissional / Avançado → Essential / Professional / Advanced plan | plan Esencial / Profesional / Avanzado
- Em evolução / Disponível / Roadmap → In progress / Available / Roadmap | En evolución / Disponible / Roadmap
- status page(s) → status page(s) | página(s) de estado
- inteligência de ameaças → threat intelligence | inteligencia de amenazas
- descomissionamento → decommissioning | desmantelamiento (baja segura)
- protocolo (número de referência) → reference number | protocolo
- dias úteis → business days | días hábiles
- cliente fundador → founding customer | cliente fundador
- Libras → Brazilian Sign Language (Libras) | lengua de señas brasileña (Libras)
