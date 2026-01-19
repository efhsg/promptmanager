# Bi-Directional Sync API Design

## Document Metadata

| Eigenschap | Waarde |
|------------|--------|
| Versie | 1.5 |
| Status | Draft |
| Parent Document | tailscale-webapp-central.md |
| Laatste update | 2026-01-19 |

### Besluitlog

| Datum | Besluit |
|-------|---------|
| 2026-01-19 | S1: Last-write-wins automatisch |
| 2026-01-19 | S2: File fields excluded van sync |
| 2026-01-19 | S3: Auto + manual sync |
| 2026-01-19 | S5: User ID vervangen bij import (single-user model) |
| 2026-01-19 | S6: Volledig P2P (geen master peer) |
| 2026-01-19 | S7: Clock skew grace period (5 sec) |
| 2026-01-19 | S8: URL versioning voor API |
| 2026-01-19 | Sync volgorde expliciet gedocumenteerd (dependency order) |
| 2026-01-19 | S9: Quill Delta limiet (10MB hard, 5MB warning) |
| 2026-01-19 | File-field sync verduidelijkt (entity wel, value niet) |
| 2026-01-19 | ProjectLinkedProject conflict resolution: last-write-wins |
| 2026-01-19 | API versioning geuniformeerd (1.0 notatie, alle endpoints) |
| 2026-01-19 | Conflict audit log toegevoegd (sync_conflict_log tabel) |
| 2026-01-19 | Entity payload specificaties toegevoegd |
| 2026-01-19 | Impact op bestaande functionaliteit gedocumenteerd |
| 2026-01-19 | Herstelproces na ongewenst conflict toegevoegd |

---

## Scope Wijziging

Dit document vervangt de "centrale webapplicatie" architectuur met een **federatief model** waarbij meerdere onafhankelijke PromptManager instanties hun data synchroniseren.

### Oude Architectuur (Vervalt)

```
┌─────────────┐         ┌─────────────┐
│   Laptop    │         │   Desktop   │
│  (Browser)  │────────►│  (Browser)  │
└─────────────┘         └─────────────┘
       │                       │
       └───────────┬───────────┘
                   ▼
         ┌─────────────────┐
         │  Central Server │
         │  (Single SoR)   │
         └─────────────────┘
```

### Nieuwe Architectuur

```
┌─────────────────────────┐              ┌─────────────────────────┐
│      Ubuntu Server      │              │    Windows Desktop      │
│  ┌───────────────────┐  │   Tailscale  │  ┌───────────────────┐  │
│  │  PromptManager    │  │     VPN      │  │  PromptManager    │  │
│  │  (Docker Stack)   │◄─┼──────────────┼─►│  (Docker Stack)   │  │
│  │  + MySQL (SoR A)  │  │   Sync API   │  │  + MySQL (SoR B)  │  │
│  └───────────────────┘  │              │  └───────────────────┘  │
└─────────────────────────┘              └─────────────────────────┘
         │                                          │
         │                                          │
         ▼                                          ▼
   Standalone OK                              Standalone OK
   (eigen database)                           (eigen database)
```

**Kenmerken:**
- Beide machines draaien volledige PromptManager stack
- Beide machines hebben eigen MySQL database
- Beide machines kunnen onafhankelijk werken (offline)
- Sync gebeurt on-demand via API over Tailscale
- Geen "centrale server" - peers zijn gelijkwaardig

---

## Impact op Bestaande Functionaliteit

### Gewijzigde flows

| Flow | Wijziging | Impact |
|------|-----------|--------|
| CRUD operaties | `uuid` wordt automatisch gegenereerd bij create | Geen user impact, transparant |
| Soft delete | `deleted_at` kolom toegevoegd aan alle sync-entiteiten | Geen user impact, transparant |
| Field (file types) | Value wordt niet gesynct | User moet file paths per machine instellen |

### Nieuwe randvoorwaarden

| Onderdeel | Randvoorwaarde |
|-----------|----------------|
| Database | UUID en deleted_at kolommen vereist (migratie) |
| Quill Delta content | Max 10MB per entity |
| Sync | Tailscale VPN actief voor peer bereikbaarheid |
| Tijdsynchronisatie | NTP aanbevolen voor betrouwbare LWW |

### Placeholder gedrag

Placeholders (`PRJ:{{name}}`, `EXT:{{label:name}}`) blijven lokaal werken. Bij sync:
- Field definities worden gesynct (naam, type)
- Field values voor file types worden NIET gesynct → placeholder resolveert naar null op andere machine
- User moet file-type fields lokaal configureren na sync

---

## User & Ownership Scope

### Single-User Model

Dit FO gaat uit van een **single-user per instance** model:

| Aspect | Aanname |
|--------|---------|
| Gebruikers per instance | 1 (dezelfde fysieke gebruiker) |
| User accounts | Elk instance heeft eigen `user` record |
| Ownership | Alle data behoort tot de lokale user |

### User ID Mapping

Bij sync wordt `user_id` als volgt behandeld:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    USER ID MAPPING BIJ SYNC                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Machine A (user_id = 1)              Machine B (user_id = 1)       │
│  ─────────────────────────            ─────────────────────────     │
│                                                                     │
│  Project X (user_id = 1) ────sync────► Project X (user_id = 1)     │
│                                                                     │
│  Regel: Bij import wordt user_id ALTIJD vervangen door de          │
│         lokale user_id van de ontvangende machine.                  │
│                                                                     │
│  Dit betekent:                                                      │
│  - user_id wordt NIET gesynct                                       │
│  - Geïmporteerde entiteiten krijgen lokale user_id                  │
│  - Ownership blijft altijd bij lokale user                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Rationale:** Omdat elke instance door dezelfde fysieke gebruiker wordt beheerd, is user mapping triviaal. Multi-user sync valt buiten scope van MVP.

### Rollen en Autoriteit

In het single-user model is de lokale user altijd de eigenaar van alle sync-acties:

| Actie | Uitvoerder | Autorisatie |
|-------|------------|-------------|
| Peer token genereren | Lokale owner | Ingelogd als owner |
| Peer token revoken | Lokale owner | Ingelogd als owner |
| Sync starten (manual) | Lokale owner | Ingelogd als owner |
| Sync starten (auto) | Systeem | Geconfigureerd door owner |
| Sync log inzien | Lokale owner | Ingelogd als owner |
| Conflict log inzien | Lokale owner | Ingelogd als owner |

**Geen uitzonderingen:** Er zijn geen gevallen waar niet-owner acties worden uitgevoerd. Alle sync-operaties vereisen geldige Bearer token die gekoppeld is aan de lokale owner.

### Buiten Scope

- Multi-user instances (meerdere gebruikers per database)
- User account synchronisatie
- UserPreference synchronisatie (machine-specifieke UI instellingen)
- Access control / permissies tussen users
- Shared ownership van projecten

---

## Sync Model

### Entiteiten en Sync Scope

| Entiteit | Sync | Conflict Resolution | Notes |
|----------|------|---------------------|-------|
| User | Nee | N/A | Elke stack heeft eigen user(s), user_id wordt bij import vervangen |
| UserPreference | Nee | N/A | Machine-specifieke instellingen (UI preferences) |
| Project | Ja | Last-write-wins | Soft delete sync |
| Context | Ja | Last-write-wins | Quill Delta content |
| Field | Ja | Last-write-wins | Entity gesynct, value=null voor file/directory/file_list types (S2) |
| FieldOption | Ja | Parent wins | Volgt Field, soft delete |
| PromptTemplate | Ja | Last-write-wins | |
| TemplateField | Ja | Parent wins | Volgt PromptTemplate, soft delete |
| PromptInstance | Ja | Last-write-wins | Gegenereerde content |
| ScratchPad | Ja | Last-write-wins | Quill Delta content |
| ProjectLinkedProject | Ja | Last-write-wins | Delete vs present via timestamp vergelijking |

