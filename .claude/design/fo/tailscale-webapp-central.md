# Tailscale + Centrale Webapplicatie voor PromptManager - Design Document

## Document Metadata

| Eigenschap | Waarde |
|------------|--------|
| Versie | 2.2 |
| Status | **Draft** |
| Laatste update | 2026-01-19 |
| Eigenaar | Development Team |

---

## Scope

### Omgevingen

| Omgeving | In Scope | Toelichting |
|----------|----------|-------------|
| Centrale server | **Ja** | Ubuntu server draait PromptManager + MySQL |
| Clients (browser) | **Ja** | Laptop/desktop toegang via browser |
| CI/CD | Nee | Blijft lokale setup per pipeline gebruiken |
| Staging | Nee | Niet van toepassing |
| Production | Nee | Niet van toepassing |

### Document Type

Dit document is een **volledig nieuw ontwerp** (geen delta op bestaand). Het beschrijft de transitie van lokale PromptManager instanties naar een centrale webapplicatie op een gedeelde server.

### Niet in Scope

| Item | Reden |
|------|-------|
| High availability / replication | Overkill voor 1-2 gebruikers |
| SSL/TLS certificaten | Tailscale biedt end-to-end encryptie |
| Load balancing | Enkele server is voldoende |
| Multi-tenant applicatie | Single user scenario |
| Cloud hosting (AWS, etc.) | Bestaande Ubuntu server gebruiken |
| File field functionaliteit | Vereist lokale bestandstoegang (zie beperkingen) |

---

## Impact op Bestaande Functionaliteit

### Workflow Wijzigingen

| Huidige Workflow | Na Centralisatie | Impact | Mitigatie |
|------------------|------------------|--------|-----------|
| Lokale applicatie starten | **Vervalt** - browser naar centrale server | Laag | Bookmark naar `http://ubuntu-server:8080` |
| Offline development | **Vervalt** - netwerk vereist | Hoog | Duidelijke documentatie |
| Docker/code op elke machine | **Vervalt** - alleen browser nodig | Positief | Vereenvoudiging |
| Lokale tests draaien | **Vervalt** - tests draaien op server | Medium | SSH toegang voor tests indien nodig |

### Functionaliteit die Verandert

| Functionaliteit | Huidige Situatie | Na Centralisatie | Regressierisico |
|-----------------|------------------|------------------|-----------------|
| Prompt CRUD | Lokaal, instant | Via browser, ~50ms | Laag |
| Zoeken/Filteren | Lokaal | Via browser | Laag |
| Code deployment | Per machine `git pull` | Alleen op server | Positief - één plek |

### Functionaliteit die Vervalt

| Functionaliteit | Reden | Alternatief |
|-----------------|-------|-------------|
| **File fields** | Server heeft geen toegang tot lokale bestanden | Kopieer bestanden naar server, of gebruik tekst-invoer |
| Offline werken | Centrale server vereist | Geen - accepteer als beperking |
| Lokale development | App draait op server | SSH naar server voor development |

### File Fields Beperking

> **BELANGRIJK:** File fields die verwijzen naar lokale bestanden (bijv. `/home/jan/projects/myapp/src/App.php`) werken NIET meer na centralisatie. De server kan deze bestanden niet lezen.

**Alternatieven:**

| Optie | Beschrijving | Geschikt voor |
|-------|--------------|---------------|
| Tekst kopiëren | Kopieer bestandsinhoud handmatig naar prompt | Incidenteel gebruik |
| Bestanden op server | Clone repositories naar server, gebruik server-paden | Frequente file fields |
| Geen file fields | Gebruik alleen tekst-type velden | Eenvoudigste oplossing |

**Aanbeveling:** Als je file fields intensief gebruikt, overweeg repositories te clonen naar de server en `root_directory` aan te passen naar server-paden.

### Regressierisico's

| Risico | Kans | Impact | Detectie | Mitigatie |
|--------|------|--------|----------|-----------|
| File fields werken niet | **Hoog** | Medium | Direct zichtbaar | Documentatie + alternatieven |
| Gelijktijdige edits overschrijven data | Medium | Medium | AC-C1 | Last-write-wins + awareness |
| Server niet bereikbaar | Laag | Hoog | Direct zichtbaar | Tailscale status check |

---

## Problem Statement

### Current Situation

Ontwikkelaars die PromptManager gebruiken op meerdere machines (laptop + desktop) hebben elk een lokale installatie met eigen MySQL database. Dit leidt tot:

- **Data fragmentatie:** Prompts gemaakt op de laptop zijn niet beschikbaar op de desktop
- **Sync overhead:** Handmatig exporteren/importeren van data tussen machines
- **Inconsistente state:** Verschillende versies van dezelfde prompt op verschillende machines
- **Setup overhead:** Docker, code, en database op elke machine configureren
- **Backup complexiteit:** Meerdere databases om te backuppen

### Desired Situation

Een centrale PromptManager webapplicatie op een bestaande Ubuntu server, toegankelijk via browser over Tailscale VPN. Clients hebben alleen een browser en Tailscale nodig.

```
┌─────────────────┐                        ┌─────────────────────────────┐
│     Laptop      │                        │       Ubuntu Server         │
│  ┌───────────┐  │     Tailscale VPN      │  ┌───────────────────────┐  │
│  │  Browser  │──┼───────────────────────►│  │  Docker               │  │
│  └───────────┘  │     100.x.x.x:8080     │  │  ┌─────────────────┐  │  │
└─────────────────┘                        │  │  │ PromptManager   │  │  │
                                           │  │  │ (Yii + Apache)  │  │  │
┌─────────────────┐                        │  │  └────────┬────────┘  │  │
│     Desktop     │                        │  │           │           │  │
│  ┌───────────┐  │     Tailscale VPN      │  │  ┌────────▼────────┐  │  │
│  │  Browser  │──┼───────────────────────►│  │  │     MySQL       │  │  │
│  └───────────┘  │     100.x.x.x:8080     │  │  └─────────────────┘  │  │
└─────────────────┘                        │  └───────────────────────┘  │
                                           └─────────────────────────────┘
```

### Measurable Goals

| Doel | Meetpunt | Target |
|------|----------|--------|
| Data synchronisatie | Tijd tussen save en zichtbaarheid op andere browser | < 1 seconde |
| Page load | Tijd voor volledig laden van pagina | < 2 seconden |
| Server setup | Tijd voor complete server configuratie | < 30 minuten |
| Client onboarding | Stappen voor nieuwe client | **2 stappen** |

**Definitie "Client onboarding":** Een nieuwe client vereist:
1. Tailscale installeren en verbinden met tailnet
2. Browser openen naar `http://ubuntu-server:8080`

Dit is **near zero-config** voor clients.

---

## Glossary

| Term | Definitie |
|------|-----------|
| **Tailscale** | Mesh VPN gebaseerd op WireGuard, zero-config networking |
| **Tailnet** | Privé netwerk van alle Tailscale-verbonden apparaten |
| **MagicDNS** | Tailscale feature voor hostname resolution binnen tailnet |
| **100.x.x.x** | Tailscale IP range (CGNAT range, specifiek 100.64.0.0/10) |
| **UFW** | Uncomplicated Firewall - standaard firewall tool op Ubuntu |
| **Centrale server** | Ubuntu server die PromptManager (Docker) + MySQL draait |
| **Client** | Laptop/desktop met browser die verbindt met centrale server via Tailscale |
| **Cut-over** | Het moment waarop gebruikers overschakelen naar centrale webapplicatie |
| **Migratie** | Het proces van data overzetten van lokale naar centrale database |
| **Rollback** | Terugkeren naar lokale installatie na een gefaalde cut-over |
| **Health check** | Geautomatiseerde controle of applicatie en services operationeel zijn |
| **SoR (System of Record)** | De autoritatieve bron voor data - na cut-over is dit de centrale server |
| **Last-write-wins** | Conflict resolutie waarbij de laatste write (op basis van timestamp) prevaleert |
| **Canonieke bron** | De machine waarvan data wordt gemigreerd als autoritatieve bron |

---

## Context

### Stakeholders

| Stakeholder | Rol | Belang |
|-------------|-----|--------|
| Ontwikkelaar | Primaire gebruiker | Data toegankelijk op alle machines |
| Ops (dezelfde persoon) | Server beheer | Minimale onderhoudslast |

### Constraints

| Constraint | Impact |
|------------|--------|
| Bestaande Ubuntu server | Geen nieuwe hardware nodig; beperkt door huidige capaciteit |
| 1-2 clients | Schaalbaarheid is geen prioriteit |
| Single user | Geen multi-user access control nodig |
| Development only | Geen uptime SLA; downtime is acceptabel |

### Server Capaciteit (Aannames)

| Resource | Vereist | Aanname |
|----------|---------|---------|
| RAM | ~1GB (MySQL + Docker + Apache/PHP) | Server heeft > 2GB beschikbaar |
| Disk | ~500MB (app + data + logs) | Server heeft > 10GB vrij |
| CPU | Minimaal (1-2 concurrent users) | Server is niet resource-constrained |
| Network | Tailscale overhead ~5% | Voldoende bandbreedte aanwezig |

---

## Data Ownership & Lineage

### System of Record (SoR)

| Fase | System of Record | Schrijfrechten |
|------|------------------|----------------|
| **Vóór migratie** | Lokale MySQL per machine | Elke machine schrijft naar eigen DB |
| **Na cut-over** | Centrale server (ubuntu-server) | Alle gebruikers via browser naar centrale app |

**Belangrijk:** Na cut-over is de centrale server de **enige** bron van waarheid. Lokale installaties worden niet meer gebruikt en kunnen worden verwijderd.

### Data Flow

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           VÓÓR MIGRATIE                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────┐              ┌─────────────────────┐           │
│  │       Laptop        │              │       Desktop       │           │
│  │  ┌───────────────┐  │              │  ┌───────────────┐  │           │
│  │  │    Docker     │  │              │  │    Docker     │  │           │
│  │  │ PromptManager │  │              │  │ PromptManager │  │           │
│  │  │    + MySQL    │  │   GEEN SYNC  │  │    + MySQL    │  │           │
│  │  │     (SoR)     │◄─┼──────────────┼──►│     (SoR)     │  │           │
│  │  └───────────────┘  │              │  └───────────────┘  │           │
│  └─────────────────────┘              └─────────────────────┘           │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

                                    │
                                    │ MIGRATIE
                                    ▼

┌─────────────────────────────────────────────────────────────────────────┐
│                           NA CUT-OVER                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────┐              ┌─────────────────────┐           │
│  │       Laptop        │              │       Desktop       │           │
│  │  ┌───────────────┐  │              │  ┌───────────────┐  │           │
│  │  │    Browser    │  │              │  │    Browser    │  │           │
│  │  └───────┬───────┘  │              │  └───────┬───────┘  │           │
│  │          │          │              │          │          │           │
│  └──────────┼──────────┘              └──────────┼──────────┘           │
│             │                                    │                      │
│             │      Tailscale VPN (HTTP)          │                      │
│             └────────────────┬───────────────────┘                      │
│                              │                                          │
│                              ▼                                          │
│                   ┌─────────────────────────────┐                       │
│                   │       ubuntu-server         │                       │
│                   │  ┌───────────────────────┐  │                       │
│                   │  │  Docker               │  │                       │
│                   │  │  ┌─────────────────┐  │  │                       │
│                   │  │  │ PromptManager   │  │  │                       │
│                   │  │  └────────┬────────┘  │  │                       │
│                   │  │           │           │  │                       │
│                   │  │  ┌────────▼────────┐  │  │                       │
│                   │  │  │  MySQL (SoR)    │  │  │                       │
│                   │  │  └─────────────────┘  │  │                       │
│                   │  └───────────────────────┘  │                       │
│                   └─────────────────────────────┘                       │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

