# Clientes identificados pelos logotipos publicados no site original (trixti.com.br/clientes.php)
CLIENTS = [
 ("images/unimed/70.png", "Central Nacional Unimed"), ("images/unimed/40.png", "Unimed Anápolis"), ("images/unimed/41.png", "Unimed Aquidauana"),
 ("images/unimed/42.png", "Unimed Arapiraca"), ("images/unimed/43.png", "Unimed Ariquemes"), ("images/unimed/45.png", "Unimed Cáceres"),
 ("images/unimed/46.png", "Unimed Campina Grande"), ("images/unimed/47.png", "Unimed Caruaru"), ("images/unimed/49.png", "Unimed Cerrado"),
 ("images/unimed/unimed-curitiba.jpg", "Unimed Curitiba"), ("images/unimed/50.png", "Unimed Corumbá"), ("images/unimed/51.png", "Unimed Dourados"),
 ("images/unimed/52.png", "FAMA – Federação das Unimeds da Amazônia"), ("images/unimed/53.png", "Unimed Imperatriz"), ("images/unimed/55.png", "Unimed Manaus"),
 ("images/unimed/56.png", "Unimed Oeste do Pará"), ("images/unimed/57.png", "Unimed Palmeira dos Índios"), ("images/unimed/58.png", "Unimed Picos"),
 ("images/unimed/59.png", "Unimed Regional de Floriano"), ("images/unimed/61.png", "Unimed Rio Verde"), ("images/unimed/62.png", "Unimed Rondônia"),
 ("images/unimed/unimed-sao-joao.jpg", "Unimed São João Nepomuceno"), ("images/unimed/unimed-serra-minas.jpg", "Unimed Serra de Minas"),
 ("images/unimed/65.png", "Unimed Sul do Pará"), ("images/unimed/66.png", "Unimed Teresina"), ("images/unimed/67.png", "Unimed Três Lagoas"),
 ("images/unimed/unimed-uba.jpg", "Unimed Ubá"), ("images/unimed/69.png", "Unimed Vilhena"), ("images/unimed/71.png", "Unimed Federação Minas"),
 ("images/economus.jpg", "Economus"), ("images/41.png", "Intermed"),
]
def logo_wall(items):
    return '<div class="trix-logos trix-reveal">' + "".join('<figure><img src="{{media:%s}}" alt="%s" title="%s" loading="lazy"></figure>' % (k, n, n) for k, n in items) + '</div>'
