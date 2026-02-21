# Analyse: symlinks, vendor, .env, Nginx multi-worktree

## 1. vendor/ — symlink vs hardlink vs composer install

### Hoe de autoloader werkt

Composer genereert `vendor/composer/autoload_static.php` met `__DIR__`-relatieve paden:

```php
'tests\\' => array(
    0 => __DIR__ . '/../..' . '/tests',
),
'common\\' => array(
    0 => __DIR__ . '/../..' . '/common',
),
'app\\' => array(
    0 => __DIR__ . '/../..' . '/yii',     // ongebruikt — Yii's eigen autoloader wint
),
```

Vanuit `vendor/composer/`, `/../..` gaat 2 niveaus omhoog naar de `yii/` directory.
Daar vandaan resolvet `/tests` naar `yii/tests/`, `/common` naar `yii/common/`, etc.

**Opmerking:** De `app\\` namespace wordt NIET door Composer geresolved maar door Yii's
eigen autoloader via de `@app` alias (gezet op `dirname(__DIR__)` in `main.php:30`).
Bewijs: `app\models\Project` is niet vindbaar via Composer's autoloader alleen, maar
wél met Yii's autoloader erbij. Dit is bevestigd via CLI test.

### Symlink vendor/ → BROKEN

PHP's `__DIR__` resolvet symlinks naar het **fysieke pad**.

```
Worktree: /var/www/worktree/html-feat/yii/vendor → symlink → /var/www/worktree/html/yii/vendor
PHP ziet: __DIR__ = /var/www/worktree/html/yii/vendor/composer  (target, niet link)
Resolved: /../.. = /var/www/worktree/html/yii
           /tests = /var/www/worktree/html/yii/tests  ← MAIN REPO, niet worktree!
```

De test-classes, common-classes en identity-tests laden uit de **main repo** in plaats van
de worktree. Je test dan de verkeerde code. Dit is een stille fout — geen error, gewoon
verkeerde resultaten.

### Hardlink copy (`cp -al`) → WERKT

Hardlinks hebben geen "echt" vs "link" pad. PHP's `__DIR__` retourneert het **access pad**.

```
cp -al /var/www/worktree/html/yii/vendor /var/www/worktree/html-feat/yii/vendor

PHP ziet: __DIR__ = /var/www/worktree/html-feat/yii/vendor/composer  (access pad)
Resolved: /../.. = /var/www/worktree/html-feat/yii
           /tests = /var/www/worktree/html-feat/yii/tests  ← WORKTREE, correct!
```

**Voordelen:**
- Instant (geen bestanden kopiëren, alleen directory entries + inode refs)
- Nul extra diskruimte (zelfde inodes)
- Autoloader resolvet correct naar worktree-paden

**Voorwaarden:**
- Main en worktree moeten op hetzelfde filesystem staan (hardlinks kunnen niet cross-FS).
  Onder `/var/www/worktree/` is dit per definitie het geval.
- Als `composer.lock` verschilt tussen main en worktree (je hebt dependencies gewijzigd
  in de feature branch), moet je `composer install` draaien in plaats van hardlinken.

**Nadeel:**
- Na `composer update` in main, veranderen de hardlinked bestanden ook in de worktree
  (zelfde inode). Dit is meestal gewenst (zelfde lock file = zelfde vendor), maar kan
  verwarring veroorzaken als je het niet verwacht.

### `composer install` → ALTIJD CORRECT

Veiligste optie. Genereert een eigen autoloader met correcte `__DIR__` paden.

```
cd /var/www/worktree/html-feat/yii && composer install
```

- Eerste keer: ~30 seconden (met Composer cache)
- Daarna: ~5-10 seconden (alles gecached)
- Werkt altijd, ook bij gewijzigde `composer.json`

### Conclusie vendor/

| Methode | Correct? | Snelheid | Disk | Voorwaarde |
|---------|----------|----------|------|------------|
| Symlink | NEE | Instant | 0 | — |
| `cp -al` | JA | Instant | 0 | Zelfde FS, zelfde composer.lock |
| `composer install` | JA | ~30s/5s | ~200MB | Geen |