### Eigenaarschap

| Data/Component | Eigenaar | Backup verantwoordelijk | Wijzigingsbevoegd |
|----------------|----------|------------------------|-------------------|
| Centrale applicatie + data | Gebruiker | Gebruiker (via cron) | Gebruiker |
| Docker/applicatie configuratie | Gebruiker (Ops rol) | Git repository | Gebruiker |
| Tailscale configuratie | Gebruiker | N/A (Tailscale cloud) | Gebruiker |
| DB credentials | Gebruiker | Password manager | Gebruiker |
| Backup files | Gebruiker | Geen (is zelf backup) | Gebruiker |

---

## Complete Data Scope

### Alle Database Tabellen

| Tabel | Type | Beschrijving | Migratie Vereist | Validatie Vereist |
|-------|------|--------------|------------------|-------------------|
| `project` | Bron | Hoofdcontainer voor prompts | **Ja** | **Ja** |
| `prompt_template` | Bron | Template definities | **Ja** | **Ja** |
| `prompt_instance` | Afgeleid | Gegenereerde instanties (kan worden herbouwd) | Ja | Ja |
| `context` | Bron | Context configuraties per project | **Ja** | **Ja** |
| `field` | Bron | Veld definities per project | **Ja** | **Ja** |
| `field_option` | Bron | Opties voor select-type velden | **Ja** | Ja |
| `scratch_pad` | Bron | Notities en scratch content | **Ja** | **Ja** |
| `template_field` | Koppel | Koppeling template ↔ field | **Ja** | Ja |
| `project_linked_project` | Koppel | Koppeling tussen projecten | **Ja** | Ja |
| `user` | Systeem | Gebruikersaccounts | Ja* | Nee |
| `user_preference` | Gebruiker | Gebruikersvoorkeuren | Ja | Nee |
| `migration` | Systeem | Yii migratie administratie | Nee (automatisch) | Nee |

*`user` tabel: alleen migreren indien zelfde user_id moet behouden blijven.

### Bron vs Afgeleide Data

| Categorie | Tabellen | Herberekening mogelijk | Migratie prioriteit |
|-----------|----------|------------------------|---------------------|
| **Brondata** | project, prompt_template, context, field, field_option, scratch_pad | Nee - handmatig gecreëerd | Kritiek |
| **Koppeldata** | template_field, project_linked_project | Nee - referentiële integriteit | Kritiek |
| **Afgeleide data** | prompt_instance | Ja - kan worden geregenereerd uit template + context | Hoog |
| **Systeemdata** | user, user_preference, migration | N.v.t. | Laag |

### Data Afhankelijkheden

```
project (root)
├── context (project_id → project.id)
├── field (project_id → project.id)
│   └── field_option (field_id → field.id)
├── prompt_template (project_id → project.id)
│   ├── template_field (template_id → prompt_template.id, field_id → field.id)
│   └── prompt_instance (prompt_template_id → prompt_template.id)
├── scratch_pad (project_id → project.id)
└── project_linked_project (project_id, linked_project_id → project.id)
```

### Delete Cascade Gedrag

> **Belangrijk:** Dit ontwerp maakt geen gebruik van soft-deletes. Bij verwijdering worden records permanent verwijderd met database-level cascade deletes.

| Parent Entiteit | Child Entiteiten | Cascade Gedrag |
|-----------------|------------------|----------------|
| `project` | context, field, prompt_template, scratch_pad, project_linked_project | **CASCADE DELETE** - Alle children worden permanent verwijderd |
| `field` | field_option | **CASCADE DELETE** - Alle opties worden verwijderd |
| `prompt_template` | template_field, prompt_instance | **CASCADE DELETE** - Alle koppelingen en instanties worden verwijderd |

**Rationale:**
- Single-user scenario: geen behoefte aan soft-delete voor audit trail
- Eenvoud boven complexiteit: geen `deleted_at` velden of filtered queries nodig
- Data integriteit: database foreign keys garanderen referentiële integriteit

**Toekomstige Overweging:**
Indien soft-deletes gewenst worden (bijv. voor undo-functionaliteit), moet cascade-gedrag expliciet worden gedefinieerd:
- Optie A: Parent soft-delete triggert cascade soft-delete naar alle children
- Optie B: Children blijven "actief" maar onbereikbaar via parent (orphaned maar recoverable)

### Validatie Vereisten per Tabel

| Tabel | Count Check | Steekproef | Referentiële Integriteit |
|-------|-------------|------------|--------------------------|
| project | Ja | Eerste + laatste | N.v.t. (root) |
| prompt_template | Ja | Eerste + laatste | project_id exists |
| prompt_instance | Ja | Steekproef | prompt_template_id exists |
| context | Ja | Steekproef | project_id exists |
| field | Ja | Steekproef | project_id exists |
| field_option | Ja | Nee | field_id exists |
| scratch_pad | Ja | Steekproef | project_id exists |
| template_field | Ja | Nee | template_id + field_id exist |
| project_linked_project | Ja | Nee | Beide project_ids exist |

---

## Merge & Conflict Strategie

### Scenario: Data op Meerdere Machines

Wanneer er data bestaat op meerdere lokale databases die niet identiek is:

```
┌─────────────────┐          ┌─────────────────┐
│   Laptop DB     │          │   Desktop DB    │
├─────────────────┤          ├─────────────────┤
│ Prompt A (v1)   │          │ Prompt A (v2)   │  ◄── Conflict!
│ Prompt B        │          │ Prompt C        │  ◄── Uniek per machine
│ Prompt D        │          │ Prompt D        │  ◄── Identiek
└─────────────────┘          └─────────────────┘
```

### Besluit: Canonieke Bron Selectie

**Strategie:** Selecteer één machine als canonieke bron vóór migratie.

| Stap | Actie |
|------|-------|
| 1 | Identificeer welke machine de meest complete/recente data heeft |
| 2 | Exporteer alleen data van deze machine naar centrale MySQL |
| 3 | Handmatig verifieer of kritieke data van andere machines nodig is |
| 4 | Indien nodig: handmatig samenvoegen vóór migratie |

**Aanbeveling:** Gebruik de machine met de meeste recente wijzigingen als canonieke bron.

### Conflict Resolutie Regels

| Situatie | Actie | Rationale |
|----------|-------|-----------|
| Identieke records op beide machines | Importeer één keer | Geen conflict |
| Record alleen op machine A | Importeer | Data behouden |
| Record alleen op machine B | Handmatig toevoegen indien gewenst | Na cut-over van A |
| Verschillende versies zelfde record | Kies meest recente of handmatig mergen | Vóór migratie beslissen |

### Pre-Migratie Checklist

- [ ] Inventariseer data op alle machines: `SELECT COUNT(*) FROM project; SELECT COUNT(*) FROM prompt_template;` etc.
- [ ] **Genereer diff-rapport** (zie script hieronder)
- [ ] Vergelijk belangrijke records tussen machines
- [ ] Besluit welke machine canonieke bron is
- [ ] Documenteer wat niet wordt gemigreerd (en waarom)
- [ ] Maak backup van alle lokale databases vóór migratie

### Diff-Rapport Script (FO-001 Resolutie)

Om te voorkomen dat waardevolle data op de 'secundaire' machine verloren gaat, genereer een diff-rapport vóór migratie.

**Stap 1: Exporteer namen/identifiers van beide machines**

```bash
# Op machine A (canonieke bron)
docker exec pma_mysql mysql -u root -p -N -e "
    SELECT 'project', name FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', CONCAT(project_id, ':', name) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'context', CONCAT(project_id, ':', name) FROM promptmanager.context
    UNION ALL
    SELECT 'field', CONCAT(project_id, ':', name) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', CONCAT(project_id, ':', name) FROM promptmanager.scratch_pad;
" | sort > /tmp/machine_a_inventory.txt

# Op machine B (secundaire)
docker exec pma_mysql mysql -u root -p -N -e "
    SELECT 'project', name FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', CONCAT(project_id, ':', name) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'context', CONCAT(project_id, ':', name) FROM promptmanager.context
    UNION ALL
    SELECT 'field', CONCAT(project_id, ':', name) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', CONCAT(project_id, ':', name) FROM promptmanager.scratch_pad;
" | sort > /tmp/machine_b_inventory.txt
```

**Stap 2: Vergelijk en identificeer delta**

```bash
# Kopieer machine_b_inventory.txt naar machine A, dan:

# Records ALLEEN op machine B (te reviewen voor handmatige migratie)
comm -13 /tmp/machine_a_inventory.txt /tmp/machine_b_inventory.txt > /tmp/only_on_b.txt

# Records ALLEEN op machine A
comm -23 /tmp/machine_a_inventory.txt /tmp/machine_b_inventory.txt > /tmp/only_on_a.txt

# Records op BEIDE machines (potentiële conflicten)
comm -12 /tmp/machine_a_inventory.txt /tmp/machine_b_inventory.txt > /tmp/on_both.txt

echo "=== Delta Rapport ==="
echo "Alleen op machine A: $(wc -l < /tmp/only_on_a.txt) records"
echo "Alleen op machine B: $(wc -l < /tmp/only_on_b.txt) records"
echo "Op beide machines:   $(wc -l < /tmp/on_both.txt) records"
echo ""
echo "=== Records alleen op machine B (review voor migratie): ==="
cat /tmp/only_on_b.txt
```

**Stap 3: Exporteer delta-data van machine B**

Als er waardevolle records op machine B staan:

```bash
# Exporteer specifieke tabellen volledig (voor handmatige merge)
docker exec pma_mysql mysqldump -u root -p \
    --single-transaction \
    --no-create-info \
    --complete-insert \
    promptmanager prompt_template > /tmp/machine_b_prompt_templates.sql
```

**Besluit na diff-rapport:**

| Situatie | Actie |
|----------|-------|
| Geen unieke records op B | Geen extra actie - migreer alleen A |
| Unieke records op B (waardevolle data) | Handmatig toevoegen aan A vóór migratie, of bewaar export voor latere import |
| Conflicterende records (zelfde naam, andere inhoud) | Vergelijk inhoud, kies meest recente of merge handmatig |

---

## Concurrency & Conflict Gedrag (Runtime)

### Gelijktijdige Edits - Gedrag

**Besluit:** Last-write-wins (standaard MySQL/Yii gedrag).

