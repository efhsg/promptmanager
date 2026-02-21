# Routing & Sessions: meerdere worktrees in de browser

## Kernidee

Per browser-tab een worktree kiezen. De routing-strategie volgt automatisch uit de
DB-keuze in het isolatieprofiel.

```
Gedeelde DB  → cookie-routing  → gedeelde session → één login
Eigen DB     → poort-routing   → eigen session    → apart inloggen
```

---

## Cookie-routing (gedeelde DB)

### Mechanisme

Eén Nginx server block, één poort. Een cookie `_worktree` bepaalt de document root.

```nginx
map $cookie__worktree $wt_root {
    default   /var/www/worktree/html;
    ~^(.+)$   /var/www/worktree/html-$1;
}

server {
    listen 80;
    root $wt_root/yii/web;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass pma_yii:${PHP_FPM_PORT};
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Hoe het werkt

1. Nginx leest cookie `_worktree` bij elk request
2. `map` vertaalt de cookie-waarde naar een filesystem root
3. `$document_root` wijst naar de worktree's `yii/web/`
4. PHP-FPM krijgt `SCRIPT_FILENAME` met het worktree-pad
5. Yii bootstrapt met `dirname(__DIR__)` → laadt de worktree's code

### Sessions

De `PHPSESSID` cookie is gebonden aan domain:port. Omdat alle worktrees dezelfde
domain:port delen, delen ze dezelfde session.

```
Tab 1: _worktree=       → main     → PHPSESSID=abc123 → user 42 ✓
Tab 2: _worktree=feat-x → worktree → PHPSESSID=abc123 → user 42 ✓
```

Eén login geldt voor alle tabs. De user-tabel is dezelfde (gedeelde DB), dus de
session is altijd geldig.

### Cookie zetten vanuit de UI

Een dropdown/selector in de PromptManager UI (bijv. in de navbar) die:

1. De beschikbare worktrees ophaalt (bestaande `/worktree/status` endpoint)
2. Bij selectie de `_worktree` cookie zet: `document.cookie = '_worktree=feat-x; path=/'`
3. De pagina herlaadt → Nginx serveert de gekozen worktree

De "main" optie zet een lege cookie of verwijdert hem.

### Beveiliging

De `map` regex accepteert elke cookie-waarde. Een kwaadwillende cookie
`_worktree=../../etc` zou Nginx een ongeldig root geven.

Mitigatie: valideer de cookie-waarde in de `map` met een strikt patroon:

```nginx
map $cookie__worktree $wt_root {
    default                     /var/www/worktree/html;
    ~^[a-zA-Z0-9_-]{1,100}$    /var/www/worktree/html-$1;
}
```

Alleen alfanumeriek, underscore, hyphen, max 100 tekens. Sluit path traversal uit.
Als de directory niet bestaat, geeft Nginx een 404 — geen security risico.

### Voordelen

- Nul Nginx config-wijzigingen bij nieuwe worktree (cookie-waarde is vrij)
- Nul extra poorten
- Eén login voor alles
- Worktree-selectie is instant (cookie zetten + reload)

---

## Poort-routing (eigen DB)

### Wanneer

Alleen als de worktree een eigen database-schema heeft. De session van main is dan
ongeldig (user-ID bestaat misschien niet in de worktree-DB).

### Mechanisme

Elk worktree krijgt een eigen Nginx server block op een unieke poort.

```nginx
# Main (bestaand)
server {
    listen 80;
    root /var/www/worktree/html/yii/web;
    # ...
}

# Worktree feat-x (gegenereerd door worktree/setup)
server {
    listen 81;
    root /var/www/worktree/html-feat-x/yii/web;
    # ... identiek aan main, ander root
}
```

### Sessions

Ander poort = ander origin = andere `PHPSESSID` cookie. Volledig gescheiden.

```
Tab 1: localhost:8503 → main     → PHPSESSID=abc → user 42 (main DB)
Tab 2: localhost:8504 → feat-x   → PHPSESSID=xyz → user 42 (worktree DB, apart ingelogd)
```

### Poort-allocatie

Het `worktree/setup` command wijst een poort toe:

- Bereik: `NGINX_PORT + 1` t/m `NGINX_PORT + 20` (bijv. 8504-8523)
- Opgeslagen in `project_worktree.nginx_port`
- Bij teardown vrijgegeven

### Docker port exposure

De poorten moeten bereikbaar zijn vanuit de host. Opties:

**A. Port range in docker-compose.yml:**
```yaml
pma_nginx:
  ports:
    - "127.0.0.1:${NGINX_PORT:-8503}:80"
    - "127.0.0.1:8504-8523:81-100"     # worktree range
```

**B. docker-compose.override.yml per worktree** (meer work, flexibeler)

**C. Nginx luistert op één poort, reverse proxy per pad** (weer het cookie-probleem)

Aanbeveling: **A** — port range vooraf reserveren. Simpelst.

### Config generatie en reload

Het `worktree/setup` command:

1. Genereert een server block in `/etc/nginx/conf.d/wt-{suffix}.conf`
2. Draait `nginx -s reload` (binnen de nginx container)

Het `worktree/teardown` command:

1. Verwijdert het conf-bestand
2. Draait `nginx -s reload`

---

## Gedeelde vereisten (beide routing-methodes)

### web/assets/ directory

Yii's AssetManager publiceert assets naar `web/assets/`. Dit is gitignored en leeg
in een worktree. Bij het eerste request maakt Yii de directory aan — maar alleen als
die writable is.

Onderdeel van `worktree/setup`:
```php
$assetsDir = $worktreePath . '/yii/web/assets';
if (!is_dir($assetsDir)) {
    mkdir($assetsDir, 0777, true);
}
```

### runtime/ directory

Yii schrijft logs, cache en sessions naar `runtime/`. Ook gitignored.

```php
$runtimeDir = $worktreePath . '/yii/runtime';
if (!is_dir($runtimeDir)) {
    mkdir($runtimeDir, 0777, true);
}
```

### PHP-FPM sharing

Beide methodes gebruiken dezelfde PHP-FPM backend (`pma_yii`). Nginx bepaalt via
`SCRIPT_FILENAME` welke code uitgevoerd wordt. PHP-FPM hoeft niets te weten van
worktrees.

---

## Samenvatting

| | Cookie-routing | Poort-routing |
|---|----------------|---------------|
| **Trigger** | Gedeelde DB (default) | Eigen DB-schema |
| **Poorten** | Één (bestaande) | Eén per worktree |
| **Sessions** | Gedeeld | Gescheiden |
| **Login** | Eén keer | Per worktree |
| **Nginx config** | Eenmalig (map block) | Per worktree (server block) |
| **Nginx reload** | Nooit | Bij create/remove |
| **Docker wijziging** | Geen | Port range exposen |
| **UI element** | Worktree-selector dropdown | Link met poort-nummer |
| **Setup** | `worktree/setup` zet default | `worktree/setup` genereert config |
| **Teardown** | Niks (cookie is client-side) | Verwijder config + reload |

## Implementatievolgorde

1. **Cookie-routing eerst** — dekt het default scenario (gedeelde DB), nul Docker
   wijzigingen, één `map` block in nginx.conf.template
2. **Poort-routing later** — alleen nodig voor eigen DB, meer infra-werk
3. **UI dropdown** — simpel JS component dat `/worktree/status` leest en cookie zet