### Sync Identifier

Elke entiteit krijgt een **UUID** naast de bestaande auto-increment `id`:

```sql
ALTER TABLE project ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE project ADD UNIQUE INDEX idx_project_uuid (uuid);
-- Herhaal voor alle sync-entiteiten
```

**Rationale:** Auto-increment IDs conflicteren tussen databases. UUID is globally unique.

### Sync Scope: Full vs Delta

| Aspect | MVP (Must Have) | Post-MVP (Should Have) |
|--------|-----------------|------------------------|
| Manifest | Alle entiteiten | Filter op `since=timestamp` |
| Sync operatie | Full comparison | Alleen wijzigingen sinds laatste sync |
| Performance | O(n) alle records | O(delta) alleen wijzigingen |

**MVP Aanpak (Full Sync):**
- Manifest bevat ALLE entiteiten met hun `updated_at` timestamps
- Client vergelijkt complete manifests om verschillen te detecteren
- Eenvoudig te implementeren, geen state tracking nodig
- Geschikt voor datasets < 10.000 entiteiten

**Post-MVP Optimalisatie (Delta Sync):**
- Manifest ondersteunt `since=timestamp` parameter
- Alleen entiteiten gewijzigd sinds timestamp worden geretourneerd
- Vereist betrouwbare `last_sync_at` tracking per peer

### Sync Protocol

**Één sync-operatie synchroniseert beide databases volledig.**

```
┌─────────────────────────────────────────────────────────────────────┐
│                    BIDIRECTIONEEL SYNC PROTOCOL                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Machine A (initiator)                  Machine B (responder)       │
│  ─────────────────────                  ─────────────────────       │
│                                                                     │
│  1. GET /api/sync/v1/manifest ──────────────────────────────────►   │
│     Response: {entities: [{uuid, updated_at}, ...]}                 │
│                                                                     │
│  2. Compare manifests (A doet dit lokaal)                           │
│     - B newer → PULL from B                                         │
│     - A newer → PUSH to B                                           │
│     - Only on B → PULL (new entity)                                 │
│     - Only on A → PUSH (new entity)                                 │
│     - Deleted on A → PUSH deletion                                  │
│     - Deleted on B → PULL deletion                                  │
│                                                                     │
│  3. GET /api/sync/v1/pull?uuids=... ◄───────────────────────────    │
│     → A ontvangt nieuwere/nieuwe entities van B                     │
│     → A past lokale database aan                                    │
│                                                                     │
│  4. POST /api/sync/v1/push ──────────────────────────────────────►  │
│     → B ontvangt nieuwere/nieuwe entities van A                     │
│     → B past lokale database aan                                    │
│     Response: {accepted: [...], conflicts: [...]}                   │
│                                                                     │
│  5. Conflict resolution (automatisch, last-write-wins)              │
│     → Beide machines hebben nu dezelfde data                        │
│                                                                     │
│  6. Update sync_state op BEIDE machines                             │
│     - last_sync_at = NOW()                                          │
│     - peer_id = andere machine                                      │
│                                                                     │
│  RESULTAAT: A en B zijn identiek                                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Note:** Het maakt niet uit welke machine initieert - resultaat is altijd dat beide databases in sync zijn.

---

## API Specification

### Authentication

```
Authorization: Bearer <access_token>
X-Sync-Peer-ID: <uuid-of-requesting-machine>
```

### Peer Onboarding Flow

Voordat twee machines kunnen syncen, moeten ze elkaar "kennen" via een eenmalige onboarding:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PEER ONBOARDING (EENMALIG)                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  STAP 1: Machine A genereert sync credentials                       │
│  ────────────────────────────────────────────                       │
│  UI: Settings > Sync > "Generate Peer Token"                        │
│  Resultaat:                                                         │
│  - peer_id: "machine-a-uuid" (gegenereerd of uit config)            │
│  - access_token: "random-bearer-token"                              │
│  - peer_url: "http://100.x.y.z:8080" (Tailscale IP)                 │
│                                                                     │
│  STAP 2: Gebruiker voert credentials in op Machine B                │
│  ────────────────────────────────────────────────                   │
│  UI: Settings > Sync > "Add Peer"                                   │
│  Invoer:                                                            │
│  - Peer URL: http://100.x.y.z:8080                                  │
│  - Access Token: <plak token van stap 1>                            │
│                                                                     │
│  STAP 3: Machine B verifieert connectie                             │
│  ─────────────────────────────────────────                          │
│  Request: GET /api/sync/handshake                                   │
│  Response: {success: true, peer_id: "machine-a-uuid", name: "..."}  │
│                                                                     │
│  STAP 4: Machine B stuurt eigen credentials terug                   │
│  ────────────────────────────────────────────────                   │
│  Automatisch na succesvolle handshake                               │
│  Machine A ontvangt Machine B's peer_id en slaat op                 │
│                                                                     │
│  RESULTAAT: Beide machines kennen elkaar                            │
│  - sync_state tabel bevat peer record op beide machines             │
│  - Sync kan nu worden uitgevoerd                                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Handshake Endpoint:**

```http
GET /api/sync/v1/handshake
Authorization: Bearer xxx
X-Sync-Peer-ID: machine-b-uuid
X-Sync-Peer-URL: http://100.x.y.w:8080
X-Sync-Peer-Token: <machine-b-token>
```

**Response:**
```json
{
  "success": true,
  "peer_id": "machine-a-uuid",
  "peer_name": "Ubuntu Server",
  "api_version": "1.0",
  "min_supported_version": "1.0",
  "supported_entities": ["project", "context", "field", "field_option", "prompt_template", "template_field", "prompt_instance", "scratch_pad", "project_linked_project"]
}
```

**Token Storage:**

Tokens worden opgeslagen in de database (niet in `.env`):

```sql
-- sync_peer tabel (nieuw)
CREATE TABLE sync_peer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    peer_name VARCHAR(255) NULL,
    peer_url VARCHAR(255) NOT NULL,
    access_token_hash VARCHAR(255) NOT NULL,  -- bcrypt hash
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX idx_peer_id (peer_id)
);
```

**Security Notes:**
- Tokens worden alleen bij generatie getoond (daarna alleen hash)
- Tailscale VPN biedt extra netwerk-level beveiliging
- Tokens kunnen worden gerevoked via UI

### Endpoints

#### 1. GET /api/sync/v1/manifest

Retourneert alle UUIDs met hun `updated_at` timestamps.

**Request:**
```http
GET /api/sync/v1/manifest?since=2026-01-01T00:00:00Z
Authorization: Bearer xxx
X-Sync-Peer-ID: machine-a-uuid
```

**Response:**
```json
{
  "success": true,
  "peer_id": "machine-b-uuid",
  "generated_at": "2026-01-19T12:00:00Z",
  "entities": {
    "project": [
      {"uuid": "proj-uuid-1", "updated_at": "2026-01-18T10:00:00Z", "deleted_at": null},
      {"uuid": "proj-uuid-2", "updated_at": "2026-01-19T09:00:00Z", "deleted_at": "2026-01-19T09:00:00Z"}
    ],
    "context": [
      {"uuid": "ctx-uuid-1", "updated_at": "2026-01-17T15:00:00Z", "deleted_at": null}
    ],
    "field": [...],
    "field_option": [...],
    "prompt_template": [...],
    "template_field": [...],
    "prompt_instance": [...],
    "scratch_pad": [...],
    "project_linked_project": [...]
  }
}
```

#### 2. GET /api/sync/v1/pull

Haalt volledige entiteiten op basis van UUIDs.

**Request:**
```http
GET /api/sync/v1/pull?uuids=proj-uuid-1,ctx-uuid-1,ctx-uuid-2
Authorization: Bearer xxx
```

**Response:**
```json
{
  "success": true,
  "entities": [
    {
      "type": "project",
      "uuid": "proj-uuid-1",
      "updated_at": "2026-01-18T10:00:00Z",
      "deleted_at": null,
      "data": {
        "name": "My Project",
        "description": "...",
        "root_directory": "/home/user/projects/myapp",
        "allowed_file_extensions": ["php", "js"],
        "blacklisted_directories": ["vendor", "node_modules"]
      },
      "children": {
        "context": ["ctx-uuid-1", "ctx-uuid-2"],
        "field": ["field-uuid-1"],
        "prompt_template": ["tmpl-uuid-1"]
      }
    },
    {
      "type": "context",
      "uuid": "ctx-uuid-1",
      "updated_at": "2026-01-17T15:00:00Z",
      "parent_uuid": "proj-uuid-1",
      "data": {
        "name": "Default Context",
        "content": {"ops": [{"insert": "..."}]},
        "is_default": true,
        "share": false,
        "order": 1
      }
    }
  ]
}
```

### Entity Payload Specificaties

Elke entity in pull/push heeft een gestandaardiseerd formaat:

```json
{
  "type": "<entity_type>",
  "uuid": "<uuid>",
  "updated_at": "<ISO8601>",
  "deleted_at": "<ISO8601|null>",
  "parent_uuid": "<uuid|null>",
  "data": { /* entity-specifieke velden */ }
}
```

**Verplichte velden per entity type:**

| Entity | parent_uuid | Verplichte data velden |
|--------|-------------|------------------------|
| project | null | name, description, root_directory, allowed_file_extensions, blacklisted_directories |
| context | project.uuid | name, content (Quill Delta), is_default, share, order |
| field | project.uuid | name, label, type, content, value*, order |
| field_option | field.uuid | label, value, order |
| prompt_template | project.uuid | name, content (Quill Delta), order |
| template_field | prompt_template.uuid | field_uuid, order |
| prompt_instance | prompt_template.uuid | name, content, field_values (JSON) |
| scratch_pad | project.uuid (nullable) | name, content (Quill Delta) |
| project_linked_project | project.uuid | linked_project_uuid, label |

*Field.value = null voor type file/directory/file_list (zie S2)

**Voorbeeld payloads:**

```json
// field_option
{
  "type": "field_option",
  "uuid": "opt-uuid-1",
  "updated_at": "2026-01-19T10:00:00Z",
  "deleted_at": null,
  "parent_uuid": "field-uuid-1",
  "data": {
    "label": "Option A",
    "value": "option_a",
    "order": 1
  }
}