| Scenario | Gedrag | Voorbeeld |
|----------|--------|-----------|
| Gelijktijdig lezen | Beide clients zien zelfde data | OK |
| Gelijktijdig schrijven (verschillende records) | Beide writes slagen | OK |
| Gelijktijdig schrijven (zelfde record) | **Laatste write overschrijft** | Client A save om 10:00:01, Client B save om 10:00:02 → B wint |
| Delete terwijl ander edit | Delete wint (record weg) | Client A delete, Client B edit → record verdwenen |
| Edit terwijl ander delete | Edit faalt (record niet gevonden) | Error: "Record not found" |

### Edge Cases

| Edge Case | Gedrag | UX Impact |
|-----------|--------|-----------|
| Beide clients openen zelfde prompt | Beide zien snapshot van dat moment | Geen probleem |
| Client A saved, Client B (met oude data) saved | B overschrijft A's wijzigingen | **Data verlies mogelijk** |
| Client A delete, Client B probeert te openen | 404 / "Not found" | Verwarrend maar correct |
| Netwerk timeout tijdens save | Transactie faalt, data niet opgeslagen | Retry nodig |

### Mitigatie Strategieën

| Strategie | Implementatie Status | Beschrijving |
|-----------|---------------------|--------------|
| Optimistic locking | **Niet geïmplementeerd** | Zou version field vereisen |
| Real-time sync | **Niet geïmplementeerd** | Zou WebSocket vereisen |
| Awareness | **Aanbevolen** | Gebruiker weet dat 1-2 clients actief zijn; coördineer handmatig |
| Last-write-wins | **Actief** (default) | Accepteer risico; data is recoverable via backup |

### Aanbeveling voor Gebruiker

> Bij gelijktijdig werken op meerdere machines: coördineer welke machine actief edits doet. Gebruik andere machine alleen voor lezen totdat edit klaar is.

---

## Roles & Permissions

### Rollen

| Rol | Persoon | Verantwoordelijkheden |
|-----|---------|----------------------|
| Server Administrator | Gebruiker | Server beheer: Docker, MySQL, Tailscale, backups |
| Tailscale Administrator | Gebruiker | Tailscale installatie op clients, ACL configuratie (indien gebruikt) |
| Incident Handler | Gebruiker | Troubleshooting, recovery |

> **Note:** In deze single-user setup vervult dezelfde persoon alle rollen.

### Toegangsbeheer

| Resource | Wie mag lezen | Wie mag wijzigen |
|----------|---------------|------------------|
| PromptManager webapplicatie | Alle Tailscale clients via browser | Alle Tailscale clients via browser |
| MySQL database | Alleen Docker container op server | Alleen Docker container op server |
| Tailscale configuratie | N/A | Gebruiker via Tailscale admin console |
| Backup files | Gebruiker op server | Gebruiker op server |

### Onboarding Nieuwe Client

| Stap | Wie | Actie |
|------|-----|-------|
| 1 | Gebruiker | Tailscale installeren op nieuwe machine |
| 2 | Gebruiker | Tailscale verbinden met tailnet (auth via browser) |
| 3 | Gebruiker | Browser openen naar `http://ubuntu-server:8080` |

> **Note:** Geen Docker, geen .env, geen credentials nodig op de client. De applicatie draait volledig op de server.

### Offboarding / Toegang Intrekken

| Scenario | Procedure | Verantwoordelijke |
|----------|-----------|-------------------|
| Device verloren/gestolen | Tailscale device verwijderen via admin console | Gebruiker |
| Device afgestoten (verkoop) | 1. Tailscale uitloggen op device<br>2. Tailscale device verwijderen via admin console | Gebruiker |

### Integratiecontract: Client ↔ Centrale Server

| Aspect | Specificatie |
|--------|--------------|
| **Authenticatie** | Tailscale device authenticatie (geen aparte app login) |
| **Autorisatie** | Alle Tailscale clients hebben volledige toegang tot webapp |
| **Netwerk** | Tailscale VPN vereist; geen directe internet toegang |
| **Protocol** | HTTP naar port 8080 op server |
| **Minimum client identificatie** | Tailscale device name + IP (zichtbaar in `tailscale status`) |
| **Logging** | Apache access logs op server; Tailscale connection logs |

### Toegang Audit

```bash
# Bekijk welke Tailscale devices verbonden zijn
tailscale status

# Bekijk Apache access logs (op server)
docker logs pma_yii | tail -100

# Bekijk actieve verbindingen naar poort 8080
sudo ss -tlnp | grep 8080
```

---

## Architectuur

### Network Topology

```
┌──────────────────────────────────────────────────────────────────────┐
│                          Tailnet (privé VPN)                         │
│                                                                      │
│  ┌───────────────────────────────┐        ┌───────────────────────┐  │
│  │       ubuntu-server           │        │       laptop          │  │
│  │       100.64.0.1              │        │     100.64.0.2        │  │
│  │                               │        │                       │  │
│  │  ┌─────────────────────────┐  │        │  ┌─────────────────┐  │  │
│  │  │         Docker          │  │  HTTP  │  │     Browser     │  │  │
│  │  │  ┌───────────────────┐  │◄─┼────────┼──│                 │  │  │
│  │  │  │  PromptManager    │  │  │  :8080 │  └─────────────────┘  │  │
│  │  │  │  (Apache + PHP)   │  │  │        └───────────────────────┘  │
│  │  │  └─────────┬─────────┘  │  │                                   │
│  │  │            │            │  │        ┌───────────────────────┐  │
│  │  │  ┌─────────▼─────────┐  │  │        │       desktop         │  │
│  │  │  │      MySQL        │  │  │        │     100.64.0.3        │  │
│  │  │  └───────────────────┘  │  │        │                       │  │
│  │  └─────────────────────────┘  │  HTTP  │  ┌─────────────────┐  │  │
│  │                               │◄─┼────────│     Browser     │  │  │
│  └───────────────────────────────┘  │  :8080 │                 │  │  │
│                                     │        └─────────────────┘  │  │
│                                     │        └───────────────────────┘  │
└──────────────────────────────────────────────────────────────────────┘
```

### Componenten

| Component | Locatie | Functie | Eigenaar |
|-----------|---------|---------|----------|
| Tailscale daemon | Alle machines | VPN tunnel en routing | Server Admin |
| Docker | Ubuntu server | Container runtime voor PromptManager + MySQL | Server Admin |
| PromptManager (Apache + PHP) | Ubuntu server (Docker) | Webapplicatie | Server Admin |
| MySQL | Ubuntu server (Docker) | Database (SoR) | Server Admin |
| UFW Firewall | Ubuntu server | Beperkt toegang tot Tailscale | Server Admin |
| Backup cron | Ubuntu server | Dagelijkse database backup | Server Admin |
| Health check timer | Ubuntu server | Monitoring Docker/Tailscale | Server Admin |
| Browser | Laptop/Desktop | Client interface | Gebruiker |

---

## Gedrag bij Uitval

### Scenario's en Verwacht Gedrag

| Scenario | Applicatie gedrag | Gebruikersimpact | Recovery |
|----------|-------------------|------------------|----------|
| Docker containers offline | Pagina laadt niet | App niet bruikbaar | Server: `docker compose up -d` |
| Tailscale offline (client) | Connection timeout in browser | App niet bruikbaar | Client: `tailscale up` |
| Tailscale offline (server) | Connection timeout | Alle clients niet bruikbaar | Server: `tailscale up` |
| Tijdelijke netwerk glitch | Mogelijk 1-2 failed requests | Retry lost het op | Automatisch |
| Server reboot | Tijdelijk niet beschikbaar | Wacht op server boot | Automatisch (Docker auto-start) |

### UX Impact per Uitvalscenario

| Scenario | Wat ziet gebruiker | Gewenst gedrag | Data integriteit |
|----------|-------------------|----------------|------------------|
| Docker containers offline | Browser: "Connection refused" of timeout | Refresh pagina na recovery | Geen data verlies - write faalde |
| Tailscale timeout | Browser: "Connection timed out" | Wacht, `tailscale up`, refresh | Geen data verlies |
| Mid-request disconnect | Mogelijk partial save afhankelijk van timing | Controleer data na recovery, eventueel opnieuw invoeren | **Mogelijk partial write** |
| Server reboot | Connection errors tijdens reboot (~1-2 min) | Wacht, refresh | Geen data verlies |

### Gebruikersactie bij Uitval

| Situatie | Eerste actie | Vervolgactie | Escalatie |
|----------|--------------|--------------|-----------|
| Pagina laadt niet | Wacht 10 seconden, refresh | Check `tailscale status` | SSH naar server, check Docker |
| Timeout bij save | Noteer wat je wilde opslaan | Check of data deels is opgeslagen | Herstel handmatig |
| Langdurige uitval (>5 min) | Check server bereikbaarheid | SSH naar server, check logs | Restart Docker containers |

### Geen Graceful Degradation

**Belangrijk:** Er is geen fallback naar lokale installatie. Bij uitval van de centrale server of Tailscale is de applicatie niet bruikbaar totdat de verbinding is hersteld.

**Rationale:**
- Lokale fallback zou data-inconsistentie introduceren
- Development scenario: downtime is acceptabel
- Eenvoud boven complexiteit

### Recovery Procedures

**Docker containers niet bereikbaar:**
```bash
# Op server via SSH
docker compose ps                    # Check status
docker compose up -d                 # Start containers
docker logs pma_yii --tail 50        # Check logs
docker logs pma_mysql --tail 50         # Check MySQL logs
```

**Tailscale niet verbonden (client):**
```bash
tailscale status
tailscale up
tailscale ping ubuntu-server
```

**Tailscale niet verbonden (server):**
```bash
# Op server
sudo tailscale status
sudo tailscale up
# Controleer of andere devices kunnen verbinden
```

---

## Besluitpunten

### Impact/Kans Classificatie

| Classificatie | Impact | Kans |
|---------------|--------|------|
| **Hoog** | Dataverlies, langdurige downtime (>4 uur), security breach | >50% kans op optreden |
| **Medium** | Tijdelijke downtime (1-4 uur), handmatig herstelwerk nodig | 10-50% kans |
| **Laag** | Korte onderbreking (<1 uur), geen dataverlies | <10% kans |

### Besluitpuntenregister

#### Open Besluitpunten

| ID | Besluitpunt | Opties | Aanbeveling | Eigenaar | Deadline | Consequenties bij uitstel |
|----|-------------|--------|-------------|----------|----------|---------------------------|
| D1 | Server firewall configuratie | A: Alleen Tailscale / B: Ook lokaal netwerk | **Optie A** | Gebruiker | Dag 1 server setup | Security risico bij verkeerde keuze |
| D2 | Tailscale ACLs | Wel configureren / Default laten | **Default** | Gebruiker | Dag 1 server setup | Beheerlast zonder proportionele meerwaarde |
| D3 | Canonieke bron selectie | Laptop / Desktop / Andere machine | **Machine met meest recente data** | Gebruiker | Pre-migratie (vóór data export) | Verkeerde data gemigreerd; dataverlies van niet-gekozen machines |

