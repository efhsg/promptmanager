# Continuation Prompt: Tailscale Centralization

## Context

We hebben de Tailscale centralisatie voor PromptManager afgerond. Lees dit document en de gerelateerde bestanden om de huidige status te begrijpen.

## Wat is gedaan

1. **Server (zenbook)**: Tailscale, Docker, UFW geconfigureerd
2. **Docker hardening**: NGINX dual-binding (localhost + Tailscale IP), MySQL localhost-only
3. **Client (Windows 11)**: Tailscale in WSL, port forwarding naar Windows
4. **Documentatie**: `.claude/client-setup-windows.md` aangemaakt
5. **Commit**: `3783116` - "ADD: Tailscale centralization with Windows WSL client setup"

## Huidige Setup

| Component | Status | Toegang |
|-----------|--------|---------|
| zenbook (centrale server) | Operationeel | `http://100.104.97.118:8503` |
| Windows 11 WSL | Tailscale connected | Via port forwarding `localhost:8503` |
| Lokale WSL PromptManager | Blijft actief | Voor file fields, offline, development |

## Relevante Bestanden

- `.claude/design/fo/tailscale-webapp-central.md` - Functioneel ontwerp
- `.claude/design/to/tailscale-webapp-central.md` - Technisch ontwerp
- `.claude/client-setup-windows.md` - Windows client setup guide
- `docker-compose.yml` - Docker configuratie met Tailscale binding

## Architectuur

```
Windows browser → localhost:8503 → port forwarding → WSL → Tailscale → zenbook (100.104.97.118:8503)
```

Tailscale draait alleen in WSL, niet op Windows zelf.

## Open Punten

### 1. Database synchronisatie
Sync tussen lokale WSL en centrale server (zenbook) is nog niet geïmplementeerd.

Huidige situatie:
- Twee onafhankelijke databases (lokaal + centraal)
- Geen automatische sync
- Handmatig exporteren/importeren indien nodig

Te bespreken:
1. Is sync nodig? Of is één bron leidend?
2. Indien sync gewenst: richting? (lokaal → centraal, centraal → lokaal, bidirectioneel)
3. Frequentie? (on-demand, dagelijks, real-time)
4. Conflict resolutie strategie

## Prompt om verder te gaan

```
Lees .claude/continue-tailscale-setup.md voor de status van de Tailscale centralisatie. Database sync tussen lokaal en centraal is nog open. Bespreek de sync requirements.
```
