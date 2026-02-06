## Mappen

### Basisstructuur
```
./application/components/{subject}/*
./application/{module}/components/{subject}/*
```
Met als basis de Yii structuur.

### Naamgeving regels
- **Subject** is altijd enkelvoud (bijv. `mailer`, `sampleData`)
- **Type** is altijd meervoud, behalve: `query`

### Models structuur
We beschouwen 'models' als een eigen component:
```
./models/                    # ActiveRecord modellen
./models/query/              # Query classes (enkelvoud!)
./models/behaviors/          # Behaviors
./models/forms/              # Form models
./models/enums/              # PHP Native enums
```

### Components structuur
Indien er genoeg classes zijn om een type te bundelen, bundle deze per type:
./components/{subject}/{type}
```
./components/{subject}/
./components/{subject}/service/      # Services binnen een component
./components/{subject}/providers/    # Data providers binnen een component
```

Voorbeeld uit de codebase - `sampleData` component:
```
./components/sampleData/
./components/sampleData/service/
./components/sampleData/providers/
```

### Module structuur voorbeeld
```
./modules/{moduleName}/
./modules/{moduleName}/controllers/
./modules/{moduleName}/views/
./modules/{moduleName}/models/
./modules/{moduleName}/models/forms/
./modules/{moduleName}/models/search/
./modules/{moduleName}/components/
```

### Overige application-level mappen
```
./controllers/
./views/
./widgets/
./commands/        # Console commands
./config/
./db/migrations/
./rbac/
./rbac/rules/
./mail/            # Mail templates
./mail/layouts/    # Mail layouts
./assets/
./assets/scss/
./assets/public/
```

### Referenties
- [Symfony dir structure](https://symfony.com/doc/current/best_practices.html#use-the-default-directory-structure)
- [Laravel dir structure](https://laravel.com/docs/12.x/structure#the-app-directory)
- [Yii2 dir structure](https://www.yiiframework.com/extension/yiisoft/yii2-app-advanced/doc/guide/2.0/en/structure-directories)
- [Wikipedia software design patterns](https://en.wikipedia.org/wiki/Software_design_pattern#Examples)


### Class naamgeving

**Mapnaam vs class suffix:**
- **Mapnaam**: meervoud (`builders/`, `services/`, `forms/`)
- **Class suffix**: enkelvoud (`SchoolBuilder`, `StringHelper`, `LoginForm`)

**Uitzondering** - geen type suffix voor:
- Model, ValueObject, DTO

Voorbeelden: `School` (niet `SchoolModel`), `SchoolStatus` (niet `SchoolStatusEnum`)

De rest wel een suffix, bijv: `SchoolSearch`, `DepartmentBuilder`, `MailService`.

---

### Types

#### A
- **Action**: Yii2 standalone action class, voor herbruikbare controller actions
- **Adapter**: Converteert een object interface naar een andere interface (structureel). Gebruik Transformer voor data conversie.

#### B
- **Behavior**: Yii2 behavior voor herbruikbare model/component functionaliteit (bijv. `TimestampBehavior`, `BlameableBehavior`)
- **Builder**: Bouwt complexe objecten stap voor stap op met fluent interface. Gebruik voor objecten met veel optionele parameters. (bijv. `SchoolBuilder`, `StudentBuilder`)

#### C
- **Client**: Communiceert met externe services (API's, webservices). Bevat HTTP calls en response handling.
- **Command**: Yii2 console command voor CLI taken (bijv. `MigrateCommand`, `CronCommand`)
- **Controller**: Handelt HTTP requests af en coördineert response. Bevat geen business logic.

#### E
- **Enum**: PHP 8.1+ native enum voor vaste waardensets (bijv. `SchoolStatus`, `TestSessionStatus`)
- **Exception**: Custom exception classes voor specifieke foutafhandeling

#### F
- **Factory**: Creëert objecten met standaard/verplichte waarden. Gebruik voor eenvoudige object creatie; gebruik Builder voor complexe objecten.
- **Filter**: Filtert of transformeert data in een pipeline (bijv. input filtering, output filtering)
- **Fixture**: Test data voor unit/integration tests
- **Form**: Form model voor validatie en data binding (bijv. `LoginForm`, `CreateSchoolForm`)

#### H
- **Handler**: Handelt business logic af direct vanuit een controller.
- **Helper**: Stateless utility functies, vaak static methods (bijv. `StringHelper`, `ArrayHelper`)

#### I
- **Interface**: PHP interface voor contracten tussen classes (bijv. `LabeledEnumInterface`)

#### J
- **Job**: Data envelope voor queue jobs. Bevat alle data die nodig is om een taak uit te voeren.

#### M
- **Migration**: Database schema wijzigingen (in `db/migrations/`)
- **Model**: ActiveRecord model, representeert een database tabel (bijv. `School`, `Student`)
- **Module**: Yii2 module, een zelfstandige sub-applicatie (bijv. `admin`, `authentication`)

#### P
- **Provider**: Levert data aan andere componenten. Voorbeelden: `DataProvider` voor grids, `SampleDataProvider` voor test data.

#### Q
- **Query**: ActiveQuery class voor het bouwen van database queries. Bevat scopes en query methods, geen business logic. (bijv. `SchoolQuery`, `StudentQuery`)

#### S
- **Search**: Model voor GridView filtering met search attributes en `search()` method (bijv. `SchoolSearch`)
- **Serializer**: Converteert objecten naar/van serialized formaten (JSON, XML, etc.)

#### T
- **Trait**: PHP trait voor herbruikbare method implementaties (bijv. `LabeledEnumTrait`)
- **Transformer**: Converteert data van het ene formaat naar het andere (bijv. array naar DTO, API response naar Model)

#### V
- **Validator**: Custom validatie regel voor form/model validation

#### W
- **Widget**: Herbruikbare UI component met eigen rendering logic (bijv. `Alert`, `SearchForm`)
