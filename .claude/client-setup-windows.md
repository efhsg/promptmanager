# PromptManager Client Setup - Windows 11 (WSL)

## Overzicht

- **Centrale server:** zenbook (100.104.97.118:8503)
- **Client:** Windows 11 PC met WSL
- **Architectuur:** WSL fungeert als Tailscale gateway voor Windows

## Netwerk Architectuur

```
Windows browser → localhost:8503 → port forwarding → WSL → Tailscale → zenbook
```

- Tailscale draait in WSL (niet op Windows)
- Port forwarding routeert Windows verkeer naar WSL
- WSL verbindt via Tailscale met de centrale server

## Stap 1: Tailscale Installeren in WSL

```bash
# In WSL terminal
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

Log in met je Tailscale account (zelfde als zenbook server).

## Stap 2: Verificatie in WSL

```bash
tailscale status
```

Verwacht resultaat: zenbook moet zichtbaar zijn in de lijst.

Test verbinding:

```bash
tailscale ping zenbook
curl -I http://100.104.97.118:8503
```

## Stap 3: Port Forwarding Configureren

Configureer port forwarding van Windows naar WSL zodat `localhost:8503` bereikbaar is vanuit Windows.

**Optie A: Windows PowerShell (Admin)**
```powershell
# Verkrijg WSL IP
wsl hostname -I

# Stel port forwarding in (vervang <WSL_IP> met het verkregen IP)
netsh interface portproxy add v4tov4 listenport=8503 listenaddress=127.0.0.1 connectport=8503 connectaddress=<WSL_IP>
```

**Optie B: SSH tunnel (in WSL)**
```bash
# Start SSH tunnel die Windows localhost:8503 doorstuurt naar zenbook
ssh -L 127.0.0.1:8503:100.104.97.118:8503 localhost -N
```

## Stap 4: PromptManager Openen via Windows Browser

Open browser naar:

```
http://localhost:8503
```

**Tip:** Maak een bookmark voor snelle toegang.

## Lokale WSL Installatie

De lokale PromptManager installatie in WSL blijft behouden voor:
- **File fields:** Toegang tot lokale bestanden
- **Offline werken:** Wanneer Tailscale/zenbook niet beschikbaar is
- **Development:** Lokale ontwikkeling en testen

Beide instanties kunnen parallel draaien.

## Troubleshooting

| Probleem | Oplossing |
|----------|-----------|
| `zenbook` niet gevonden in WSL | Gebruik IP: `100.104.97.118` |
| Connection timeout | Check `tailscale status` in WSL - moet "connected" zijn |
| Connection refused | Check of Docker draait op zenbook |
| localhost:8503 werkt niet op Windows | Check port forwarding configuratie |
| WSL IP veranderd | Herstart port forwarding met nieuw WSL IP |

## Server Gegevens

| Item | Waarde |
|------|--------|
| Server | zenbook |
| Tailscale IP | 100.104.97.118 |
| Poort | 8503 |
| Toegang vanuit WSL | http://100.104.97.118:8503 |
| Toegang vanuit Windows | http://localhost:8503 (via port forwarding) |