// template_field (join entity)
{
  "type": "template_field",
  "uuid": "tf-uuid-1",
  "updated_at": "2026-01-19T10:00:00Z",
  "deleted_at": null,
  "parent_uuid": "tmpl-uuid-1",
  "data": {
    "field_uuid": "field-uuid-1",
    "order": 1
  }
}

// project_linked_project
{
  "type": "project_linked_project",
  "uuid": "plp-uuid-1",
  "updated_at": "2026-01-19T10:00:00Z",
  "deleted_at": null,
  "parent_uuid": "proj-uuid-1",
  "data": {
    "linked_project_uuid": "proj-uuid-2",
    "label": "Shared Components"
  }
}

// prompt_instance
{
  "type": "prompt_instance",
  "uuid": "pi-uuid-1",
  "updated_at": "2026-01-19T10:00:00Z",
  "deleted_at": null,
  "parent_uuid": "tmpl-uuid-1",
  "data": {
    "name": "Generated 2026-01-19",
    "content": "The generated prompt text...",
    "field_values": {"field-uuid-1": "value1", "field-uuid-2": "value2"}
  }
}

// scratch_pad
{
  "type": "scratch_pad",
  "uuid": "sp-uuid-1",
  "updated_at": "2026-01-19T10:00:00Z",
  "deleted_at": null,
  "parent_uuid": "proj-uuid-1",
  "data": {
    "name": "My Notes",
    "content": {"ops": [{"insert": "Notes content..."}]}
  }
}
```

#### 3. POST /api/sync/v1/push

Pusht entiteiten naar remote peer.

**Request:**
```http
POST /api/sync/v1/push
Authorization: Bearer xxx
Content-Type: application/json