**Aanbeveling:** `cp -al` als default (snel, correct), fallback naar `composer install`
als `composer.lock` verschilt.

---

## 2. .env — symlink of kopiëren

### Voor tests via `docker exec`

**Niet nodig.** `docker exec` erft de environment variables van de container.
De container krijgt ze via `env_file: .env` in docker-compose.yml. Alle DB credentials,
PATH_MAPPINGS, etc. zijn beschikbaar zonder `.env` in de worktree.

### Voor een aparte docker-compose stack

Als je een tweede set containers wilt starten vanuit de worktree, heb je `.env` nodig
voor Docker Compose. Een symlink werkt prima:

```bash
ln -s ../html/.env /var/www/worktree/html-feat/.env
```

Docker Compose leest `.env` als gewoon tekstbestand. Geen PHP `__DIR__` issues.

### Conclusie .env

- Tests: **geen actie nodig**
- Aparte stack: symlink naar main `.env`

---

## 3. Nginx — meerdere worktrees via browser

### Huidige situatie

Eén server block, één root:
```nginx
server {
    listen 80;
    root /var/www/worktree/html/yii/web;      # na refactor met APP_ROOT
}
```

### Optie A: Meerdere server blocks, verschillende poorten

```nginx
# Main app
server {
    listen 80;
    root /var/www/worktree/html/yii/web;
    # ... PHP-FPM proxy
}

# Worktree: feature-x
server {
    listen 81;
    root /var/www/worktree/html-feat-x/yii/web;
    # ... PHP-FPM proxy (zelfde PHP-FPM, ander root)
}
```

| Pro | Con |
|-----|-----|
| Schone scheiding per worktree | Server block per worktree toevoegen |
| Standaard Nginx, geen hacks | Nginx reload nodig bij elke nieuwe worktree |
| Yii werkt ongewijzigd | Poort-allocatie beheren |
| Kan dezelfde PHP-FPM backend delen | docker-compose ports moeten mee |

**PHP-FPM sharing:** Alle worktrees kunnen dezelfde `pma_yii` PHP-FPM gebruiken.
De Nginx `SCRIPT_FILENAME` bepaalt welk bestand uitgevoerd wordt:
```nginx
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
```
`$document_root` is per server block anders → PHP voert de juiste `index.php` uit.

**Probleem:** PHP-FPM's basePath in Yii (`dirname(__DIR__)`) resolvet naar het pad van
de `index.php` die aangeroepen wordt. Als die in de worktree zit, laadt Yii correct de
worktree's config, models, etc. **Dit werkt.**

### Optie B: Dynamische config generatie

Een script dat:
1. De `project_worktree` tabel leest (of de directory listing van `/var/www/worktree/`)
2. Per worktree een server block genereert met een unieke poort
3. Nginx config schrijft en `nginx -s reload` draait

```bash
#!/bin/bash
# Genereer per worktree: listen op poort 80 + offset
PORT=81
for wt in /var/www/worktree/html-*/; do
    suffix=$(basename "$wt" | sed 's/^html-//')
    cat >> /tmp/worktrees.conf <<EOF
server {
    listen $PORT;
    root ${wt}yii/web;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass pma_yii:9001;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF
    PORT=$((PORT + 1))
done
```

| Pro | Con |
|-----|-----|
| Automatisch, geen handwerk | Script moet gedraaid worden na worktree create/remove |
| Schaalt naar N worktrees | Poorten moeten in docker-compose exposed worden |

### Optie C: PHP built-in server (voor quick testing)

```bash
docker exec -w /var/www/worktree/html-feat/yii pma_yii php yii serve --port=8080
```

| Pro | Con |
|-----|-----|
| Nul config | Single-threaded (geen concurrent AJAX) |
| Instant beschikbaar | Poort moet exposed zijn in docker-compose |
| Geen nginx reload | Niet production-like |

### Optie D: Reverse proxy met pad-prefix

