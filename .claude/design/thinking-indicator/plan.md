# Thinking Indicator — UX Optimalisatie

## Probleem

De huidige "thinking" indicatie is subtiel en onvoldoende herkenbaar voor nieuwe gebruikers:

1. **Drie grijze dots** (`#6c757d`, 6×6px) met een pulse-animatie — te klein, te weinig contrast met de omliggende UI
2. **Geen tekstueel label** — de dots verschijnen naast "Claude" zonder uitleg wat er gebeurt
3. **Geen visuele hiërarchie** — het stream-preview blok heeft een lichtgrijze achtergrond (`#f8f9fa`) die nauwelijks opvalt tussen andere UI-elementen
4. **Geen tijdsindicatie** — gebruiker heeft geen idee hoe lang het proces al loopt

## Huidige implementatie

### Stream-preview header (compact box)
```
┌──────────────────────────────────────┐
│ ⬛ Claude ● ● ●              [Stop] ⤢│
│ (preview body - 5 regels)            │
└──────────────────────────────────────┘
```

- Dots: `6px` cirkel, kleur `#6c757d`, animatie `claude-dot-pulse` (1.4s cycle)
- Header: `font-size: 0.85rem`, kleur `#495057`
- Box: `background: #f8f9fa`, `border: 1px solid #0d6efd` met `border-left: 3px`

### Modal header
```
┌──────────────────────────────────────┐
│ ⬛ Claude Process ● ● ●   [Stop] [×]│
│ ...                                  │
└──────────────────────────────────────┘
```

- Zelfde dots als preview
- Titel: "Claude Process"

## Voorstel

### 1. Vergroot de dots en voeg kleur toe

**Wat:** Vergroot dots van 6px naar 8px. Verander kleur van grijs (`#6c757d`) naar blauw (`#0d6efd`) — dezelfde kleur als de border, waardoor de animatie visueel verbonden is met de actieve staat.

**Waarom:** Grotere dots zijn beter scanbaar. Blauw signaleert "actief/bezig" (consistent met de blauwe border die al "streaming" aangeeft).

**CSS wijzigingen:**
```css
.claude-thinking-dots span {
    width: 8px;
    height: 8px;
    background: #0d6efd;       /* was: #6c757d */
}
```

### 2. Voeg een statuslabel toe

**Wat:** Voeg een geanimeerd label toe naast de dots: "Thinking…" dat pulst. Dit label verdwijnt zodra de eerste content-delta binnenkomt en wordt vervangen door "Responding…".

**Waarom:** Tekst is universeel begrijpbaar. De statuswisseling geeft feedback over de fase van het proces.

**HTML wijziging in preview:**
```
Claude ● ● ● Thinking…    →    Claude ● ● ● Responding…
```

**HTML wijziging in modal:**
```
Claude Process ● ● ● Thinking…    →    Claude Process ● ● ● Responding…
```

**CSS toevoeging:**
```css
.claude-thinking-dots__label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #0d6efd;
    animation: claude-label-pulse 2s infinite ease-in-out;
}

@keyframes claude-label-pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}
```

**JS wijziging:** In `renderStreamContent()`, bij de eerste `text`-delta, update het label van "Thinking…" naar "Responding…".

### 3. Voeg een elapsed-timer toe

**Wat:** Toon een subtiele timer rechts in de header: `0:03`, `0:15`, `1:02`. Start bij submit, stopt bij stream-end.

**Waarom:** Geeft de gebruiker een referentiepunt. Voorkomt het gevoel dat de UI bevroren is. Bij langere requests is dit essentieel — de gebruiker ziet dat er iets loopt.

**Plaatsing:**
```
⬛ Claude ● ● ● Thinking…          0:05  [Stop] ⤢
```

**CSS:**
```css
.claude-stream-timer {
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    color: #adb5bd;
    margin-left: auto;
}
```

**JS:** `setInterval` die elke seconde update. Opslaan in `this.streamTimerInterval`, opruimen in `cleanupStreamUI()`.

### 4. Voeg een pulse-ring toe aan de preview-box border

**Wat:** Voeg een subtiele, herhalende box-shadow pulse toe aan de stream-preview container zolang er gestreamd wordt.

**Waarom:** Trekt de aandacht naar het actieve element zonder opdringerig te zijn. Vergelijkbaar met de bestaande `claude-response-flash`, maar dan herhalend en zachter.

**CSS:**
```css
.claude-stream-preview--active {
    animation: claude-stream-pulse 2.5s infinite ease-in-out;
}

@keyframes claude-stream-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
    50% { box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12); }
}

@media (prefers-reduced-motion: reduce) {
    .claude-stream-preview--active {
        animation: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.12);
    }
}
```

**JS:** Voeg class `claude-stream-preview--active` toe bij start, verwijder bij end.

## Wat we NIET doen

- **Geen skeleton screens** — het preview-blok toont al echte streaming content
- **Geen progress bar** — we weten niet hoe lang de response duurt
- **Geen geluid of browser-notificatie** — te opdringerig voor een in-page interactie
- **Geen grote spinners** — past niet bij de compacte chat-UI

## Impactanalyse

### Bestanden die wijzigen

| Bestand | Wijziging |
|---------|-----------|
| `yii/web/css/claude-chat.css` | Dots groter + blauw, statuslabel, timer, pulse-ring |
| `yii/views/claude/index.php` | Label-element in preview en modal, timer-logica, statuswisseling |

### Bestaand gedrag dat bewaard blijft

- Klikken op preview opent modal — ongewijzigd
- Cancel/Stop knop — ongewijzigd
- Stream-preview body met Quill rendering — ongewijzigd
- Dots verdwijnen bij stream-end (`removeStreamDots()`) — uitbreiden met label + timer cleanup
- `prefers-reduced-motion` — respecteren voor alle nieuwe animaties

## Volgorde van implementatie

1. CSS: dots vergroten + kleur wijzigen
2. CSS: statuslabel stijl + animatie
3. CSS: timer stijl
4. CSS: pulse-ring op preview-box
5. JS: label-element toevoegen in `renderStreamingPlaceholderInto()`
6. JS: label-element toevoegen in modal HTML
7. JS: statuswisseling "Thinking…" → "Responding…" in `renderStreamContent()`
8. JS: timer starten bij stream-start, stoppen bij stream-end
9. JS: `claude-stream-preview--active` class toggle
10. Cleanup: alle nieuwe elementen/intervals opruimen in `cleanupStreamUI()`
