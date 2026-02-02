# Plan: Context Usage Meter voor Claude Chat

## Doel

Visuele context meter met kleurindicatie (groen/oranje/rood) + waarschuwing bij >80%.
Cumulatieve tracking per sessie. Geen blokkering.

## Bestanden

| Bestand | Wijziging |
|---------|-----------|
| `yii/views/scratch-pad/claude.php` | HTML + JS: meter element, tracking state, update logica |
| `yii/web/css/claude-chat.css` | CSS: meter bar styling, kleurzones |

Geen backend wijzigingen — alle tokendata is al beschikbaar in de `actionRunClaude()` response.

## Token counting strategie

Bij `--continue` sessies bevat `input_tokens` van elke response al de volledige conversatiegeschiedenis.
Daarom: **de laatste response's `input_tokens + cache_tokens + output_tokens` = huidige totale context usage**.
We hoeven niet te sommeren over eerdere responses.

## Implementatiestappen

### Stap 1: CSS — meter styling

**Bestand:** `yii/web/css/claude-chat.css` (append aan einde)

```css
/* Context Usage Meter */
.claude-chat-page .claude-context-meter {
    padding: 0.25rem 0;
}

.claude-chat-page .claude-context-meter__bar-container {
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.claude-chat-page .claude-context-meter__fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.4s ease, background-color 0.3s ease;
}

.claude-chat-page .claude-context-meter__fill--green {
    background-color: #198754;
}

.claude-chat-page .claude-context-meter__fill--orange {
    background-color: #fd7e14;
}

.claude-chat-page .claude-context-meter__fill--red {
    background-color: #dc3545;
}

.claude-chat-page .claude-context-meter__label {
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: #6c757d;
    text-align: right;
}
```

### Stap 2: HTML — meter en warning elementen

**Bestand:** `yii/views/scratch-pad/claude.php`
**Locatie:** Na regel 160 (na prompt editor card `</div>`, voor `<!-- Section 3a -->`)

```html
<!-- Context Usage Meter -->
<div id="claude-context-meter-wrapper" class="claude-context-meter d-none mb-3">
    <div class="claude-context-meter__bar-container">
        <div id="claude-context-meter-fill" class="claude-context-meter__fill" style="width: 0%"></div>
    </div>
    <div class="claude-context-meter__label">
        <span id="claude-context-meter-text">0% context used</span>
    </div>
</div>

<!-- Context Warning -->
<div id="claude-context-warning" class="alert alert-warning alert-dismissible d-none mb-3" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <span id="claude-context-warning-text"></span>
    <small class="ms-1">Consider starting a new session to avoid degraded performance.</small>
    <button type="button" class="btn-close" id="claude-context-warning-close" aria-label="Close"></button>
</div>
```

### Stap 3: JS — properties toevoegen

**Locatie:** `claude.php:261` — na `historyCounter: 0,`

```javascript
contextHistory: [],
maxContext: 200000,
warningDismissed: false,
```

### Stap 4: JS — drie nieuwe methodes

**Locatie:** Op het `ClaudeChat` object, na `formatMeta()`

```javascript
getMaxContext: function(modelName) {
    if (!modelName) return 200000;
    var name = modelName.toLowerCase();
    var limits = { 'haiku': 200000, 'sonnet': 200000, 'opus': 200000 };
    for (var key in limits) {
        if (name.indexOf(key) !== -1) return limits[key];
    }
    return 200000;
},

updateContextMeter: function(pctUsed, totalUsed) {
    var wrapper = document.getElementById('claude-context-meter-wrapper');
    var fill = document.getElementById('claude-context-meter-fill');
    var text = document.getElementById('claude-context-meter-text');

    wrapper.classList.remove('d-none');
    fill.style.width = pctUsed + '%';

    fill.classList.remove(
        'claude-context-meter__fill--green',
        'claude-context-meter__fill--orange',
        'claude-context-meter__fill--red'
    );
    if (pctUsed < 60)
        fill.classList.add('claude-context-meter__fill--green');
    else if (pctUsed < 80)
        fill.classList.add('claude-context-meter__fill--orange');
    else
        fill.classList.add('claude-context-meter__fill--red');

    var fmt = function(n) { return n >= 1000 ? (n / 1000).toFixed(1) + 'k' : String(n || 0); };
    text.textContent = pctUsed + '% context used (' + fmt(totalUsed) + ' / ' + fmt(this.maxContext) + ' tokens)';
},

showContextWarning: function(pctUsed) {
    var warning = document.getElementById('claude-context-warning');
    var warningText = document.getElementById('claude-context-warning-text');
    warningText.textContent = 'Context usage is at ' + pctUsed + '%.';
    warning.classList.remove('d-none');
},
```

### Stap 5: JS — tracking in `send()` succes handler

**Locatie:** `claude.php:463` — na `self.messages.push(...)`

```javascript
// Context tracking
var inputTotal = (data.input_tokens || 0) + (data.cache_tokens || 0);
var outputTotal = data.output_tokens || 0;
var totalUsed = inputTotal + outputTotal;

if (data.model)
    self.maxContext = self.getMaxContext(data.model);

var pctUsed = Math.min(100, Math.round(totalUsed / self.maxContext * 100));

self.contextHistory.push({
    inputTokens: inputTotal,
    outputTokens: outputTotal,
    totalUsed: totalUsed,
    pctUsed: pctUsed
});

self.updateContextMeter(pctUsed, totalUsed);

if (pctUsed >= 80 && !self.warningDismissed)
    self.showContextWarning(pctUsed);
```

### Stap 6: JS — event listener warning dismiss

**Locatie:** `claude.php:341` — in `setupEventListeners()`, na bestaande listeners

```javascript
document.getElementById('claude-context-warning-close').addEventListener('click', function() {
    document.getElementById('claude-context-warning').classList.add('d-none');
    self.warningDismissed = true;
});
```

### Stap 7: JS — reset in `newSession()`

**Locatie:** `claude.php:739` — na `this.currentPromptText = null;`

```javascript
this.contextHistory = [];
this.maxContext = 200000;
this.warningDismissed = false;
document.getElementById('claude-context-meter-wrapper').classList.add('d-none');
document.getElementById('claude-context-meter-fill').style.width = '0%';
document.getElementById('claude-context-warning').classList.add('d-none');
```

### Stap 8: JS — `formatMeta()` aanpassen

**Locatie:** `claude.php:580`

```javascript
// Wijzig:
var maxContext = 200000;
// Naar:
var maxContext = this.maxContext || 200000;
```

## Verificatie

1. Start een nieuwe sessie en stuur een prompt → meter verschijnt groen, lage %
2. Stuur meerdere follow-up berichten → meter groeit, kleur verandert
3. Bij >80% → rode meter + warning alert verschijnt
4. Dismiss warning → verdwijnt, komt niet terug binnen dezelfde sessie
5. Klik "New Session" → meter verborgen, state gereset
6. Per-message meta toont nog steeds de individuele token info
7. `./linter.sh fix` — geen linting fouten