{
  "entities": [
    {
      "type": "project",
      "uuid": "proj-uuid-3",
      "updated_at": "2026-01-19T11:00:00Z",
      "deleted_at": null,
      "data": {
        "name": "New Project",
        "description": "Created on Machine A"
      }
    },
    {
      "type": "context",
      "uuid": "ctx-uuid-5",
      "updated_at": "2026-01-19T11:05:00Z",
      "parent_uuid": "proj-uuid-3",
      "data": {
        "name": "Main Context",
        "content": {"ops": [{"insert": "Hello"}]}
      }
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "accepted": ["proj-uuid-3", "ctx-uuid-5"],
  "conflicts": [],
  "errors": []
}
```

**Conflict Response (indien last-write-wins niet automatisch):**
```json
{
  "success": false,
  "accepted": ["ctx-uuid-5"],
  "conflicts": [
    {
      "uuid": "proj-uuid-3",
      "type": "project",
      "local_updated_at": "2026-01-19T11:30:00Z",
      "remote_updated_at": "2026-01-19T11:00:00Z",
      "resolution": "local_wins"
    }
  ]
}
```

---

## Conflict Resolution

### Strategie: Last-Write-Wins (Automatisch)

```
if (remote.updated_at > local.updated_at) {
    // Remote wint - accepteer remote data
    upsert(remote.data);
} else if (remote.updated_at < local.updated_at) {
    // Local wint - reject remote, stuur local terug
    reject(remote);
} else {
    // Exacte timestamp match - gebruik UUID als tiebreaker
    if (remote.uuid > local.uuid) {
        upsert(remote.data);
    }
}
```

### Soft Deletes

```
if (remote.deleted_at != null && local.deleted_at == null) {
    if (remote.deleted_at > local.updated_at) {
        // Remote delete is nieuwer dan local edit - delete local
        softDelete(local);
    } else {
        // Local edit is nieuwer dan remote delete - restore remote
        // Push local naar remote om delete ongedaan te maken
        pushToRemote(local);
    }
}
```

### Cascade Soft Delete Policy

Bij soft-delete van een parent worden children **NIET** automatisch soft-deleted:

| Parent verwijderd | Children gedrag |
|-------------------|-----------------|
| Project | Context, Field, PromptTemplate, ScratchPad blijven `deleted_at=null` |
| PromptTemplate | TemplateField, PromptInstance blijven `deleted_at=null` |
| Field | FieldOption blijft `deleted_at=null` |

**Rationale:**
- Children blijven intact voor restore scenario (undelete parent → children direct beschikbaar)
- Applicatie filtert op `parent.deleted_at IS NULL` in queries
- Orphaned children (child toegevoegd terwijl parent deleted) worden onzichtbaar tot parent restore

**Uitzondering:** ProjectLinkedProject wordt WEL cascade soft-deleted (zie ProjectLinkedProject Sync Rules).

### Cascade Sync

Bij sync van parent entiteit, sync ook children:

| Parent | Children |
|--------|----------|
| Project | Context, Field, PromptTemplate, ScratchPad, ProjectLinkedProject |
| PromptTemplate | TemplateField, PromptInstance |
| Field | FieldOption |

### Sync Volgorde (Dependency Order)

Entiteiten moeten in specifieke volgorde worden gesynct vanwege foreign key dependencies:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SYNC VOLGORDE (IMPORT)                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Stap  Entiteit              Dependencies                           │
│  ────  ────────              ────────────                           │
│   1    Project               (geen - root entiteit)                 │
│   2    Context               → Project                              │
│   3    Field                 → Project                              │
│   4    FieldOption           → Field                                │
│   5    PromptTemplate        → Project                              │
│   6    TemplateField         → PromptTemplate + Field ⚠️            │
│   7    PromptInstance        → PromptTemplate                       │
│   8    ScratchPad            → Project                              │
│   9    ProjectLinkedProject  → Project + Project ⚠️                 │
│                                                                     │
│  ⚠️ = Dubbele dependency - beide parents moeten bestaan            │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Kritieke volgorde regels:**

| Entiteit | Moet wachten op |
|----------|-----------------|
| TemplateField | Field EN PromptTemplate (beide stap 3+5 voltooid) |
| ProjectLinkedProject | Beide Project records (linked_project_id moet ook bestaan) |

**Implementatie:**

```php
// SyncService::importEntities()
$importOrder = [
    'project',
    'context',
    'field',
    'field_option',
    'prompt_template',
    'template_field',      // NA field + prompt_template
    'prompt_instance',
    'scratch_pad',
    'project_linked_project', // LAATST (beide projects nodig)
];

foreach ($importOrder as $entityType) {
    $this->importEntitiesOfType($entityType, $pulledData);
}
```

**Export volgorde:** Bij export maakt volgorde niet uit - alle data wordt opgehaald. Volgorde is alleen kritiek bij import.

### ProjectLinkedProject Sync Rules

`ProjectLinkedProject` is een join-tabel met speciale sync regels:

```
┌─────────────────────────────────────────────────────────────────────┐
│               PROJECT LINKED PROJECT SYNC RULES                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Structuur:                                                         │
│  - project_id (FK → project owning the link)                       │
│  - linked_project_id (FK → project being linked to)                │
│  - label (display name for the link)                                │
│                                                                     │
│  Sync Gedrag:                                                       │
│  ────────────                                                       │
│  CREATE: Link wordt aangemaakt → sync als nieuwe entiteit           │
│  DELETE: Link wordt verwijderd → sync als soft delete               │
│                                                                     │
│  Conflict Resolution: LAST-WRITE-WINS (niet UNION)                  │
│  ─────────────────────────────────────────────────                  │
│  - Link nieuw op A, niet op B → creëer op B                        │
│  - Link nieuw op B, niet op A → creëer op A                        │
│  - Link deleted op A, present op B:                                │
│      → vergelijk deleted_at vs updated_at                          │
│      → delete wint als deleted_at > B.updated_at                   │
│      → present wint als B.updated_at > deleted_at                  │
│  - Link op beide deleted → blijft deleted                          │
│                                                                     │
│  Edge Cases:                                                        │
│  ───────────                                                        │
│  1. Linked project bestaat niet op target machine                  │
│     → Link wordt NIET gecreëerd, log warning                       │
│     → Bij volgende sync (na project sync) wordt link alsnog        │
│       gecreëerd                                                     │
│                                                                     │
│  2. Project wordt verwijderd                                        │
│     → Alle ProjectLinkedProject records met dit project_id         │
│       worden soft deleted (cascade)                                 │
│     → Links NAAR dit project (linked_project_id) blijven bestaan   │
│       maar zijn "broken" tot project weer bestaat                   │
│                                                                     │
│  3. Unlink vs Delete                                                │
│     → Unlink = soft delete van ProjectLinkedProject record         │
│     → Bij sync wordt unlink doorgevoerd op andere machine          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

**Database wijziging voor soft delete:**

```sql
ALTER TABLE project_linked_project ADD COLUMN deleted_at DATETIME NULL;
```

---

## Database Wijzigingen

### Nieuwe Kolommen

```sql
-- UUID voor global identity
ALTER TABLE project ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE context ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE field ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE field_option ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE prompt_template ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE template_field ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE prompt_instance ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE scratch_pad ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;
ALTER TABLE project_linked_project ADD COLUMN uuid CHAR(36) NOT NULL AFTER id;

-- Unique indexes
ALTER TABLE project ADD UNIQUE INDEX idx_project_uuid (uuid);
-- ... (herhaal voor alle tabellen)

-- Soft delete voor alle entiteiten (project heeft al)
ALTER TABLE context ADD COLUMN deleted_at DATETIME NULL;
ALTER TABLE field ADD COLUMN deleted_at DATETIME NULL;
-- ... (herhaal voor alle tabellen zonder deleted_at)
```

### Nieuwe Tabel: sync_state

```sql
CREATE TABLE sync_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    peer_name VARCHAR(255) NULL,
    last_sync_at DATETIME NOT NULL,
    last_manifest_hash CHAR(64) NULL,
    sync_direction ENUM('push', 'pull', 'both') DEFAULT 'both',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX idx_peer_id (peer_id)
);
```

### Nieuwe Tabel: sync_log

```sql
CREATE TABLE sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    sync_started_at DATETIME NOT NULL,
    sync_completed_at DATETIME NULL,
    entities_pushed INT DEFAULT 0,
    entities_pulled INT DEFAULT 0,
    conflicts_count INT DEFAULT 0,
    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_peer_sync (peer_id, sync_started_at)
);
```

### Nieuwe Tabel: sync_conflict_log

Voor audit-doeleinden wordt elk conflict individueel gelogd:

```sql
CREATE TABLE sync_conflict_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_log_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_uuid CHAR(36) NOT NULL,
    local_updated_at DATETIME NOT NULL,
    remote_updated_at DATETIME NOT NULL,
    resolution ENUM('local_wins', 'remote_wins') NOT NULL,
    local_snapshot JSON NULL,      -- optioneel: data voor herstel
    remote_snapshot JSON NULL,     -- optioneel: data voor herstel
    created_at DATETIME NOT NULL,
    INDEX idx_sync_log (sync_log_id),
    INDEX idx_entity (entity_type, entity_uuid)
);
```

**Audit velden per conflict:**

| Veld | Doel |
|------|------|
| entity_type | Welk type entity (project, context, etc.) |
| entity_uuid | Welke specifieke entity |
| local_updated_at | Timestamp van lokale versie |
| remote_updated_at | Timestamp van remote versie |
| resolution | Wie won (local_wins / remote_wins) |
| local_snapshot | JSON snapshot van lokale data (voor herstel) |
| remote_snapshot | JSON snapshot van remote data (voor analyse) |

### Nieuwe Tabel: sync_peer

```sql
CREATE TABLE sync_peer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    peer_id CHAR(36) NOT NULL,
    peer_name VARCHAR(255) NULL,
    peer_url VARCHAR(255) NOT NULL,
    access_token_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_handshake_at DATETIME NULL,
    api_version VARCHAR(10) DEFAULT 'v1',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE INDEX idx_sync_peer_id (peer_id)
);
```

---

## File Path Handling

### Probleem

`Field` entiteiten met type `file` of `directory` bevatten paden die machine-specifiek zijn:
- Linux: `/home/user/projects/myapp/src/Controller.php`
- Windows: `C:\Users\user\projects\myapp\src\Controller.php`

### Oplossing: Path Mapping

Elke machine definieert path mappings in `.env`:

```bash
# Machine A (Linux server)
SYNC_PATH_MAPPINGS='{"projects": "/home/user/projects"}'

# Machine B (Windows)
SYNC_PATH_MAPPINGS='{"projects": "C:\\Users\\user\\projects"}'
```

Bij sync:
1. **Export:** Vervang absolute paths met placeholders: `{{projects}}/myapp/src/Controller.php`
2. **Import:** Vervang placeholders met lokale paths

### Alternatief: Geen File Sync

File-type fields worden niet gesynct. Elke machine beheert eigen file references.

```json
{
  "sync_exclude_field_types": ["file", "directory", "file_list"]
}
```

---

## Sync UI/UX

### Sync Trigger

**Manual:** Via UI knop of keyboard shortcut

**Automatisch:** Background sync met configureerbaar interval

```
┌─────────────────────────────────────────────────────────────────┐
│  PromptManager                                    [Sync ↻]  ⚙  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Projects    Templates    Fields    Settings                    │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  Sync Status                                     [⚙]    │    │
│  │  ──────────────────────────────────────────────────────│    │
│  │  ● Auto-sync: ON (every 15 min)                        │    │
│  │  Last sync: 2026-01-19 11:45 with "ubuntu-server"      │    │
│  │  Next sync: in 12 minutes                               │    │
│  │                                                         │    │
│  │  Local changes: 3 pending                               │    │
│  │  Remote changes: unknown (check on next sync)           │    │
│  │                                                         │    │
│  │  [Sync Now]  [View Log]                                │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Auto-Sync Configuratie

| Setting | Default | Opties |
|---------|---------|--------|
| Auto-sync enabled | Ja | Ja / Nee |
| Sync interval | 15 minuten | 5 / 15 / 30 / 60 minuten |
| Sync on startup | Ja | Ja / Nee |
| Notify on conflict | Ja | Ja / Nee |
| Peer URL | - | `http://ubuntu-server:8080` of IP |

> **Tip:** Slechts één machine hoeft auto-sync aan te hebben. Elke sync-operatie is bidirectioneel en synchroniseert beide databases volledig.

**Gedrag:**
- Auto-sync draait alleen als peer bereikbaar is (Tailscale connected)
- **Sync is altijd bidirectioneel:** één machine initieert, beide worden bijgewerkt
- Bij netwerk fout: retry na 1 minuut, dan exponential backoff
- Sync wordt gepauzeerd tijdens actieve user edits (debounce 30 sec)

### Bidirectionele Sync

Wanneer Machine A een sync initieert (auto of manual):

```
Machine A                                Machine B
─────────                                ─────────

1. GET /api/sync/manifest ──────────────►
   ◄────────────────────── manifest B

2. Compare manifests
   - A has newer: mark PUSH
   - B has newer: mark PULL

3. GET /api/sync/pull?uuids=... ────────►
   ◄────────────────────── entities from B

4. POST /api/sync/push ─────────────────►
   entities from A ──────►
   ◄────────────────────── accepted/conflicts

5. Both databases now in sync
```

**Belangrijk:** Het maakt niet uit welke machine de sync initieert - het resultaat is identiek. Slechts één machine hoeft auto-sync aan te hebben.

### Conflict Notificatie

Bij automatische conflict resolution (last-write-wins) wordt de gebruiker geïnformeerd:

```
┌─────────────────────────────────────────────────────────────────┐
│  ⚠ Sync Conflict Resolved                              [×]     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Project "My App" was modified on both machines.               │
│                                                                 │
│  Winner: ubuntu-server (2026-01-19 11:30)                      │
│  Your version from 11:25 was overwritten.                      │
│                                                                 │
│  [View Changes]  [Dismiss]                                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Conflict log:** Alle conflicten worden gelogd in `sync_conflict_log` voor audit.

### Herstel na Ongewenst Conflict

Als LWW de verkeerde versie kiest, kan de user herstellen:

| Methode | Beschrijving |
|---------|--------------|
| **Manual edit** | Pas entity aan op winnende machine, sync opnieuw |
| **Conflict log** | Bekijk `sync_conflict_log.local_snapshot` voor verloren data |
| **Re-sync** | Pas entity aan, verhoog `updated_at`, sync opnieuw |

**MVP scope:** Automatische revert is post-MVP. Voor MVP volstaat manual edit + re-sync.

---

## Implementatie Fases

### Fase 1: Database Migraties (Week 1)
- UUID kolommen toevoegen aan alle entiteiten
- `deleted_at` toevoegen waar nodig
- `sync_state` en `sync_log` tabellen
- Bestaande records vullen met UUIDs

### Fase 2: API Endpoints (Week 2-3)
- `/api/sync/manifest` endpoint
- `/api/sync/pull` endpoint
- `/api/sync/push` endpoint
- Bearer token authenticatie
- Rate limiting

### Fase 3: Sync Service (Week 3-4)
- `SyncService` class voor business logic
- Conflict resolution implementatie
- Cascade sync logic
- Path mapping (optioneel)

### Fase 4: UI Integration (Week 4-5)
- Sync status widget
- Manual sync trigger
- Conflict resolution UI
- Sync log viewer

### Fase 5: Testing & Polish (Week 5-6)
- Unit tests voor sync logic
- Integration tests voor API
- End-to-end sync tests
- Documentation

---

## Geschatte Effort

| Component | Uren | Notes |
|-----------|------|-------|
| Database migraties | 8-12 | UUID, deleted_at, sync tabellen |
| API endpoints | 20-30 | manifest, pull, push, auth |
| SyncService | 30-40 | Core sync logic, conflicts |
| Path mapping | 10-15 | Optioneel |
| UI components | 15-20 | Status, trigger, conflicts |
| Tests | 20-25 | Unit, integration, e2e |
| Documentation | 5-10 | API docs, user guide |
| **Totaal** | **108-152 uur** | ~3-4 weken |

---

## Risico's en Mitigaties

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| Clock skew tussen machines | Verkeerde conflict resolution | NTP sync vereisen + 5 sec grace period (S7) |
| Grote datasets | Lange sync tijd | Incremental sync (post-MVP), pagination |
| Network interruption mid-sync | Inconsistente state | Transactional sync, rollback capability (zie Failure Handling) |
| UUID collision | Data corruption | UUIDv4 heeft <10^-37 collision kans |
| Concurrent edits during sync | Race conditions | Optimistic locking via updated_at check |
| API versie mismatch | Sync faalt | Handshake controleert api_version (S8) |
| Peer niet bereikbaar | Sync faalt | Exponential backoff retry, user notificatie |

---

## Failure Handling & Rollback

### Sync Transacties

Elke sync operatie is atomair per fase:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    SYNC FAILURE HANDLING                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  FASE 1: Manifest ophalen                                           │
│  ─────────────────────────                                          │
│  Fout: Netwerk timeout, auth failure                                │
│  Actie: Abort sync, log error, retry later                          │
│  Rollback: Niet nodig (geen wijzigingen)                            │
│                                                                     │
│  FASE 2: Pull van remote                                            │
│  ───────────────────────                                            │
│  Fout: Partial response, invalid data                               │
│  Actie: Abort sync, log error                                       │
│  Rollback: Discard pulled data (niet gecommit)                      │
│  Integriteit: Pull gebruikt database transactie                     │
│                                                                     │
│  FASE 3: Push naar remote                                           │
│  ────────────────────────                                           │
│  Fout: Remote rejects push, netwerk error mid-push                  │
│  Actie: Log partial success, schedule retry                         │
│  Rollback: Remote doet rollback van partial push                    │
│  Integriteit: Push wrapped in transactie op remote                  │
│                                                                     │
│  FASE 4: Sync state update                                          │
│  ─────────────────────────                                          │
│  Fout: Database error                                               │
│  Actie: Log warning, sync is wel geslaagd                           │
│  Impact: Volgende sync ziet geen last_sync_at update                │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Transactie Scope

```php
// Pull transactie (lokaal)
$transaction = Yii::$app->db->beginTransaction();
try {
    foreach ($pulledEntities as $entity) {
        $this->importEntity($entity);
    }
    $transaction->commit();
} catch (Exception $e) {
    $transaction->rollBack();
    throw new SyncPullException($e->getMessage());
}

// Push transactie (remote)
// Remote endpoint wraps ontvangen entities in transactie
```

### Retry Policy

| Fout Type | Retry | Backoff |
|-----------|-------|---------|
| Network timeout | Ja | 1m → 2m → 5m → 15m |
| Auth failure (401) | Nee | - (credentials invalid) |
| Server error (5xx) | Ja | 1m → 2m → 5m → 15m |
| Validation error (422) | Nee | - (data invalid, log voor debug) |
| Version mismatch | Nee | - (upgrade vereist) |

### Sync Log bij Failure

```sql
INSERT INTO sync_log (
    peer_id,
    sync_started_at,
    sync_completed_at,
    status,
    error_message,
    entities_pushed,
    entities_pulled
) VALUES (
    'peer-uuid',
    '2026-01-19 12:00:00',
    '2026-01-19 12:00:05',
    'failed',
    'Network timeout during push phase',
    0,
    15  -- pull succeeded before failure
);
```

### Partial Success Handling

Als pull slaagt maar push faalt:

1. Lokale database is bijgewerkt met remote wijzigingen
2. Remote database mist lokale wijzigingen
3. Volgende sync zal lokale wijzigingen alsnog pushen
4. **Geen data verlies** - alleen tijdelijke inconsistentie

---

## Besluitpunten (Nieuw)

| ID | Besluitpunt | Opties | Besluit | Datum |
|----|-------------|--------|---------|-------|
| S1 | Conflict resolution | A: Last-write-wins (auto) / B: User kiest | **A: Automatisch** | 2026-01-19 |
| S2 | File path handling | A: Path mapping / B: Exclude file fields | **B: Exclude** | 2026-01-19 |
| S3 | Sync trigger | A: Manual only / B: Auto + manual | **B: Auto + manual** | 2026-01-19 |
| S4 | Soft deletes | A: Alle entiteiten / B: Alleen project | **A: Alle entiteiten** | 2026-01-19 |
| S5 | User ID mapping | A: Sync user_id / B: Vervang bij import | **B: Vervang bij import** | 2026-01-19 |
| S6 | Peer model | A: Volledig P2P / B: Master peer | **A: Volledig P2P** | 2026-01-19 |
| S7 | Clock skew mitigatie | A: NTP vereist / B: Grace period | **B: Grace period (5 sec)** | 2026-01-19 |
| S8 | API versioning | A: URL versioning / B: Header versioning | **A: URL versioning** | 2026-01-19 |
| S9 | Quill Delta limiet | A: Geen limiet / B: Met limiet | **B: 10MB hard limit, 5MB warning** | 2026-01-19 |

### S1: Conflict Resolution - Last-Write-Wins (Automatisch)

Bij conflicten wint automatisch de nieuwste `updated_at` timestamp. Geen user interactie nodig.

### S2: File Field Values Excluded

Field entiteiten met type `file`, `directory`, of `file_list` worden **wel gesynct**, maar het `value` veld wordt op `null` gezet bij sync. Dit voorkomt cross-platform path problemen.

**Gedrag per veld:**

| Veld | Gesynct? |
|------|----------|
| Field.name | ✅ Ja |
| Field.label | ✅ Ja |
| Field.type | ✅ Ja |
| Field.value (type=file) | ❌ Nee → null |
| Field.value (type=directory) | ❌ Nee → null |
| Field.value (type=file_list) | ❌ Nee → null |
| Field.value (andere types) | ✅ Ja |

**Rationale:** De Field-definitie (naam, type) is relevant voor beide machines, maar de waarde (pad naar bestand) is machine-specifiek.

### S3: Auto + Manual Sync

- **Manual:** Gebruiker kan altijd sync triggeren via UI knop
- **Auto:** Periodieke sync op achtergrond (configureerbaar interval, default: 15 minuten)

### S5: User ID Vervangen bij Import

Bij import wordt `user_id` altijd vervangen door de lokale user_id. Zie sectie "User & Ownership Scope" voor details.

### S6: Volledig P2P (Geen Master)

- Beide peers zijn gelijkwaardig
- Beide kunnen sync initiëren
- Geen "source of truth" - beiden zijn authoritative voor hun eigen wijzigingen
- Conflicten worden opgelost via last-write-wins (S1)

### S7: Clock Skew - Grace Period

Om kleine tijdsverschillen tussen machines op te vangen:

```
if (abs(remote.updated_at - local.updated_at) < 5 seconds) {
    // Binnen grace period: gebruik UUID als tiebreaker
    winner = (remote.uuid > local.uuid) ? remote : local;
} else {
    // Buiten grace period: stricte timestamp vergelijking
    winner = (remote.updated_at > local.updated_at) ? remote : local;
}
```

**Aanbeveling:** Beide machines moeten NTP (network time protocol) gebruiken voor klok synchronisatie. Grace period is fallback, niet primaire oplossing.

### S8: API Versioning

**Versie-notatie:** Semantic versioning zonder prefix (e.g., `1.0`, `1.1`, `2.0`)

**Alle sync endpoints zijn geversioneerd:**

```
/api/sync/v1/handshake
/api/sync/v1/manifest
/api/sync/v1/pull
/api/sync/v1/push
```

Bij incompatibele API wijzigingen wordt major versie verhoogd (v2, v3, etc.).
Bij backwards-compatible wijzigingen wordt minor versie verhoogd (1.1, 1.2, etc.).

**Versie Compatibiliteit:**

| Local API | Remote API | Actie |
|-----------|------------|-------|
| 1.0 | 1.0 | Sync OK |
| 1.0 | 1.1 | Sync OK (minor compatible) |
| 1.0 | 2.0 | Weiger sync, toon upgrade melding |
| 2.0 | 1.0 | Weiger sync, toon downgrade melding voor remote |

**Handshake response bevat versie:**
```json
{
  "api_version": "1.0",
  "min_supported_version": "1.0"
}
```

### S9: Quill Delta Content Limiet

Voor MVP geldt een limiet op Quill Delta content (Context, PromptTemplate, ScratchPad):

| Drempel | Gedrag |
|---------|--------|
| < 5MB | Normaal sync |
| 5MB - 10MB | Sync met warning in log |
| > 10MB | Sync geweigerd, error response |

**Gedrag bij overschrijding:**

```json
{
  "success": false,
  "error": "PAYLOAD_TOO_LARGE",
  "message": "Entity ctx-uuid-1 exceeds 10MB limit (12.5MB)",
  "entity_uuid": "ctx-uuid-1",
  "entity_type": "context",
  "size_bytes": 13107200,
  "limit_bytes": 10485760
}
```

**Rationale:** 10MB Quill Delta is equivalent aan ~5 miljoen tekens plain text - ruim voldoende voor normale use cases. Grotere content wijst op misbruik of bug.

---

## Acceptance Criteria

### Must Have (MVP)

| AC ID | Criterium | Verificatie |
|-------|-----------|-------------|
| AC-S1 | Beide machines draaien onafhankelijk | Stop netwerk, CRUD werkt lokaal |
| AC-S2 | Manifest endpoint retourneert alle UUIDs | GET /api/sync/manifest response bevat alle entiteiten |
| AC-S3 | Pull haalt entities correct op | GET /api/sync/pull retourneert volledige data |
| AC-S4 | Push stuurt entities correct | POST /api/sync/push accepteert nieuwe entities |
| AC-S5 | Nieuwe entity synct correct | Create op A, sync, zichtbaar op B |
| AC-S6 | Gewijzigde entity synct correct | Update op A, sync, wijziging zichtbaar op B |
| AC-S7 | Verwijderde entity synct correct | Delete op A, sync, verwijderd op B |
| AC-S8 | Conflict resolution werkt | Edit op A én B, sync, last-write-wins |
| AC-S9 | Bearer token authenticatie | Sync zonder token faalt met 401 |
| AC-S20 | Peer onboarding werkt | Generate token op A, add peer op B, handshake slaagt |
| AC-S21 | API version check | Handshake met incompatibele versie faalt met duidelijke melding |
| AC-S22 | User ID mapping | Geïmporteerde entities krijgen lokale user_id |
| AC-S23 | Sync failure rollback | Bij push failure worden partial changes teruggedraaid |

### Should Have

| AC ID | Criterium | Verificatie |
|-------|-----------|-------------|
| AC-S10 | Sync log bijgehouden | sync_log tabel bevat entries na sync |
| AC-S12 | Sync status in UI | Gebruiker ziet laatste sync tijd |
| AC-S13 | Manual sync trigger | Knop in UI start sync |
| AC-S14 | Automatic sync | Periodieke sync (configureerbaar, default 15 min) |
| AC-S15 | File field values excluded | Field entity gesynct, value=null voor file/directory/file_list types |

### Could Have (Post-MVP)

| AC ID | Criterium | Verificatie |
|-------|-----------|-------------|
| AC-S11 | Delta sync (incremental) | Sync na initiële sync haalt alleen wijzigingen via `since` parameter |
| AC-S16 | Path mapping | File fields werken cross-platform (vervangt S2) |
| AC-S17 | Multi-peer sync | >2 machines kunnen syncen |
| AC-S18 | Selective sync | Sync alleen specifieke projecten |

---

## Test Scenarios

### Scenario 1: Initial Sync (Clean Slate)

```
Given: Machine A heeft 5 projecten, Machine B is leeg
When:  Machine B doet sync met Machine A
Then:  Machine B heeft dezelfde 5 projecten (met UUIDs)
```

### Scenario 2: Bi-directional New Entities

```
Given: Beide machines zijn in sync
When:  Machine A maakt Project X, Machine B maakt Project Y
And:   Sync wordt uitgevoerd
Then:  Beide machines hebben Project X én Project Y
```

### Scenario 3: Update Conflict (Last-Write-Wins)

```
Given: Beide machines hebben Project Z
When:  Machine A update Project Z om 10:00 (name = "Foo")
And:   Machine B update Project Z om 10:05 (name = "Bar")
And:   Sync wordt uitgevoerd
Then:  Beide machines hebben Project Z met name = "Bar"
```

### Scenario 4: Delete Sync

```
Given: Beide machines hebben Project W
When:  Machine A verwijdert Project W
And:   Sync wordt uitgevoerd
Then:  Machine B heeft Project W ook verwijderd (soft delete)
```

### Scenario 5: Delete vs Update Conflict

```
Given: Beide machines hebben Project V
When:  Machine A verwijdert Project V om 10:00
And:   Machine B update Project V om 10:05
And:   Sync wordt uitgevoerd
Then:  Project V is NIET verwijderd (update is nieuwer)
```

### Scenario 6: Offline Operation

```
Given: Machine A is offline (geen netwerk)
When:  Gebruiker doet CRUD operaties op Machine A
Then:  Alle operaties werken lokaal
And:   Wijzigingen worden gesynct wanneer weer online
```

### Scenario 7: Cascade Sync

```
Given: Machine A maakt nieuw Project met 3 Contexts en 2 Fields
When:  Sync wordt uitgevoerd
Then:  Machine B heeft Project + alle 3 Contexts + alle 2 Fields
```

### Edge Case Scenarios

#### Scenario 8: Clock Skew binnen Grace Period

```
Given: Machine A en B hebben 3 seconden clock skew
And:   Beide machines updaten Project Z "tegelijk"
When:  A update om 10:00:00 (A's clock), B update om 10:00:02 (B's clock)
And:   Real time difference < 5 seconds
Then:  UUID tiebreaker bepaalt winnaar
And:   Beide machines krijgen dezelfde versie
```

#### Scenario 9: Linked Project Bestaat Niet

```
Given: Machine A heeft Project X linked to Project Y
And:   Machine B heeft alleen Project X (niet Y)
When:  Sync wordt uitgevoerd
Then:  ProjectLinkedProject record wordt NIET gecreëerd op B
And:   Warning wordt gelogd
And:   Bij volgende sync (na Project Y sync) wordt link gecreëerd
```

#### Scenario 10: Sync Failure Mid-Push

```
Given: Machine A pusht 10 entities naar B
And:   Netwerk faalt na entity 5
When:  B detecteert incomplete push
Then:  B doet rollback van alle 10 entities
And:   A krijgt error response
And:   A scheduled retry
And:   Volgende sync pusht alle 10 entities opnieuw
```

#### Scenario 11: Simultaneous Sync Attempts

```
Given: Machine A start sync naar B
And:   Machine B start tegelijk sync naar A
When:  Beide manifest requests arriveren
Then:  Één sync krijgt lock (first-come-first-served)
And:   Andere krijgt 423 Locked response
And:   Locked request retry na korte delay
```

#### Scenario 12: File Field Exclusion

```
Given: Machine A heeft Field met type="file" en value="/home/user/doc.pdf"
When:  Sync wordt uitgevoerd
Then:  Field entity wordt gesynct ZONDER value
And:   Machine B ziet Field met value=null
And:   User moet file path lokaal instellen
```

#### Scenario 13: API Version Mismatch

```
Given: Machine A draait API v1
And:   Machine B is geüpgraded naar API v2
When:  Machine A probeert sync met B
Then:  Handshake faalt met version mismatch error
And:   User ziet melding: "Peer requires upgrade to v2"
And:   Sync wordt niet uitgevoerd
```

#### Scenario 14: Restore After Delete

```
Given: Machine A verwijdert Project W (soft delete)
And:   Sync propageert delete naar B
And:   Later: Machine A herstelt Project W (deleted_at = null)
When:  Sync wordt uitgevoerd
Then:  Project W wordt hersteld op B
And:   Alle children blijven intact (waren niet echt verwijderd)
```

#### Scenario 15: Large Quill Delta Content (binnen limiet)

```
Given: Context heeft Quill Delta content van 7MB
When:  Sync wordt uitgevoerd
Then:  Content wordt volledig overgedragen
And:   Warning wordt gelogd (>5MB threshold)
And:   Sync slaagt
```

#### Scenario 15b: Quill Delta Content Overschrijdt Limiet

```
Given: Context heeft Quill Delta content van 12MB
When:  Sync wordt uitgevoerd
Then:  Sync voor deze entity wordt geweigerd
And:   Error PAYLOAD_TOO_LARGE wordt geretourneerd
And:   Andere entities worden wel gesynct
```

#### Scenario 16: ProjectLinkedProject Delete vs Present

```
Given: Machine A en B hebben link tussen Project X en Y
When:  Machine A verwijdert link om 10:00 (soft delete)
And:   Machine B heeft link nog (niet gewijzigd sinds 09:00)
And:   Sync wordt uitgevoerd
Then:  Link wordt verwijderd op B (delete is nieuwer)
```

#### Scenario 17: ProjectLinkedProject Present vs Delete (omgekeerd)

```
Given: Machine A en B hebben link tussen Project X en Y
When:  Machine A verwijdert link om 10:00 (soft delete)
And:   Machine B wijzigt link label om 10:05
And:   Sync wordt uitgevoerd
Then:  Link blijft bestaan op beide (update is nieuwer dan delete)
And:   Machine A herstelt link met nieuwe label
```

#### Scenario 18: Peer Onboarding Success (AC-S20)

```
Given: Machine A draait PromptManager
And:   Machine B draait PromptManager (nog niet verbonden)
When:  User genereert peer token op Machine A
And:   User voert token en URL in op Machine B
And:   Machine B stuurt handshake request naar A
Then:  Handshake slaagt (200 OK)
And:   Machine A ontvangt B's peer credentials
And:   sync_peer record bestaat op beide machines
And:   Sync kan worden uitgevoerd
```

#### Scenario 18b: Peer Onboarding Invalid Token

```
Given: Machine A draait PromptManager
When:  Machine B stuurt handshake met ongeldig token
Then:  Handshake faalt met 401 Unauthorized
And:   Geen sync_peer record wordt aangemaakt
```

#### Scenario 18c: Peer Onboarding Version Mismatch

```
Given: Machine A draait API v1.0
And:   Machine B draait API v2.0
When:  Machine B stuurt handshake naar A
Then:  Handshake faalt met version mismatch
And:   Response bevat: required_version en current_version
And:   User ziet upgrade/downgrade melding
```

#### Scenario 19: User ID Mapping (AC-S22)

```
Given: Machine A heeft user_id=1 (Alice)
And:   Machine B heeft user_id=1 (ook Alice, zelfde persoon)
And:   Machine A heeft Project X met user_id=1
When:  Sync wordt uitgevoerd naar Machine B
Then:  Project X wordt aangemaakt op B met user_id=1 (B's lokale user)
And:   Ownership blijft bij lokale user
```

#### Scenario 19b: User ID Mapping met Verschillende IDs

```
Given: Machine A heeft user_id=1
And:   Machine B heeft user_id=5 (andere auto-increment)
And:   Machine A heeft Project X met user_id=1
When:  Sync wordt uitgevoerd naar Machine B
Then:  Project X wordt aangemaakt op B met user_id=5 (B's lokale user)
And:   Originele user_id=1 wordt NIET bewaard
```

---

## Vergelijking met Originele Architectuur

| Aspect | Centrale Webapplicatie | Bi-directional Sync |
|--------|------------------------|---------------------|
| **Offline werken** | Niet mogelijk | Volledig ondersteund |
| **Data consistentie** | Gegarandeerd (single SoR) | Eventual consistency |
| **Complexiteit** | Laag | Medium-Hoog |
| **Client vereisten** | Alleen browser | Volledige Docker stack |
| **Conflict handling** | Geen (single writer) | Last-write-wins |
| **Setup tijd client** | 2 minuten | 30+ minuten |
| **File fields** | Werken niet (server-side) | Werken lokaal |
| **Bandbreedte** | Continu (elke request) | Alleen bij sync |
| **Single point of failure** | Ja (centrale server) | Nee (beide standalone) |

---

## Migratie van Bestaande Data

### Stap 1: UUID Toekennen

Bij migratie worden UUIDs toegekend aan bestaande records:

```php
// Migration: m260119_000001_add_uuid_columns.php
public function safeUp()
{
    // Voeg UUID kolom toe
    $this->addColumn('project', 'uuid', $this->char(36)->notNull()->after('id'));

    // Genereer UUIDs voor bestaande records
    $projects = (new Query())->from('project')->all();
    foreach ($projects as $project) {
        $this->update('project',
            ['uuid' => Uuid::uuid4()->toString()],
            ['id' => $project['id']]
        );
    }

    // Voeg unique index toe
    $this->createIndex('idx_project_uuid', 'project', 'uuid', true);
}
```

### Stap 2: Initiële Sync

Na migratie op beide machines:

1. Kies één machine als "primary" voor initiële data
2. **BELANGRIJK:** Secondary machine moet starten met LEGE database (alleen user tabel)
   - Kopieer GEEN database bestanden handmatig tussen machines
   - Handmatig kopiëren veroorzaakt verschillende UUIDs voor dezelfde data → duplicatie
3. Voer sync uit: secondary haalt alles van primary
4. Controleer data integriteit
5. Beide machines zijn nu peers

### Stap 3: Normale Operatie

Na initiële sync:
- Beide machines werken onafhankelijk
- Periodieke sync (handmatig of automatisch)
- Geen "primary" meer - volledig peer-to-peer

---

## Glossary (Aanvulling)

| Term | Definitie |
|------|-----------|
| **UUID** | Universally Unique Identifier - globally unieke ID voor sync |
| **Manifest** | Lijst van alle UUIDs met timestamps voor sync vergelijking |
| **Peer** | Een PromptManager instantie die deelneemt aan sync |
| **Pull** | Data ophalen van remote peer |
| **Push** | Data versturen naar remote peer |
| **Last-write-wins** | Conflict resolution waarbij nieuwste timestamp wint |
| **Soft delete** | Record markeren als verwijderd (deleted_at) i.p.v. echt verwijderen |
| **Eventual consistency** | Data is uiteindelijk consistent na sync, niet real-time |
| **file_list** | Field type voor meerdere bestandspaden (array van paden), machine-specifiek |
| **Quill Delta** | JSON formaat voor rich text content (contexten, templates, scratch pads) |

---

## Open Vragen

| # | Vraag | Impact | Status |
|---|-------|--------|--------|
| 1 | ~~Moet sync automatisch of alleen handmatig?~~ | UX, complexiteit | **Besloten: Auto + manual (S3)** |
| 2 | ~~Hoe om te gaan met grote Quill Delta content?~~ | Performance | **Besloten: 10MB limiet, warning bij >5MB** |
| 3 | ~~Moet er een "master" peer zijn of volledig P2P?~~ | Architectuur | **Besloten: Volledig P2P (S6)** |
| 4 | ~~Hoe vaak verwacht de gebruiker te syncen?~~ | UX, caching strategie | **Besloten: 15 min default** |
| 5 | Wat is het auto-sync interval minimum/maximum? | UX, performance | Open - TO fase |
| 6 | ~~Hoe clock skew mitigeren?~~ | Conflict resolution | **Besloten: Grace period 5 sec (S7)** |
| 7 | ~~Hoe user_id mappen tussen peers?~~ | Data ownership | **Besloten: Vervang bij import (S5)** |