#### Besluit Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    BESLUITPUNTEN TIJDLIJN                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Dag -1 (Pre-migratie)     Dag 1 (Setup)        Dag 2+ (Cut-over)   │
│  ────────────────────      ─────────────        ────────────────    │
│                                                                     │
│  ┌─────────────────┐       ┌─────────────┐      ┌───────────────┐   │
│  │ D3: Canonieke   │       │ D1: Firewall│      │ Implementatie │   │
│  │     bron keuze  │──────►│ D2: ACLs    │──────►│ volgens       │   │
│  └─────────────────┘       └─────────────┘      │ besluiten     │   │
│                                                 └───────────────┘   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

#### Genomen Besluiten

| ID | Besluit | Rationale | Datum | Eigenaar | Status |
|----|---------|-----------|-------|----------|--------|
| B1 | Centrale MySQL als enige SoR na cut-over | Voorkomt data-inconsistentie | 2026-01-18 | Gebruiker | **Definitief** |
| B2 | Geen fallback naar lokale DB | Eenvoud, dev-only scenario | 2026-01-18 | Gebruiker | **Definitief** |
| B3 | Cron-based backup (geen real-time replicatie) | Voldoende voor 1 user | 2026-01-18 | Gebruiker | **Definitief** |
| B4 | Last-write-wins bij concurrency | Standaard MySQL gedrag; awareness is mitigatie | 2026-01-18 | Gebruiker | **Definitief** |

#### Besluit Template (voor open punten)

Bij het nemen van een besluit, documenteer:

```
Besluit: [D1/D2/D3/D4/D5]
Gekozen optie: [A/B/...]
Rationale: [Waarom deze keuze]
Datum: [YYYY-MM-DD]
Consequenties: [Wat moet er nu gebeuren]
```

---

## Risico Register

### Risico Matrix

```
            │ Laag Impact │ Medium Impact │ Hoog Impact
────────────┼─────────────┼───────────────┼─────────────
Hoog Kans   │             │               │
Medium Kans │             │ R3            │ R2, R6
Laag Kans   │             │ R4            │ R1, R5
```

### Risico's

| ID | Risico | Impact | Kans | Risico Score | Mitigatie | Eigenaar | Status |
|----|--------|--------|------|--------------|-----------|----------|--------|
| R1 | Server niet beschikbaar (hardware failure) | Hoog | Laag | Medium | Dagelijkse backup, restore procedure gedocumenteerd | Gebruiker | Gemitigeerd |
| R2 | Data verlies bij migratie | Hoog | Medium | Hoog | Pre-migratie backup van ALLE lokale DBs, checksum verificatie | Gebruiker | Gemitigeerd |
| R3 | Data conflict bij merge van meerdere machines | Medium | Medium | Medium | Canonieke bron selectie, pre-migratie inventarisatie | Gebruiker | Gemitigeerd |
| R4 | Tailscale service outage | Medium | Laag | Laag | Wachten op herstel; geen alternatief mogelijk | Gebruiker | **Geaccepteerd** |
| R5 | MySQL credentials gelekt | Hoog | Laag | Medium | Tailscale-only binding, UFW firewall, geen publieke exposure | Gebruiker | Gemitigeerd |
| R6 | Rollback met data verlies (data na cut-over) | Hoog | Medium | Hoog | Pre-rollback snapshot, reconciliatie procedure gedocumenteerd | Gebruiker | Gemitigeerd |

### Geaccepteerde Risico's

| Risico | Classificatie | Reden voor Acceptatie | Goedgekeurd door |
|--------|---------------|----------------------|------------------|
| R4 (Tailscale outage) | Laag | Externe dependency, geen alternatief zonder grote complexiteit; development-only scenario | Gebruiker |
| Single point of failure | Medium | Development-only, uptime niet kritiek; dagelijkse backup biedt hersteloptie | Gebruiker |

---

## Aannames Register

| ID | Aanname | Onderbouwing | Risico bij Onjuistheid | Mitigatie | Validatie |
|----|---------|--------------|------------------------|-----------|-----------|
| A1 | Single user (1 persoon) | Context sectie | ACL/permissions ontoereikend | Herzien bij meerdere gebruikers | Pre-implementatie |
| A2 | 1-2 clients | Context sectie | Performance/concurrency issues | Herzien bij >2 clients | Pre-implementatie |
| A3 | Server capaciteit voldoende | Capaciteit tabel | Latency/storage issues | Capaciteitscheck vóór cut-over | AC-P tests |
| A4 | Geen staging/production | Scope sectie | Minder regressiesignaal | Extra regressietesten vooraf | N.v.t. |
| A5 | Downtime acceptabel | Constraints | Werkonderbreking | Duidelijke rollbackcriteria | Pre-implementatie |
| A6 | Tailscale performance <50ms | Measurable goals | Onacceptabele UX | `tailscale ping` test vooraf | Pre-cut-over |
| A7 | Centrale server altijd aan | Architectuur | Geen development mogelijk | Monitoring + snelle recovery | Health checks |

### Scope Creep Triggers

Als een van de volgende optreedt, moet het ontwerp worden herzien:

| Trigger | Impact | Actie |
|---------|--------|-------|
| >2 clients | Concurrency complexiteit | Overweeg locking strategie |
| >1 gebruiker | ACL nodig | Implementeer per-user credentials |
| CI/CD integratie gewenst | Gedeelde test-DB conflicten | Aparte DB per pipeline |
| Uptime kritiek wordt | Single point of failure | Overweeg replicatie |

---

## Tailscale Setup

### Server Installatie (Ubuntu)

```bash
# 1. Tailscale repository toevoegen
curl -fsSL https://tailscale.com/install.sh | sh

# 2. Tailscale starten en authenticeren
sudo tailscale up

# 3. Tailscale IP opvragen
tailscale ip -4
# Output: 100.64.0.1 (voorbeeld)

# 4. MagicDNS hostname bekijken
tailscale status
# Output: ubuntu-server   100.64.0.1   linux   -
```

### Client Installatie (Laptop/Desktop)

Clients hebben alleen Tailscale en een browser nodig. Geen Docker, geen code, geen configuratie.

```bash
# Linux
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up

# macOS
brew install tailscale
# Of download van https://tailscale.com/download
tailscale up

# Windows
# Download installer van https://tailscale.com/download
# Installeer en log in via browser
```

**Verificatie:**

```bash
# Controleer Tailscale verbinding
tailscale status

# Test bereikbaarheid server
tailscale ping ubuntu-server
```

**Gebruik:**

Open browser naar: `http://ubuntu-server:8080`

> **Note:** Indien MagicDNS niet werkt, gebruik het Tailscale IP van de server: `http://100.64.x.x:8080`

### MagicDNS

Met MagicDNS kunnen machines elkaar bereiken via hostname:

```bash
# In browser:
http://ubuntu-server:8080

# Of met IP-adres:
http://100.64.0.1:8080
```

MagicDNS is standaard ingeschakeld in Tailscale. Controleer via Tailscale admin console indien hostnames niet resolven.

---

## Docker Setup (Server)

De centrale server draait PromptManager en MySQL in Docker containers. Dit is dezelfde Docker setup die eerder lokaal werd gebruikt.

### Vereisten

```bash
# Docker installeren (indien nog niet aanwezig)
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Uitloggen en opnieuw inloggen voor group changes
```

### PromptManager Installatie

```bash
# Clone repository (of kopieer van bestaande installatie)
git clone <repository-url> /opt/promptmanager
cd /opt/promptmanager

# Configureer .env
cp .env.example .env
# Edit .env met gewenste database credentials

# Start containers
docker compose up -d

# Verifieer
docker compose ps
docker logs pma_yii --tail 20
```

### Database Schema Initialiseren

```bash
# Run migraties
docker exec pma_yii ./yii migrate --interactive=0

# Run test database migraties
docker exec pma_yii ./yii_test migrate --interactive=0
```

### Poort Configuratie

De PromptManager webapplicatie luistert standaard op poort 8080. Dit is geconfigureerd in `docker-compose.yml`:

```yaml
services:
  yii:
    ports:
      - "8080:80"  # Exposed op alle interfaces, beveiligd door firewall
```

> **Note:** MySQL draait alleen binnen Docker network en is niet extern toegankelijk. Dit vereist dat de `DB_PORT` mapping uit docker-compose.yml wordt verwijderd (zie TO sectie 4.4.1). Alleen de webapplicatie (poort 8080) is bereikbaar via Tailscale.

---

## Security Model

### Firewall Configuratie (UFW + Docker Hardening)

> **Belangrijk:** UFW alleen is niet voldoende voor Docker security. Docker manipuleert iptables direct en kan UFW regels omzeilen. Zie het TO document (sectie 4.4.1) voor Docker firewall hardening opties:
> - **Optie A (aanbevolen):** Bind NGINX aan Tailscale IP in docker-compose.yml
> - **Optie B:** Configureer DOCKER-USER iptables chain

```bash
# Standaard policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH (voor server beheer)
sudo ufw allow ssh

# HTTP ALLEEN via Tailscale interface
sudo ufw allow in on tailscale0 to any port 8080

# UFW activeren
sudo ufw enable
sudo ufw status verbose

# BELANGRIJK: Voer ook Docker hardening uit (zie TO sectie 4.4.1)
```

### Firewall Rules Overzicht

| Rule | Interface/Source | Port | Actie | Reden |
|------|------------------|------|-------|-------|
| SSH | Any | 22 | Allow | Serverbeheer |
| HTTP | tailscale0 | 8080 | Allow | PromptManager webapplicatie |
| HTTP | eth0/public | 8080 | Deny (default) | Geen publieke toegang |

### Security Layers

```
┌────────────────────────────────────────────────────────────────┐
│                        Internet                                 │
└────────────────────────────────────────────────────────────────┘
                               │
                               │ ✗ Port 8080 blocked (publiek)
                               ▼
┌────────────────────────────────────────────────────────────────┐
│                     UFW Firewall                                │
│     allow: tailscale0:8080    deny: eth0:8080                  │
└────────────────────────────────────────────────────────────────┘
                               │
                               │ ✓ Tailscale interface only
                               ▼
┌────────────────────────────────────────────────────────────────┐
│                   Tailscale (WireGuard)                         │
│     End-to-end encrypted    Authenticated devices only         │
└────────────────────────────────────────────────────────────────┘
                               │
                               │ ✓ Encrypted tunnel
                               ▼
┌────────────────────────────────────────────────────────────────┐
│                   PromptManager (Docker)                        │
│     HTTP op poort 8080    MySQL alleen intern (Docker network) │
└────────────────────────────────────────────────────────────────┘
```

### Tailscale ACLs

**Besluit D2:** Default ACLs gebruiken (geen custom configuratie).

Rationale: Voor 1-2 machines onder dezelfde Tailscale account is de default ACL (alle devices kunnen met elkaar communiceren) voldoende. Custom ACLs voegen onnodige complexiteit toe.

---

## PromptManager Configuratie (Server)

De PromptManager draait volledig op de server. Er is geen client-side configuratie nodig.

### Server Docker Compose

Gebruik de standaard `docker-compose.yml` uit het repository. De belangrijkste services zijn:

| Container | Service | Functie |
|-----------|---------|---------|
| `pma_yii` | yii | PHP/Yii applicatie (PHP-FPM) |
| `pma_nginx` | nginx | Webserver (reverse proxy) |
| `pma_mysql` | mysql | MySQL 8.0 database |

