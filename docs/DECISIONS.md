# Architekturentscheidungen

Entscheidungen, die sich nicht aus dem Code ableiten lassen — vor allem solche,
bei denen die naheliegende Alternative bewusst verworfen wurde. Wer eine dieser
Stellen anfasst und den Grund nicht kennt, dreht sie erfahrungsgemäß zurück.

Neue Einträge oben anfügen, Format beibehalten, nichts löschen: ein revidierter
Eintrag bekommt einen Nachtrag, keine Streichung.

---

## 2026-07-30 — Kein zweiter CSRF-Listener im CMS

**Entscheidung:** Das CMS verlässt sich vollständig auf den `CsrfListener` aus
`nixphp/form`. Es gibt keine eigene CSRF-Prüfung, auch nicht ergänzend.

**Kontext:** Der Listener im Framework hat zwei bekannte Lücken:

- `vendor/nixphp/form/src/Events/CsrfListener.php:28` prüft nur `POST`, `PUT`
  und `DELETE` — `PATCH` fehlt.
- Zeile 33 überspringt die Prüfung komplett, sobald ein `Authorization`-Header
  vorhanden ist.

Beides ist für NixCMS heute folgenlos: es sind keine `PATCH`-Endpunkte
registriert, und die Authentifizierung läuft über Session-Cookies, nicht über
Bearer-Token.

**Warum nicht ergänzen:** Zwei CSRF-Prüfungen, die auseinanderlaufen können,
sind schlechter als eine dokumentierte Lücke. Der Fix gehört in das Paket, das
die Prüfung besitzt.

**Stattdessen:** `tests/Unit/Security/CsrfEndpointContractTest.php` verhindert,
dass NixCMS einen `PATCH`-Endpunkt registriert, solange das Framework ihn nicht
schützt. Der eigentliche Fix wird separat in `nixphp/form` gemacht.

**Status:** Framework-Fix steht aus.

---

## 2026-07-30 — `bin/nix-completion` bleibt entfernt

**Entscheidung:** Die Shell-Completion (276 Zeilen) wird nicht wiederhergestellt.

**Kontext:** Sie war Teil einer `dev/bootstrap.php`-Konstruktion, die es im
Repository nie gab (`git log --all -- dev/` ist leer). `bin/nix` selbst wurde
ebenfalls entfernt; die CI ruft jetzt `vendor/bin/nix` auf.

**Warum:** Shell-Completion für die NixPHP-CLI gehört ins CLI-Plugin, nicht in
ein CMS-Plugin. Dort wird sie später sauber neu aufgesetzt.

---

## 2026-07-30 — Statische Analyse mit Baseline statt Level-Absenkung

**Entscheidung:** PHPStan läuft auf Level 6. Bestehende Meldungen liegen in
`phpstan-baseline.neon` statt das Level abzusenken.

**Kontext:** Die Baseline enthält aktuell **232 Einträge**.

**Warum:** Ein niedrigeres Level würde auch neuen Code schwächer prüfen. Die
Baseline friert nur den Altbestand ein, während alles Neue gegen Level 6 läuft.

**Worauf zu achten ist:** Die Zahl darf nicht wachsen. Wer sie ändert,
aktualisiert sie hier. Eine Baseline, die niemand zählt, wird zum Mülleimer.

---

## 2026-07-30 — `ArticleRepository` bleibt vorerst bei ~990 Zeilen

**Entscheidung:** Die Datei wird nicht aufgeteilt, solange keine saubere
fachliche Trennung erkennbar ist.

**Kontext:** `src/Repositories/ArticleRepository.php` ist die größte PHP-Datei
des Projekts. Ein Schnitt nach Zuständigkeit (Artikel / Versionen / Listen-
und Filterabfragen) wäre denkbar, würde aber Methoden trennen, die sich denselben
Zustand und dieselben Hilfsmethoden teilen.

**Warum:** Eine Aufteilung allein nach Zeilenzahl erzeugt drei Klassen, die
sich gegenseitig aufrufen — das ist schlechter lesbar als eine große Klasse mit
klarer innerer Ordnung.

**Bedingung für eine Revision:** Sobald Artikel-Versionierung eigene Logik
bekommt, die über Lesen und Schreiben hinausgeht, wird `ArticleVersionRepository`
sinnvoll. Dann neu bewerten.

---

## 2026-07-30 — Überschreibbare Services laufen über Interfaces im Container

**Entscheidung:** Verhalten, das ein Host ersetzen können soll, wird als
Interface in `src/Contracts/` definiert, in `bootstrap.php` mit einer Closure
gebunden und von Konsumenten per Konstruktor bezogen.

**Aktuell überschreibbar** (siehe `tests/Unit/ServiceBindingTest.php`, das diese
Liste durchsetzt):

`AuditSinkInterface`, `MediaStorageInterface`, `OutboundHttpClientInterface`,
`PasswordPolicyInterface`, `RateLimiterInterface`, `SettingsServiceInterface`,
`ThemeSourceInterface`, `TranslationServiceInterface`,
`UserPrivilegePolicyInterface`.

**Warum Closures:** Der Container baut sie erst beim ersten Zugriff. Ein eager
gebautes Binding würde Dienste instanziieren, die ein Request nie anfasst — und
`nixphp/cms` bootet bewusst als letztes Plugin, verlässt sich also auf Dienste,
die zum Zeitpunkt der Registrierung noch nicht fertig sind.

**Warum Repositories einen nullbaren Konstruktor-Parameter haben:**
`UserRepository`, `RoleRepository` und `PermissionRepository` nehmen
`?UserPrivilegePolicyInterface $privilegePolicy = null` entgegen und fallen auf
`new UserPrivilegePolicy($this->pdo)` zurück. Autowiring liefert das
Container-Binding; der Fallback erhält die eigene Verbindung des Repositories,
was für Tests zählt, die es mit einem dedizierten PDO bauen.

**Nicht überschreibbar und vorerst absichtlich so:** die Registries
(`cms()->pages()`, `->api()`, `->blockTypes()`, `->contentTypes()`). Sie sind
über `add(..., $replace = true)` erweiterbar, was für ihren Zweck ausreicht.
