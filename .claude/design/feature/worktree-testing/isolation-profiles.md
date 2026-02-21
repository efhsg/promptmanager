# Worktree Isolatieprofielen

## Concept

De `WorktreePurpose` bepaalt niet alleen het label, maar ook het **default isolatieniveau**.
Elke resource (vendor, env, database, nginx) kan gedeeld of onafhankelijk zijn.
De gebruiker kan per worktree de defaults overriden.

## Isolatie-dimensies

| Dimensie | Gedeeld | Onafhankelijk |
|----------|---------|---------------|
| **vendor/** | `cp -al` van main (instant, 0 disk) | `composer install` (eigen autoloader) |
| **.env** | Symlink naar `../html/.env` | Eigen kopie (andere DB, API keys, etc.) |
| **Database** | Zelfde test schema (`promptmanager_test`) | Eigen schema + migraties |
| **Nginx** | Geen server block (alleen CLI tests) | Eigen poort (browser testing) |

## Defaults per purpose

| | vendor/ | .env | Database | Nginx |
|---|---------|------|----------|-------|
| **Bugfix** | Gedeeld | Gedeeld | Gedeeld | Geen |
| **Feature** | Gedeeld | Gedeeld | Gedeeld | Geen |
| **Refactor** | Gedeeld | Gedeeld | Gedeeld | Geen |
| **Spike** | Gedeeld | Gedeeld | Gedeeld | Geen |
| **Custom** | Gedeeld | Gedeeld | Gedeeld | Geen |

Waarom alles default "gedeeld": het overgrote deel van het werk is code wijzigen
en tests draaien. De uitzonderingen (nieuwe dependency, schema-wijziging, browser
testing) zijn opt-in.

## Wanneer override je?

### vendor/ → onafhankelijk

Als je `composer.json` wijzigt in de feature branch (nieuwe dependency, versie bump).
De `composer.lock` in de worktree wijkt dan af van main.

**Detectie:** Vergelijk `composer.lock` hash van worktree met main. Als ze verschillen,
adviseer `composer install` i.p.v. `cp -al`.

### .env → eigen kopie

Als je feature een nieuwe env var nodig heeft die de main `.env` (nog) niet kent.
Bijvoorbeeld een nieuwe API key, of een andere `PATH_MAPPINGS` configuratie.

### Database → eigen schema

Als je feature nieuwe migraties bevat die het schema wijzigen. De main test database
heeft die tabellen/kolommen nog niet.

**Detectie:** Vergelijk `yii/migrations/` tussen worktree en main. Als de worktree
nieuwe migraties bevat, adviseer een eigen test schema.

### Nginx → eigen poort

Als je frontend-wijzigingen moet testen in de browser (layout, JS, Quill editor, etc.).
Niet nodig voor puur backend/API werk.

## Setup flow

Na `WorktreeService::create()` (git worktree aanmaken + DB record), een setup stap:

```
1. Lees purpose van ProjectWorktree record
2. Bepaal defaults op basis van purpose
3. Toon gebruiker: "Worktree setup — deze defaults, wil je iets aanpassen?"
4. Voer gekozen profiel uit:
   a. vendor/: cp -al of composer install
   b. .env: symlink of kopie
   c. database: skip of create schema + migrate
   d. nginx: skip of genereer server block + reload
```

### Beslissing: Yii console command

**Gekozen:** `php yii worktree/setup --id=42`

**Waarom:** Heeft toegang tot het ProjectWorktree record (purpose, paden, project config),
de database (bestaande worktrees, poort-allocatie), en kan slimme detectie doen
(composer.lock diff, nieuwe migraties). Past in het bestaande console command patroon.

```bash
# Default profiel (alles gedeeld, op basis van purpose):
docker exec pma_yii php yii worktree/setup --id=42

# Override specifieke dimensies:
docker exec pma_yii php yii worktree/setup --id=42 --vendor=install --db=isolated

# Interactief (toont defaults, vraagt bevestiging):
docker exec -it pma_yii php yii worktree/setup --id=42 --interactive
```

Het command:
1. Leest het ProjectWorktree record (purpose, paden)
2. Detecteert afwijkingen (composer.lock diff, nieuwe migraties)
3. Past defaults aan op basis van detectie
4. Toont samenvatting en voert uit
5. Slaat gekozen profiel op in DB (voor teardown)

## Stappen

### Stap 1: `cp -al` vendor (altijd, als default)

```bash
MAIN=/var/www/worktree/html/yii/vendor
TARGET=/var/www/worktree/html-feat/yii/vendor
cp -al "$MAIN" "$TARGET"
```

### Stap 2: .env symlink (default) of kopie

```bash
# Gedeeld (default):
ln -s ../html/.env /var/www/worktree/html-feat/.env

# Onafhankelijk:
cp /var/www/worktree/html/.env /var/www/worktree/html-feat/.env
```

### Stap 3: Database schema (optioneel)

```bash
SUFFIX=feat-x
DB_TEST="promptmanager_test_${SUFFIX}"

# Create + migrate
docker exec pma_yii mysql -h "$DB_HOST" -u root -p"$DB_ROOT_PASSWORD" \
    -e "CREATE DATABASE IF NOT EXISTS \`${DB_TEST}\`;"

docker exec -w /var/www/worktree/html-feat/yii \
    -e "DB_DATABASE_TEST=${DB_TEST}" \
    pma_yii php yii migrate --interactive=0
```

### Stap 4: Nginx server block (optioneel)

```bash
PORT=8504  # volgende vrije poort
cat >> /etc/nginx/conf.d/worktrees.conf <<EOF
server {
    listen ${PORT};
    root /var/www/worktree/html-feat/yii/web;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass pma_yii:${PHP_FPM_PORT};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
EOF
docker exec pma_nginx nginx -s reload
```

## Teardown

Bij `WorktreeService::remove()`:

1. Verwijder git worktree (bestaand)
2. Drop eigen test schema als die bestaat
3. Verwijder nginx server block als die bestaat + reload
4. Verwijder DB record (bestaand)

## Open vragen

1. **Waar slaan we het isolatieprofiel op?** Nieuwe kolommen op `project_worktree`
   (`vendor_mode`, `env_mode`, `db_schema`, `nginx_port`)? Of een apart JSON veld?

2. ~~**Wie voert de setup uit?**~~ → **Besloten: Yii console command** (`php yii worktree/setup`)

3. **Poort-allocatie voor Nginx:** Vast bereik (8504-8520)? Dynamisch? Opgeslagen
   in DB?

4. **`cp -al` binnen Docker:** De container draait als appuser. Heeft die rechten
   om hardlinks te maken in `/var/www/worktree/`? Ja, als de parent directory
   writable is en bestanden dezelfde owner hebben.

5. **Teardown via console command?** Symmetrisch: `php yii worktree/teardown --id=42`
   dat het profiel leest en de juiste cleanup doet (drop schema, remove nginx block).
   Of integreren in de bestaande `WorktreeService::remove()`?