**Belangrijke environment variabelen (`.env`):**

| Variabele | Doel |
|-----------|------|
| `NGINX_PORT` | HTTP poort (default: 8502, centrale server: 8080) |
| `DB_ROOT_PASSWORD` | MySQL root password |
| `DB_HOST` | Database hostname (`pma_mysql`) |
| `DB_DATABASE` | Database naam |
| `DB_USER` | Database gebruiker |
| `DB_PASSWORD` | Database wachtwoord |

> **Note:** Voor de centrale server wordt aanbevolen `NGINX_PORT=8080` te gebruiken en Docker firewall hardening toe te passen (zie TO sectie 4.4.1).

### .env Configuratie (Server)

```bash
# Basis configuratie (zie .env.example voor alle opties)
USER_ID=1000
USER_NAME=appuser
TIMEZONE=Europe/Amsterdam

# Database credentials
DB_ROOT_PASSWORD=<genereer-sterk-password>
DB_HOST=pma_mysql
DB_DATABASE=promptmanager
DB_USER=promptmanager
DB_PASSWORD=<genereer-sterk-password>

# Centrale server poort
NGINX_PORT=8080

# Optioneel: Tailscale IP voor Docker hardening
TAILSCALE_IP=100.x.x.x
```

### Verbinding Testen

```bash
# Check containers draaien
docker compose ps

# Check applicatie logs
docker logs pma_yii --tail 50

# Test database connectie
docker exec pma_yii ./yii migrate/status

# Test via browser
# Open http://ubuntu-server:8080 vanuit client browser
```

---

## Migratie

### Pre-Migratie Snapshot

**Kritiek:** Maak een snapshot van alle betrokken data vóór cut-over.

```bash
# Op ELKE machine met lokale data
DATE=$(date +%Y%m%d_%H%M%S)

# Export lokale database
docker exec pma_mysql mysqldump -u root -p \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager > "pre_migration_${HOSTNAME}_${DATE}.sql"

# Bewaar op veilige locatie (niet in /tmp)
mv "pre_migration_${HOSTNAME}_${DATE}.sql" ~/backups/
```

### Migratie Procedure

#### Stap 1: Selecteer Canonieke Bron

```bash
# Op elke machine: inventariseer data per entiteit
docker exec pma_mysql mysql -u root -p -e "
    SELECT 'project' as entity, COUNT(*) as count FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'prompt_instance', COUNT(*) FROM promptmanager.prompt_instance
    UNION ALL
    SELECT 'context', COUNT(*) FROM promptmanager.context
    UNION ALL
    SELECT 'field', COUNT(*) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', COUNT(*) FROM promptmanager.scratch_pad;
"
```

Kies de machine met de meest complete/recente data.

#### Stap 2: Export van Canonieke Bron

```bash
# Op de gekozen canonieke machine
docker exec pma_mysql mysqldump -u root -p \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager > promptmanager_canonical.sql

# Genereer checksum
sha256sum promptmanager_canonical.sql > promptmanager_canonical.sql.sha256
```

#### Stap 3: Transfer naar Server

```bash
# Via Tailscale
scp promptmanager_canonical.sql promptmanager_canonical.sql.sha256 ubuntu-server:/tmp/
```

#### Stap 4: Import op Centrale Server

> **KRITIEK - Lege Database Vereist:**
> De centrale server MOET starten met een **lege database** (alleen schema, geen data). Dit voorkomt duplicatie van records.
>
> **Wat NIET te doen:**
> - Kopieer GEEN database bestanden handmatig van een lokale machine naar de server
> - Importeer NIET meerdere database dumps naar dezelfde centrale database
>
> **Waarom:** Elke lokale machine heeft eigen auto-increment IDs. Bij import van meerdere bronnen ontstaan ID-conflicten of duplicaten met verschillende IDs voor dezelfde logische content.

```bash
# Op ubuntu-server
# Verifieer checksum
sha256sum -c /tmp/promptmanager_canonical.sql.sha256

# BELANGRIJK: Zorg dat de database leeg is vóór import
# Als er al data staat, maak eerst een backup en reset:
# docker exec pma_mysql mysql -u root -p -e "DROP DATABASE IF EXISTS promptmanager; CREATE DATABASE promptmanager;"

# Import
mysql -u root -p promptmanager < /tmp/promptmanager_canonical.sql
```

#### Stap 5: Validatie

Zie sectie "Post-Migratie Validatie" voor complete checklist.

#### Stap 6: Cut-over - Clients Gebruiken Centrale Server

Na succesvolle migratie kunnen clients direct de centrale webapplicatie gebruiken:

**Op elke client machine:**

1. **Tailscale verbinden** (indien nog niet gedaan):
   ```bash
   tailscale status    # Check of verbonden
   tailscale up        # Verbind indien nodig
   ```

2. **Browser openen naar centrale server:**
   ```
   http://ubuntu-server:8080
   ```

3. **Verificatie:**
   - Login indien nodig
   - Check of bestaande data zichtbaar is
   - Maak een test-prompt om write access te verifiëren

**Opruimen lokale installatie (optioneel, na validatie):**

```bash
# Stop lokale Docker containers
docker compose down

# Bewaar lokale backup voor noodgevallen (7 dagen)
# Verwijder later volgens decommissioning procedure
```

> **Note:** De lokale PromptManager installatie is niet meer nodig. Clients gebruiken alleen de browser.

### Post-Migratie Validatie

#### Te Valideren Entiteiten

De volgende PromptManager domein-entiteiten moeten worden gevalideerd:

| Entiteit | Tabel | Primaire Key | Beschrijving |
|----------|-------|--------------|--------------|
| Project | `project` | `id` | Hoofdcontainer voor prompts |
| PromptTemplate | `prompt_template` | `id` | Template definities |
| PromptInstance | `prompt_instance` | `id` | Gegenereerde prompt instanties |
| Context | `context` | `id` | Context configuraties |
| Field | `field` | `id` | Veld definities |
| ScratchPad | `scratch_pad` | `id` | Notities en scratch content |

#### Validatie Checks

| Check | Commando | Verwacht |
|-------|----------|----------|
| Record count project | `SELECT COUNT(*) FROM project` | Gelijk aan pre-migratie |
| Record count prompt_template | `SELECT COUNT(*) FROM prompt_template` | Gelijk aan pre-migratie |
| Record count prompt_instance | `SELECT COUNT(*) FROM prompt_instance` | Gelijk aan pre-migratie |
| Record count context | `SELECT COUNT(*) FROM context` | Gelijk aan pre-migratie |
| Record count field | `SELECT COUNT(*) FROM field` | Gelijk aan pre-migratie |
| Record count scratch_pad | `SELECT COUNT(*) FROM scratch_pad` | Gelijk aan pre-migratie |
| Steekproef: eerste project | `SELECT id, name FROM project LIMIT 1` | Data intact |
| Steekproef: laatste template | `SELECT id, name FROM prompt_template ORDER BY id DESC LIMIT 1` | Data intact |
| Applicatie start | `docker compose up -d && docker logs pma_yii` | Geen DB errors |
| CRUD test | Maak nieuwe prompt_template, edit, delete | Alle operaties werken |

#### Geautomatiseerde Validatie Script

```bash
# Schema validatie
docker exec pma_yii ./yii migrate/status
# Expected: "No new migrations found"

# Record count validatie (vergelijk pre vs post)
mysql -h ubuntu-server -u promptmanager -p -e "
    SELECT 'project' as entity, COUNT(*) as count FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template
    UNION ALL
    SELECT 'prompt_instance', COUNT(*) FROM promptmanager.prompt_instance
    UNION ALL
    SELECT 'context', COUNT(*) FROM promptmanager.context
    UNION ALL
    SELECT 'field', COUNT(*) FROM promptmanager.field
    UNION ALL
    SELECT 'scratch_pad', COUNT(*) FROM promptmanager.scratch_pad;
"

# Steekproef: eerste en laatste record per entiteit
mysql -h ubuntu-server -u promptmanager -p -e "
    SELECT 'First project' as check_type, id, name FROM promptmanager.project ORDER BY id ASC LIMIT 1;
    SELECT 'Last project' as check_type, id, name FROM promptmanager.project ORDER BY id DESC LIMIT 1;
    SELECT 'First template' as check_type, id, name FROM promptmanager.prompt_template ORDER BY id ASC LIMIT 1;
    SELECT 'Last template' as check_type, id, name FROM promptmanager.prompt_template ORDER BY id DESC LIMIT 1;
"
```

---

## Cut-over Besluitvorming

### Cut-over Besluitstappen

| Stap | Criterium | Eigenaar | Go/No-Go |
|------|-----------|----------|----------|
| 1. Pre-migratie backup compleet | Alle lokale DBs gebackupt met checksum | Gebruiker | Go indien alle checksums valid |
| 2. Canonieke bron gekozen | Data inventarisatie gedocumenteerd | Gebruiker | Go indien besluit vastgelegd |
| 3. Server operationeel | Tailscale + Docker + UFW geconfigureerd | Gebruiker | Go indien AC-1 t/m AC-4 slagen |
| 4. Test import succesvol | Import + validatie op test-DB | Gebruiker | Go indien counts + steekproef OK |
| 5. **Cut-over besluit** | Alle bovenstaande Go | **Gebruiker** | **Proceed of Abort** |
| 6. Productie import | Import naar centrale DB | Gebruiker | Proceed indien stap 5 Go |
| 7. Client cut-over | Clients openen browser naar server | Gebruiker | Rollback indien AC's falen |

### Rollback Triggers

| Trigger | Drempel | Actie |
|---------|---------|-------|
| Import faalt | 1x retry, nog steeds fail | Rollback |
| Validatie count mismatch | >1% afwijking | Investigate, mogelijk rollback |
| Applicatie start faalt | Na 3x retry | Rollback |
| Performance onacceptabel | Latency >500ms consistent | Investigate, mogelijk rollback |
| Kritieke data ontbreekt | Steekproef toont missende records | Rollback |

---

## Rollback Procedure

### Wanneer Rollback?

- Centrale MySQL niet bereikbaar en herstel duurt te lang
- Migratie data corrupt of incompleet
- Performance issues die niet oplosbaar zijn
- Validatie toont data verlies of corruptie

### Rollback met Data Behoud

**Kritiek:** Data aangemaakt na cut-over moet worden veiliggesteld.

```bash
# Stap 1: Maak snapshot van centrale MySQL (indien bereikbaar)
ssh ubuntu-server "mysqldump -u root -p promptmanager > /tmp/rollback_snapshot_$(date +%Y%m%d_%H%M%S).sql"
scp ubuntu-server:/tmp/rollback_snapshot_*.sql ~/backups/

# Stap 2: Als centrale MySQL NIET bereikbaar is
# Data na cut-over is verloren → accepteer dit of wacht op herstel

# Stap 3: Herstel lokale configuratie
cd /path/to/promptmanager
git checkout docker-compose.yml  # Of handmatig herstellen
git checkout .env                 # Of handmatig herstellen

# Stap 4: Start lokale database
docker compose up -d db

# Stap 5: Importeer pre-migratie backup
docker exec -i pma_mysql mysql -u root -p promptmanager < ~/backups/pre_migration_backup.sql

# Stap 6: Indien snapshot beschikbaar: merge nieuwe data
# Dit is handmatig werk - identificeer nieuwe records en voeg toe
```