```nginx
location ~ ^/_wt/([^/]+)/(.*)$ {
    alias /var/www/worktree/html-$1/yii/web/$2;
    # ... PHP proxy
}
```

| Pro | Con |
|-----|-----|
| Eén config, alle worktrees | Yii kent het prefix niet — URL routing breekt |
| Geen extra poorten | AssetManager, redirects, links zijn allemaal fout |

**Conclusie: niet haalbaar** zonder Yii-side wijzigingen (baseUrl configureren etc).

### Optie E: Subdomain routing

```nginx
server {
    listen 80;
    server_name ~^(?<wt>.+)\.localhost$;
    root /var/www/worktree/html-$wt/yii/web;
    # fallback: als $wt leeg of 'www', gebruik html/
}
```

| Pro | Con |
|-----|-----|
| Schone URLs | DNS/hosts configuratie nodig |
| Yii werkt ongewijzigd | Wildcard DNS op localhost is platform-afhankelijk |
| Onbeperkt worktrees | Tailscale/remote access complexer |

### Conclusie Nginx

**Voor unit tests:** Nginx is niet nodig. `docker exec -w` is genoeg.

**Voor browser testing:** Optie A (poort per worktree) is het meest pragmatisch.
De PHP-FPM backend kan gedeeld worden — alleen Nginx heeft extra server blocks nodig.
Optie B (script-generatie) is een logische vervolgstap als je het vaker doet.

Het is een apart probleem van de Docker refactor. De refactor (APP_ROOT parameterisatie)
maakt het makkelijker, maar lost het niet automatisch op.

---

## 4. Gedeeld test schema

### Huidige situatie

Alle tests gebruiken `DB_DATABASE_TEST` (default: `promptmanager_test`). Eén schema,
gedeeld door iedereen die tests draait.

### Is het een probleem?

**Bij sequentieel testen: nee.** Codeception fixtures doen setup/teardown per test.
Zolang je niet tegelijk tests draait vanuit twee worktrees, geen conflict.

**Bij parallel testen: ja.** Twee Codeception runs die tegelijk fixtures laden/opruimen
interfereren met elkaar.

### Oplossing: per-worktree test schema

```bash
# Bij worktree setup:
SUFFIX=feat-x
docker exec pma_yii mysql -h ${DB_HOST} -u root -p${DB_ROOT_PASSWORD} \
    -e "CREATE DATABASE IF NOT EXISTS promptmanager_test_${SUFFIX};"

# Bij test run:
docker exec -w /var/www/worktree/html-feat-x/yii \
    -e DB_DATABASE_TEST=promptmanager_test_feat_x \
    pma_yii vendor/bin/codecept run unit
```

**Maar:** het test schema moet de juiste tabellen hebben. Na `CREATE DATABASE` moet
je migraties draaien:

```bash
docker exec -w /var/www/worktree/html-feat-x/yii \
    -e DB_DATABASE_TEST=promptmanager_test_feat_x \
    pma_yii php yii migrate --interactive=0
```

Dit is een eenmalige setup per worktree. Nieuwe migraties in de feature branch worden
automatisch meegenomen.

### Conclusie test schema

- Sequentieel: geen probleem, geen actie
- Parallel: schema per worktree met `-e DB_DATABASE_TEST=...` override bij `docker exec`

---

## Samenvatting: alles bij elkaar

| Probleem | Oplossing | Methode |
|----------|-----------|---------|
| vendor/ | `cp -al` (hardlink copy) | Script, eenmalig per worktree |
| .env (tests) | Niet nodig | `docker exec` erft container env |
| .env (aparte stack) | Symlink | `ln -s ../html/.env` |
| Container ziet worktree | Parent mount `/var/www/worktree/` | Docker refactor |
| Nginx (unit tests) | Niet nodig | `docker exec -w` |
| Nginx (browser) | Poort per worktree | Server blocks + reload |
| Test schema (seq) | Gedeeld, geen probleem | — |
| Test schema (parallel) | Schema per worktree | `docker exec -e DB_DATABASE_TEST=...` |
