## Opdracht

- **FEATURE**: PRJ:{{Feature}}
- **SPEC**: `.claude/design/[FEATURE]/spec.md`
- **AGENT_MEMORY**: `.claude/design/[FEATURE]/implementation`

Implementeer  [FEATURE] volgens [SPEC].

## Before you start

1. **Lees** [SPEC] volledig
2. Als [AGENT_MEMORY] **niet** bestaat:
    1. **Maak** directory [AGENT_MEMORY] aan
    2. **Maak** memory files:
    - `context.md` (goal, scope, key references)
    - `todos.md` (stappen — één per class of testfile)
    - `insights.md` (beslissingen, bevindingen, pitfalls)
3. Als [AGENT_MEMORY] **wel** bestaat:
    - Lees `todos.md` en `insights.md` volledig
    - Ga verder met de eerste niet-afgevinkte stap

## 

## Per stap

1. Lees de relevante sectie uit de spec
2. Lees referentiebestanden indien genoemd
3. Implementeer (naming, folders, patterns uit spec)
4. Bij twijfel: volg bestaande codebase conventions
5. Bij blokkade: STOP, noteer in insights.md, presenteer blokkade:

Blokkade oplossen / Stap overslaan / Stoppen?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**
6. Vink af in todos.md **voordat** je aan de volgende stap begint
7. Bij onverwacht obstakel: documenteer als pitfall in insights.md

## Termination

Wanneer alle stappen afgevinkt:

1. Run linter (0 issues)
2. Run alle unit tests (0 errors, 0 failures)
3. Fix issues tot groen
4. Noteer eindresultaat in insights.md
5. Geef samenvatting: gewijzigde bestanden, testresultaat, open issues

Commit wijzigingen / Review wijzigingen / Aanpassen?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**