### Data Reconciliatie na Rollback

Als er data is aangemaakt na cut-over en een rollback nodig is:

| Scenario | Data status | Actie |
|----------|-------------|-------|
| Centrale MySQL bereikbaar | Snapshot maken | Handmatig mergen met lokale backup |
| Centrale MySQL niet bereikbaar | Data verloren | Accepteren of wachten op herstel |

**Reconciliatie stappen (indien snapshot beschikbaar):**

```sql
-- Identificeer nieuwe records per entiteit
-- In centrale snapshot:
SELECT 'prompt_template' as entity, id, name, created_at
FROM prompt_template WHERE created_at > '2026-01-18 12:00:00';

SELECT 'prompt_instance' as entity, id, created_at
FROM prompt_instance WHERE created_at > '2026-01-18 12:00:00';

SELECT 'project' as entity, id, name, created_at
FROM project WHERE created_at > '2026-01-18 12:00:00';

-- Handmatig toevoegen aan lokale DB na rollback indien nodig
```

### Rollback Acceptatiecriteria

Na rollback moet het volgende aantoonbaar intact zijn:

| ID | Criterium | Verificatie | Must Preserve |
|----|-----------|-------------|---------------|
| RB-1 | Pre-migratie data intact | Record counts matchen pre-migratie backup | **Ja** |
| RB-2 | Applicatie start succesvol | `docker compose up` zonder errors | **Ja** |
| RB-3 | CRUD operaties werken | Create + Read + Update + Delete test | **Ja** |
| RB-4 | Schema consistent | `./yii migrate/status` geen errors | **Ja** |
| RB-5 | Post-cut-over data gereconcilieerd | Nieuwe records handmatig toegevoegd (indien snapshot beschikbaar) | Nee (best effort) |

### Rollback Validatie Script

```bash
#!/bin/bash
# rollback-validation.sh

echo "=== Rollback Validation ==="

# RB-1: Count check (vergelijk met pre-migratie)
echo "Checking record counts..."
docker exec pma_mysql mysql -u root -p -e "
    SELECT 'project', COUNT(*) FROM promptmanager.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager.prompt_template;
"

# RB-2: App start check
echo "Checking app container..."
docker compose ps | grep pma_yii

# RB-3: CRUD test (manual)
echo "CRUD test: open http://localhost:8080 and test manually"

# RB-4: Schema check
echo "Checking schema..."
docker exec pma_yii ./yii migrate/status

echo "=== Validation Complete ==="
```

---

## Decommissioning Lokale Databases

### Retentie Beleid

Na succesvolle cut-over naar centrale MySQL:

| Item | Bewaartermijn | Locatie | Actie na termijn |
|------|---------------|---------|------------------|
| Pre-migratie backup (canonieke bron) | **Permanent** | `~/backups/pre_migration_*.sql` | Archiveren |
| Pre-migratie backups (andere machines) | 30 dagen | `~/backups/pre_migration_*.sql` | Verwijderen na verificatie |
| Lokale MySQL container | 7 dagen na cut-over | Docker | Stop en verwijder |
| Lokale MySQL data volume | 14 dagen na cut-over | Docker volume | Verwijderen na verificatie |

### Decommissioning Procedure

#### Fase 1: Validatie (Dag 1-7 na cut-over)

```bash
# Controleer dat centrale DB volledig werkt
docker exec pma_yii ./yii migrate/status
# Controleer dat alle data aanwezig is (vergelijk met pre-migratie)
```

Geen lokale data verwijderen in deze fase.

#### Fase 2: Lokale Container Stoppen (Dag 7)

```bash
# Stop lokale database container (indien nog draait)
docker stop pma_mysql

# Verwijder container (data volume blijft behouden)
docker rm pma_mysql
```

#### Fase 3: Data Volume Archiveren (Dag 14)

```bash
# Maak archief van lokale data volume (voor noodgevallen)
docker run --rm -v pma_mysql_data:/data -v $(pwd):/backup alpine \
    tar czf /backup/local_db_archive_$(date +%Y%m%d).tar.gz /data

# Bewaar archief op veilige locatie
mv local_db_archive_*.tar.gz ~/backups/archive/
```

#### Fase 4: Definitieve Opruiming (Dag 30)

```bash
# Verwijder lokale data volume
docker volume rm pma_mysql_data

# Verwijder niet-canonieke pre-migratie backups
# BEHOUD: pre_migration_<canonieke_machine>_*.sql (permanent)
rm ~/backups/pre_migration_<andere_machine>_*.sql
```

### Noodherstelopties na Decommissioning

| Situatie | Herstelbron | Procedure |
|----------|-------------|-----------|
| Centrale MySQL defect, backup beschikbaar | Centrale backup | Restore naar nieuwe MySQL |
| Centrale MySQL defect, geen recente backup | Pre-migratie backup (canoniek) | Restore naar lokale/nieuwe MySQL |
| Data corruptie ontdekt na 30 dagen | Archief volume | Untar en mount voor forensics |

---

## Schema Beheer

### Versiebeleid

| Component | Versie Indicator | Compatibiliteit Vereist |
|-----------|------------------|------------------------|
| PromptManager code | Git commit hash / tag | Alle clients moeten compatibel zijn met centrale schema |
| Database schema | Laatste migratie naam | Alle clients moeten zelfde migraties hebben |
| Yii framework | composer.lock | Minor versie verschillen acceptabel |

### Minimum Ondersteunde Versies

| Situatie | Vereiste |
|----------|----------|
| Na cut-over | Alle clients moeten dezelfde git branch/tag gebruiken |
| Bij nieuwe migratie | Client met migratie → deploy eerst, andere clients → update code, dan start |
| Schema incompatibiliteit | Client start niet op / toont migratie-error |

### Versie Afdwinging

#### Automatische Detectie

De applicatie detecteert schema-incompatibiliteit automatisch:

| Scenario | Yii Gedrag | Gebruiker ziet |
|----------|------------|----------------|
| Client schema achter | `./yii migrate/status` toont pending migrations | App werkt, maar `migrate/status` waarschuwt |
| Client schema voor (onbekende migratie) | Error bij `migrate/status` | "Unknown migration" error |
| Client code achter | Mogelijk runtime errors | Afhankelijk van code wijziging |
| Client code voor | Mogelijk missing schema features | "Column not found" of vergelijkbare errors |

#### Handmatige Versie Check (Aanbevolen voor elke start)

```bash
# Startup check script - voer uit bij twijfel over versie-alignment
#!/bin/bash
echo "=== Versie Check ==="

# 1. Code versie
echo "Code versie: $(git rev-parse --short HEAD)"
echo "Tag: $(git describe --tags --always 2>/dev/null || echo 'geen tag')"

# 2. Schema versie (laatste migratie)
echo "Schema check:"
docker exec pma_yii ./yii migrate/status 2>&1 | head -5

# 3. Connectie test
echo "DB connectie:"
docker exec pma_yii ./yii migrate/status
```

#### Afdwinging bij Discrepantie (op server)

| Discrepantie | Detectie | Afdwinging | Actie |
|--------------|----------|------------|-------|
| Pending migrations | `migrate/status` output | **Blokkeerend** - run migraties eerst | `docker exec pma_yii ./yii migrate` |
| Unknown migration | `migrate/status` error | **Blokkeerend** - update code eerst | `git pull && docker compose restart` |
| Code achter | Runtime errors | **Soft** - app start, maar fouten | `git pull && docker compose restart` |

#### Versie Synchronisatie Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                    VERSIE SYNCHRONISATIE                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Server (centrale applicatie)        Clients (browser only)         │
│  ────────────────────────────        ─────────────────────          │
│                                                                     │
│  1. git pull                         Geen actie nodig               │
│     └─► Nieuwe migratie in code      └─► Browser gebruikt altijd    │
│                                          laatste server versie      │
│  2. ./yii migrate/status                                            │
│     └─► "1 new migration pending"                                   │
│                                                                     │
│  3. docker exec pma_yii ./yii migrate                               │
│     └─► Migratie uitgevoerd                                         │
│                                                                     │
│  4. Verificatie: migrate/status                                     │
│     └─► "No new migrations"                                         │
│                                                                     │
│                                      Clients zien automatisch       │
│                                      nieuwe functionaliteit         │
│                                      na browser refresh             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Server Versie Controle

Alle versie- en schema-management gebeurt op de server. Clients gebruiken alleen de browser.

```bash
# Op server: Check code versie
cd /opt/promptmanager
git rev-parse HEAD
git describe --tags --always

# Op server: Check schema versie
docker exec pma_yii ./yii migrate/status
# Toont: "No new migrations found" OF lijst van pending migrations
```

### Compatibiliteitsregels

| Regel | Rationale | Consequentie bij overtreding |
|-------|-----------|------------------------------|
| Migraties alleen op server | Centrale locatie | Schema drift onmogelijk |
| Nooit directe DDL statements | Yii migratie administratie intact houden | Schema drift |
| Test schema synchroon houden | Test database moet matchen | Test failures |

### Governance: Schema Migraties

| Stap | Eigenaar | Criterium | Actie bij Fail |
|------|----------|-----------|----------------|
| 1. Migratie ontwikkelen | Developer | Migratie werkt lokaal | Fix migratie |
| 2. Code pushen | Developer | CI tests slagen | Fix code |
| 3. **Besluit: migratie uitvoeren** | **Server Admin** | Server up-to-date | Git pull op server |
| 4. Migratie uitvoeren | Server Admin | Migratie slaagt | Rollback, fix, retry |

### Schema Migratie Procedure

```bash
# Op server: code updaten
cd /opt/promptmanager
git pull

# Migraties uitvoeren
docker exec pma_yii ./yii migrate --interactive=0

# Test database synchroon houden
docker exec pma_yii ./yii_test migrate --interactive=0

# Verificatie
docker exec pma_yii ./yii migrate/status
# Moet tonen: "No new migrations found"
```

### Schema Drift Preventie

| Regel | Rationale |
|-------|-----------|
| Migraties alleen op server | Één centrale locatie |
| Geen directe DDL statements | Altijd via Yii migraties |
| Code update vóór migratie | Voorkomt "unknown migration" errors |

---

## Backup & Monitoring

### Backup Strategie

**Dagelijkse Backup (Cron)**

```bash
# /etc/cron.d/mysql-backup
0 2 * * * root /opt/scripts/mysql-backup.sh
```

**Backup Script met Validatie**

