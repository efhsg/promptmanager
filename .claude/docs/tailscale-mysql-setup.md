# Tailscale + Centrale MySQL Setup

## Overzicht

Configureer Ubuntu als centrale MySQL server, verbind Windows via Tailscale VPN.

## Huidige situatie

- MySQL draait in Docker op poort `${DB_PORT}` (standaard 3307 volgens .env)
- Database: `promptmanager`
- User: `promptmanager`

---

## Stap 1: Tailscale installeren op Ubuntu (server)

```bash
# Installeer Tailscale
curl -fsSL https://tailscale.com/install.sh | sh

# Start en authenticeer
sudo tailscale up

# Noteer je Tailscale IP (100.x.x.x)
tailscale ip -4
```

## Stap 2: Tailscale installeren op Windows (client)

1. Download van https://tailscale.com/download/windows
2. Installeer en log in met hetzelfde account
3. Beide machines zijn nu verbonden via Tailscale netwerk

## Stap 3: MySQL toegankelijk maken voor Tailscale

MySQL bindt standaard alleen aan localhost. De huidige `docker-compose.yml` bindt al aan `0.0.0.0:${DB_PORT}:3306`, dus MySQL is al bereikbaar op het Tailscale IP.

**Firewall openen (indien nodig):**

```bash
# Ubuntu firewall (ufw)
sudo ufw allow from 100.64.0.0/10 to any port 3307 proto tcp
```

## Stap 4: MySQL user rechten voor remote access

```bash
# Verbind met MySQL
docker exec -it pma_mysql mysql -u root -p

# Geef remote rechten aan de user
GRANT ALL PRIVILEGES ON promptmanager.* TO 'promptmanager'@'%' IDENTIFIED BY 'promptmanager';
FLUSH PRIVILEGES;
```

> Note: De Docker MySQL image staat standaard al '%' (alle hosts) toe voor de aangemaakte user.

## Stap 5: Windows .env aanpassen

Op de Windows machine, pas `.env` aan:

```env
# Verander dit:
DB_HOST=pma_mysql

# Naar het Tailscale IP van Ubuntu (voorbeeld):
DB_HOST=100.x.x.x
DB_PORT=3307
```

## Stap 6: Windows docker-compose aanpassen

Op Windows hoef je de MySQL service niet te draaien. Maak een `docker-compose.override.yml` op Windows:

```yaml
services:
  pma_mysql:
    profiles:
      - disabled

  pma_yii:
    depends_on: []
```

Dit zorgt ervoor dat:
- `pma_mysql` niet start (via disabled profile)
- `pma_yii` niet wacht op `pma_mysql`

## Stap 7: Testen

```bash
# Vanaf Windows, test verbinding
mysql -h 100.x.x.x -P 3307 -u promptmanager -p

# Of via Docker op Windows
docker exec pma_yii php -r "new PDO('mysql:host=100.x.x.x;port=3307;dbname=promptmanager', 'promptmanager', 'promptmanager'); echo 'OK';"
```

---

## Verificatie checklist

- [ ] Tailscale geïnstalleerd op beide machines
- [ ] `tailscale ping <andere-machine>` werkt
- [ ] MySQL bereikbaar vanaf Windows via Tailscale IP
- [ ] PromptManager op Windows kan data lezen/schrijven
- [ ] Wijzigingen zichtbaar op beide machines

---

## Alternatief: Alleen lokaal netwerk

Als beide machines altijd op hetzelfde LAN zitten, kun je ook het lokale IP gebruiken:

```bash
# Ubuntu lokaal IP vinden
ip addr show | grep "inet 192"

# Gebruik dit IP in Windows .env
DB_HOST=192.168.x.x
```

**Nadeel:** Werkt niet buiten je thuisnetwerk.

---

## Troubleshooting

### Kan niet verbinden met MySQL

1. Check of Docker draait op Ubuntu: `docker ps | grep pma_mysql`
2. Check of poort open is: `sudo netstat -tlnp | grep 3307`
3. Check Tailscale verbinding: `tailscale status`

### "Access denied" fout

1. Controleer user rechten in MySQL:
   ```sql
   SELECT host, user FROM mysql.user WHERE user = 'promptmanager';
   ```
2. Zorg dat `%` (alle hosts) aanwezig is

### Langzame verbinding

Tailscale gebruikt DERP relay servers als directe verbinding niet lukt. Check:
```bash
tailscale netcheck
```