```bash
#!/bin/bash
# /opt/scripts/mysql-backup.sh

BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7
BACKUP_FILE="$BACKUP_DIR/promptmanager_$DATE.sql.gz"

# Maak backup directory
mkdir -p "$BACKUP_DIR"

# Maak backup via Docker container
docker exec pma_mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    promptmanager | gzip > "$BACKUP_FILE"

# Genereer checksum
sha256sum "$BACKUP_FILE" > "$BACKUP_FILE.sha256"

# Validatie: check bestandsgrootte (moet > 1KB zijn)
SIZE=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE")
if [ "$SIZE" -lt 1024 ]; then
    logger -t mysql-backup "ERROR: Backup too small ($SIZE bytes)"
    exit 1
fi

# Verwijder oude backups
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name "*.sha256" -mtime +$RETENTION_DAYS -delete

# Log result
logger -t mysql-backup "Backup completed: promptmanager_$DATE.sql.gz ($SIZE bytes)"
```

### Backup Restore Test

Voer maandelijks een restore test uit:

```bash
# Restore naar test database
gunzip -c /var/backups/mysql/promptmanager_LATEST.sql.gz | \
    docker exec -i pma_mysql mysql -u root -p"$DB_ROOT_PASSWORD" promptmanager_restore_test

# Valideer (check meerdere entiteiten)
docker exec pma_mysql mysql -u root -p"$DB_ROOT_PASSWORD" -e "
    SELECT 'project', COUNT(*) FROM promptmanager_restore_test.project
    UNION ALL
    SELECT 'prompt_template', COUNT(*) FROM promptmanager_restore_test.prompt_template;
"

# Cleanup
docker exec pma_mysql mysql -u root -p"$DB_ROOT_PASSWORD" -e "DROP DATABASE promptmanager_restore_test"
```

### Monitoring

**Health Check Script**

```bash
#!/bin/bash
# /opt/scripts/healthcheck.sh

ERRORS=0

# Test Docker containers draaien
if ! docker compose -f /opt/promptmanager/docker-compose.yml ps --quiet pma_yii | grep -q .; then
    echo "PromptManager container is DOWN" | logger -t docker-monitor
    ERRORS=$((ERRORS + 1))
fi

if ! docker compose -f /opt/promptmanager/docker-compose.yml ps --quiet pma_mysql | grep -q .; then
    echo "MySQL container is DOWN" | logger -t docker-monitor
    ERRORS=$((ERRORS + 1))
fi

# Test Tailscale status
if ! tailscale status --json | jq -e '.Self.Online' > /dev/null 2>&1; then
    echo "Tailscale is OFFLINE" | logger -t tailscale-monitor
    ERRORS=$((ERRORS + 1))
fi

# Check disk space
DISK_USAGE=$(df -h /var/lib/docker | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 90 ]; then
    echo "Disk usage critical: $DISK_USAGE%" | logger -t disk-monitor
    ERRORS=$((ERRORS + 1))
fi

# Test HTTP endpoint
if ! curl -sf http://localhost:8080 > /dev/null 2>&1; then
    echo "HTTP endpoint not responding" | logger -t http-monitor
    ERRORS=$((ERRORS + 1))
fi

exit $ERRORS
```

**Systemd Timer voor Health Check**

```ini
# /etc/systemd/system/mysql-healthcheck.timer
[Unit]
Description=MySQL Health Check Timer

[Timer]
OnCalendar=*:0/5
Persistent=true

[Install]
WantedBy=timers.target
```

---

## Troubleshooting

### Verbindingsproblemen

| Symptoom | Mogelijke Oorzaak | Oplossing |
|----------|-------------------|-----------|
| `Connection refused` in browser | Docker containers niet gestart | `docker compose up -d` op server |
| `Connection refused` in browser | Firewall blokkeert | `sudo ufw allow in on tailscale0 to any port 8080` |
| Pagina laadt niet | Tailscale niet verbonden | `tailscale up` op client |
| `Unknown host` | MagicDNS niet actief | Gebruik Tailscale IP i.p.v. hostname |
| `Connection timed out` | Tailscale niet verbonden | `tailscale up` |
| HTTP 500 error | Applicatie error | Check `docker logs pma_yii` |

### Diagnostische Commando's

```bash
# 1. Controleer Tailscale status (op client of server)
tailscale status
tailscale ping ubuntu-server

# 2. Controleer Docker containers (op server)
docker compose ps
docker logs pma_yii --tail 50
docker logs pma_mysql --tail 50

# 3. Test HTTP endpoint (op server)
curl -I http://localhost:8080

# 4. Test verbinding vanaf client
tailscale ping ubuntu-server
curl -I http://ubuntu-server:8080

# 5. Check firewall rules (op server)
sudo ufw status verbose

# 6. Check applicatie logs (op server)
docker exec pma_yii ./yii migrate/status
```

### Server-specifieke Issues

| Symptoom | Oorzaak | Oplossing |
|----------|---------|-----------|
| Containers starten niet | Docker service down | `sudo systemctl start docker` |
| pma_mysql crasht | Disk vol of memory issue | Check `docker logs pma_mysql` en disk space |
| pma_yii unhealthy | PHP/Apache issue | Check `docker logs pma_yii` |
| Port 8080 niet bereikbaar | UFW blocking | `sudo ufw allow in on tailscale0 to any port 8080` |

### Escalatie Procedure

| Stap | Actie | Tijd |
|------|-------|------|
| 1 | Diagnostische commando's uitvoeren | 5 min |
| 2 | Check troubleshooting tabel | 5 min |
| 3 | Check MySQL/Tailscale logs | 10 min |
| 4 | Overweeg rollback indien kritiek | Besluit |

---

## Acceptance Criteria

### Must Have (MVP)

| ID | Criterion | Verificatie | Scenario |
|----|-----------|-------------|----------|
| AC-1 | Tailscale geinstalleerd op server en minimaal 1 client | `tailscale status` toont beide machines | Positief |
| AC-2 | Docker containers draaien op server | `docker compose ps` toont pma_yii en pma_mysql running | Positief |
| AC-3 | Firewall blokkeert HTTP op publieke interface | `curl http://<public-ip>:8080` faalt van buiten tailnet | Security |
| AC-4 | Client kan webapplicatie bereiken | Browser naar `http://ubuntu-server:8080` laadt homepage | Positief |
| AC-5 | PromptManager werkt correct | Applicatie laadt zonder errors, CRUD werkt | Positief |
| AC-6 | Data zichtbaar op alle clients | Prompt aangemaakt op laptop zichtbaar op desktop browser | Positief |
| AC-7 | Bestaande data gemigreerd | Record counts matchen + steekproef validatie | Positief |

### Negatieve Scenario's (Must Have)

| ID | Criterion | Verificatie | Scenario |
|----|-----------|-------------|----------|
| AC-N1 | Gedrag bij Docker containers offline | Browser toont connection error, geen crash | Negatief |
| AC-N2 | Gedrag bij Tailscale offline (client) | Connection timeout in browser, geen data corruption | Negatief |
| AC-N3 | Recovery na Docker restart | App werkt weer na `docker compose restart` | Recovery |
| AC-N4 | Recovery na Tailscale reconnect | App werkt weer na `tailscale up` + browser refresh | Recovery |

### Should Have

| ID | Criterion | Verificatie |
|----|-----------|-------------|
| AC-8 | MagicDNS werkt | `ping ubuntu-server` resolvet naar 100.64.x.x |
| AC-9 | Dagelijkse backup geconfigureerd | Backup file aanwezig in `/var/backups/mysql/` |
| AC-10 | Health check script draait | Cron/timer actief en logt status |
| AC-11 | Backup restore test succesvol | Restore naar test DB valideert |

### Post-Migratie Validatie

| ID | Criterion | Verificatie |
|----|-----------|-------------|
| AC-V1 | Record counts matchen | Pre/post vergelijking per tabel (project, prompt_template, prompt_instance, context, field, scratch_pad) |
| AC-V2 | Steekproef data intact | Eerste + laatste record van project en prompt_template |
| AC-V3 | CRUD operaties werken | Create, Read, Update, Delete test op prompt_template entity |
| AC-V4 | Geen schema drift | `./yii migrate/status` op alle clients toont "No new migrations" |
| AC-V5 | File field paths geldig | Steekproef: file fields wijzen naar bestaande paden |

### Concurrency Criteria

| ID | Criterion | Verificatie |
|----|-----------|-------------|
| AC-C1 | Gelijktijdige writes slagen (verschillende records) | Twee clients schrijven tegelijk naar andere prompts → beide succesvol |
| AC-C2 | Last-write-wins bij conflict | Twee clients editen zelfde prompt → laatste save is zichtbaar |

### Regressietests (Kernflows)

Na migratie moeten de volgende bestaande workflows nog functioneren:

| ID | Kernflow | Test Stappen | Pass Criteria |
|----|----------|--------------|---------------|
| REG-1 | Project aanmaken | Create project met naam + description | Project zichtbaar in lijst |
| REG-2 | Prompt template aanmaken | Create template met content, koppel aan project | Template zichtbaar, content intact |
| REG-3 | Context beheren | Create/edit/delete context in project | CRUD succesvol |
| REG-4 | Field beheren | Create field met type, add options (voor select) | Field werkt in template |
| REG-5 | Prompt instance genereren | Genereer instance van template met ingevulde velden | Instance bevat correct gerenderde content |
| REG-6 | Scratch pad gebruiken | Create/edit scratch pad content | Content opgeslagen en leesbaar |
| REG-7 | Zoeken/filteren | Zoek op naam in prompt templates | Juiste resultaten getoond |
| REG-8 | Project linking | Link project A aan project B | Link zichtbaar en functioneel |

### Regressietest Script

```bash
#!/bin/bash
# regression-test-checklist.sh

echo "=== PromptManager Regressietest Checklist ==="
echo ""
echo "Voer handmatig uit in browser op http://localhost:8080:"
echo ""
echo "[ ] REG-1: Maak nieuw project 'Test Project'"
echo "[ ] REG-2: Maak prompt template in project"
echo "[ ] REG-3: Voeg context toe aan project"
echo "[ ] REG-4: Maak field (type: text en type: select)"
echo "[ ] REG-5: Genereer prompt instance"
echo "[ ] REG-6: Maak scratch pad entry"
echo "[ ] REG-7: Zoek op 'Test' in templates"
echo "[ ] REG-8: Link project aan ander project"
echo ""
echo "Alle tests geslaagd? [Y/n]"
```

---

## Traceerbaarheid

### Requirements naar Plan Mapping

| Requirement | Plan Sectie | Verificatie |
|-------------|-------------|-------------|
| Centrale webapplicatie voor alle clients | Architectuur, Docker Setup | AC-2, AC-4, AC-5, AC-6 |
| Data migratie van lokaal naar centraal | Migratie | AC-7, AC-V1-V4 |
| Beveiligde toegang via VPN | Security Model | AC-3 |
| Rollback mogelijkheid | Rollback Procedure | RB-1 t/m RB-5 |
| Backup strategie | Backup & Monitoring | AC-9 |
| Conflict/concurrency gedrag | Concurrency & Conflict Gedrag | AC-C1, AC-C2 |
| Bestaande functionaliteit behouden (excl. file fields) | Impact op Bestaande Functionaliteit | REG-1 t/m REG-8 |
| Gedrag bij uitval | Gedrag bij Uitval | AC-N1 t/m AC-N4 |
| Versiebeleid | Schema Beheer | AC-V4 |
| Toegangsbeheer | Roles & Permissions, Integratiecontract | Documentatie |

### Opgeloste Review Bevindingen

| ID | Categorie | Beschrijving | Resolutie | Sectie |
|----|-----------|--------------|-----------|--------|
| FO-001 | Data | Geen mechanisme om delta tussen machines te identificeren | Diff-rapport script toegevoegd | Merge & Conflict Strategie |
| FO-002 | Compatibility | File paths breken bij verschillende home directories | Analyse toont relatieve paden; alleen `root_directory` update nodig | Impact op Bestaande Functionaliteit |
| FO-003 | Data/Consistentie | Cascade-delete gedrag voor parent-child relaties onduidelijk | Delete Cascade Gedrag sectie toegevoegd met expliciete regels | Complete Data Scope |
| FO-004 | Migratie | Risico op duplicatie bij import naar server met bestaande data | Kritieke waarschuwing toegevoegd: centrale server moet met lege database starten | Migratie (Stap 4) |

### Volledige AC Traceerbaarheid

| AC ID | Omschrijving | Plan Sectie | Testmethode/Scenario |
|-------|--------------|-------------|----------------------|
| AC-1 | Tailscale geïnstalleerd op server + client | Tailscale Setup | `tailscale status` toont beide machines |
| AC-2 | Docker containers draaien | Docker Setup | `docker compose ps` toont pma_yii en pma_mysql running |
| AC-3 | Firewall blokkeert HTTP op publiek | Security Model | `curl http://<public-ip>:8080` faalt van buiten tailnet |
| AC-4 | Client kan webapplicatie bereiken | Acceptance Criteria | Browser naar `http://ubuntu-server:8080` laadt |
| AC-5 | PromptManager werkt correct | Docker Setup | App laadt zonder errors, CRUD werkt |
| AC-6 | Data zichtbaar op alle clients | Acceptance Criteria | Prompt op laptop zichtbaar op desktop browser |
| AC-7 | Bestaande data gemigreerd | Migratie, Post-Migratie Validatie | Record counts matchen + steekproef |
| AC-8 | MagicDNS werkt | Tailscale Setup | `tailscale ping ubuntu-server` succesvol |
| AC-9 | Dagelijkse backup geconfigureerd | Backup & Monitoring | Backup file aanwezig |
| AC-10 | Health check actief | Backup & Monitoring | Timer actief, logs aanwezig |
| AC-11 | Backup restore test succesvol | Backup & Monitoring | Restore naar test DB valideert |
| **AC-N1** | Gedrag bij Docker containers offline | Gedrag bij Uitval | `docker compose stop` → browser toont connection error |
| **AC-N2** | Gedrag bij Tailscale offline | Gedrag bij Uitval | `tailscale down` → connection timeout, geen data corruptie |
| **AC-N3** | Recovery na Docker restart | Recovery Procedures | `docker compose up -d` → app werkt weer |
| **AC-N4** | Recovery na Tailscale reconnect | Recovery Procedures | `tailscale up` → app werkt weer |
| **AC-V1** | Record counts matchen | Post-Migratie Validatie | Pre/post vergelijking per tabel (project, prompt_template, etc.) |
| **AC-V2** | Steekproef data intact | Post-Migratie Validatie | Eerste + laatste record per entiteit controleren |
| **AC-V3** | CRUD operaties werken | Post-Migratie Validatie | Create, Read, Update, Delete test op prompt_template |
| **AC-V4** | Geen schema drift | Schema Beheer | `./yii migrate/status` op server |
| **AC-V5** | File field beperking gedocumenteerd | Impact op Bestaande Functionaliteit | Documentatie bevat waarschuwing |
| **AC-C1** | Gelijktijdige writes (verschil. records) | Concurrency & Conflict Gedrag | Twee browsers schrijven tegelijk → beide succesvol |
| **AC-C2** | Last-write-wins bij conflict | Concurrency & Conflict Gedrag | Laatste save op zelfde record is zichtbaar |
| **REG-1** | Project aanmaken | Regressietests | Project create succesvol |
| **REG-2** | Prompt template aanmaken | Regressietests | Template create + content intact |
| **REG-3** | Context beheren | Regressietests | CRUD succesvol |
| **REG-4** | Field beheren | Regressietests | Field werkt in template |
| **REG-5** | Prompt instance genereren | Regressietests | Correct gerenderde content |
| **REG-6** | Scratch pad gebruiken | Regressietests | Content opgeslagen |
| **REG-7** | Zoeken/filteren | Regressietests | Juiste resultaten |
| **REG-8** | Project linking | Regressietests | Link functioneel |

### Negatieve Test Scenarios (AC-N)

| AC ID | Pre-conditie | Actie | Verwacht Resultaat | Post-conditie |
|-------|--------------|-------|-------------------|---------------|
| AC-N1 | App draait, containers actief | `docker compose stop` op server | Browser toont connection error, geen data corruptie | Containers gestopt |
| AC-N2 | App draait, Tailscale actief | `sudo tailscale down` op client | Browser toont connection timeout, geen data corruptie | Tailscale disconnected |
| AC-N3 | Containers gestopt (AC-N1 post) | `docker compose up -d` op server | App herstelt na page refresh | Containers actief |
| AC-N4 | Tailscale disconnected (AC-N2 post) | `sudo tailscale up` op client | App herstelt na reconnect + page refresh | Tailscale connected |

### Validatie Test Scenarios (AC-V)

| AC ID | Entiteiten | Validatie Query | Pass Criteria |
|-------|------------|-----------------|---------------|
| AC-V1 | project, prompt_template, prompt_instance, context, field, scratch_pad | `SELECT COUNT(*) FROM <table>` | Count gelijk aan pre-migratie backup |
| AC-V2 | project, prompt_template | `SELECT id, name FROM <table> ORDER BY id [ASC|DESC] LIMIT 1` | Data identiek aan pre-migratie |
| AC-V3 | prompt_template | UI: Create → Read → Update → Delete | Alle operaties succesvol |
| AC-V4 | migration tabel | `./yii migrate/status` | "No new migrations found" |
| AC-V5 | field (type=file) | **N.v.t.** - File fields wijzen naar lokale paden die niet werken na centralisatie | Documentatie duidelijk over beperking |

### Concurrency Test Scenarios (AC-C)

| AC ID | Setup | Test | Pass Criteria |
|-------|-------|------|---------------|
| AC-C1 | Twee browsers open, beide ingelogd | Client A edit prompt 1, Client B edit prompt 2 tegelijk | Beide saves succesvol, beide prompts correct |
| AC-C2 | Twee browsers open, zelfde prompt | Client A edit + save, Client B edit (oude data) + save | B's versie is zichtbaar; A's wijzigingen overschreven |

### Regressietest Scenarios (REG)

| REG ID | Precondities | Stappen | Pass Criteria |
|--------|--------------|---------|---------------|
| REG-1 | Ingelogd | Create project "Test" | Project in lijst |
| REG-2 | Project bestaat | Create template met Quill content | Template toont correcte content |
| REG-3 | Project bestaat | CRUD context | Alle operaties OK |
| REG-4 | Project + field | Create select field + options | Options toonbaar in template |
| REG-5 | Template + context + fields ingevuld | Generate instance | Content correct gerenderd |
| REG-6 | Project bestaat | Create/edit scratch pad | Content persistent |
| REG-7 | Meerdere templates | Zoek op naam | Correcte resultaten |
| REG-8 | Twee projecten | Link project A → B | Link zichtbaar en volgbaar |

---

## Quick Start Checklist

### Server Setup (20 min)

- [ ] Tailscale installeren: `curl -fsSL https://tailscale.com/install.sh | sh`
- [ ] Tailscale starten: `sudo tailscale up`
- [ ] Docker installeren (indien nodig): `curl -fsSL https://get.docker.com | sh`
- [ ] PromptManager clonen: `git clone <repo> /opt/promptmanager`
- [ ] .env configureren met database credentials
- [ ] Docker containers starten: `docker compose up -d`
- [ ] UFW regel toevoegen: `sudo ufw allow in on tailscale0 to any port 8080`
- [ ] Verificatie: browser openen naar `http://localhost:8080`

### Pre-Migratie (10 min)

- [ ] Inventariseer data op alle lokale machines
- [ ] Selecteer canonieke bron (besluit D3)
- [ ] Maak backup van ALLE lokale databases
- [ ] Documenteer wat niet wordt gemigreerd

### Migratie (10 min)

- [ ] Export canonieke database met checksum
- [ ] Transfer naar server
- [ ] Verificeer checksum
- [ ] Import op centrale MySQL (via Docker)
- [ ] Run migraties: `docker exec pma_yii ./yii migrate`
- [ ] Valideer (record counts, steekproef)

### Client Setup (2 min per client)

- [ ] Tailscale installeren
- [ ] Tailscale verbinden: `tailscale up`
- [ ] Browser openen naar `http://ubuntu-server:8080`
- [ ] Verificatie: data zichtbaar, CRUD test

> **Note:** Clients hebben geen Docker, geen .env, geen code nodig. Alleen Tailscale + browser.

### Post-Setup (5 min)

- [ ] Configureer backup cron op server
- [ ] Test backup script handmatig
- [ ] Activeer health check timer

---

## Appendix: Volledige Configuratiebestanden

### UFW Rules Summary (Server)

```bash
sudo ufw status numbered
# Status: active
#
#      To                         Action      From
#      --                         ------      ----
# [ 1] 22/tcp                     ALLOW IN    Anywhere
# [ 2] 8080                       ALLOW IN    on tailscale0
```

> **Let op:** UFW alleen is niet voldoende - Docker kan UFW omzeilen via iptables. Voer ook Docker hardening uit (zie TO sectie 4.4.1).

### docker-compose.yml (Server)

De centrale server gebruikt de standaard `docker-compose.yml` uit het repository met de volgende containers:

| Container | Functie |
|-----------|---------|
| `pma_yii` | PHP/Yii applicatie (PHP-FPM) |
| `pma_nginx` | Nginx webserver (reverse proxy op `NGINX_PORT`) |
| `pma_mysql` | MySQL 8.0 database |

Voor Docker firewall hardening (aanbevolen), pas de nginx ports aan:
```yaml
# Origineel (luistert op alle interfaces):
ports:
  - "${NGINX_PORT:-8080}:80"

# Met Tailscale IP binding (alleen Tailscale interface):
ports:
  - "${TAILSCALE_IP:-127.0.0.1}:${NGINX_PORT:-8080}:80"
```

Zie het TO document (sectie 4.4.1) voor volledige Docker hardening instructies.

### .env (Server)

```bash
# Basis configuratie
USER_ID=1000
USER_NAME=appuser
TIMEZONE=Europe/Amsterdam

# Database credentials
DB_ROOT_PASSWORD=<genereer-sterk-password>
DB_HOST=pma_mysql
DB_DATABASE=promptmanager
DB_USER=promptmanager
DB_PASSWORD=<genereer-sterk-password>

# Centrale server poort
NGINX_PORT=8080

# Tailscale IP voor Docker hardening (verkrijg met: tailscale ip -4)
TAILSCALE_IP=100.x.x.x
```

> **Note:** Clients hebben geen Docker nodig - zij gebruiken alleen de browser.
