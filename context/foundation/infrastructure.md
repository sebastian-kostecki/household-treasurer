---
project: household-treasurer (Domowy Skarbnik)
researched_at: 2026-11-19
recommended_platform: Self-managed VPS (Mikrus, halina175) + Docker Compose + Traefik za Cloudflare
runner_up: Render
context_type: mvp
tech_stack:
  language: PHP 8.5 (backend), TypeScript (frontend)
  framework: Laravel 13 + Inertia 3 + Vue 3
  runtime: kontener Docker (php-fpm + nginx + supervisord z queue:work i schedule:work), PostgreSQL 18
---

## Recommendation

**Zostajemy na obecnym VPS: Mikrus (halina175) + Docker Compose + Traefik za Cloudflare.**

Aplikacja wymaga stale działającego workera kolejki i schedulera oraz PostgreSQL 18. To odrzuca Netlify, Cloudflare Containers i Vercel (stałe procesy tylko w becie). Według samych kryteriów agentowych wygrywa Render, ale wywiad ustawił priorytety: najważniejszy jest minimalny koszt, liczy się znajomość własnego VPS, wystarcza jeden region (PL), a usługi zewnętrzne są dopuszczalne (kopie w Cloudflare R2). Obecny VPS kosztuje ~130–197 zł rocznie za serwer dzielony przez pięć aplikacji, ma działającą produkcję z bramkami wdrożenia (tryb serwisowy, snapshoty użytkowników, liczby kontrolne, kopia instancji przed migracją), kopie poza serwerem w R2 i monitoring Healthchecks.io. Każdy PaaS to od $6 do $47 miesięcznie i migracja bez zysku funkcjonalnego. Słabości VPS (rollback, budowanie na produkcji, RPO kopii) łagodzimy na miejscu. Render jest ścieżką ewakuacji, gdy Mikrus zawiedzie.

**Rozjazd dokumentacji:** produkcja to Mikrus + Traefik (`kostecki-dev-infra`) + Cloudflare proxy, a backupy off-site idą do Cloudflare R2. `tech-stack.md` §6 opisuje to już poprawnie. Nieaktualny zostaje `kostecki-dev-infra/docs/household-treasurer.md` (`artisan serve`, wdrożenie z `development`). Ten plik opisuje stan faktyczny.

## Platform Comparison

Odpowiedzi z wywiadu: (1) stałe procesy wymagane, (2) minimalizuj koszt, (3) znajomość: własny VPS + Docker (repo `kostecki-dev-infra` obok), (4) jeden region, (5) zewnętrzni dostawcy w porządku (kopie: lokalnie + Cloudflare R2).

**Twardy filtr** (Laravel ze stałym `queue:work` + `schedule:work` i PostgreSQL):

| Platforma | Wynik filtra | Powód |
|---|---|---|
| Netlify | odpada | Brak PHP (funkcje TS/JS/Go), background functions do 15 min. |
| Cloudflare Workers/Containers | odpada | Workers bez PHP. Containers (GA 2026-04-13) usypiają się (`sleepAfter`), brak gwarancji czasu życia instancji, dysk efemeryczny, brak zarządzanego Postgresa. |
| Vercel | odpada | Stałe procesy tylko przez Daemons (Public Beta) + kontenerowe Functions (beta), praktycznie wyłącznie na Pro (~$35–45/mies. + Neon). |

**Punktacja kandydatów, którzy przeszli filtr** (Pass / Partial / Fail):

| Platforma | CLI-first | Managed/Serverless | Docs dla agenta | Stabilne API wdrożeń | MCP / integracja | Koszt/mies. (szac.) | PG 18 |
|---|---|---|---|---|---|---|---|
| **Mikrus VPS + Compose** (obecny) | Partial | Fail | Partial | Partial | Fail | ~3 zł (130–197 zł/rok na 5 aplikacji) | tak |
| Hetzner Cloud VPS + Compose | Partial | Fail | Partial | Partial | Fail | €5,99 (CX23) — **„currently unavailable”** | tak |
| **Render** (Frankfurt) | Partial | Pass | Pass | Pass | Pass | $14–21 | tak |
| **Railway** (EU West, Amsterdam) | Partial | Pass | Pass | Pass | Partial | $6–10 | tak |
| Laravel Cloud (Frankfurt) | Partial | Pass | Pass | Pass | Fail | $26–28 bez zmian w kodzie; $3–8 z managed queue | tak |
| Fly.io (Frankfurt) | Pass | Partial | Pass | Pass | Partial | ~$47 z MPG; $8–10 z nieutrzymywanym PG | **nie** (16/17) |

Wagi: koszt (P2) karze plany bazowe Render, Laravel Cloud i Fly.io. Znajomość (P3) rozstrzyga na rzecz obecnego VPS. Zasięg jednego regionu (P4) zeruje przewagę edge. Brak preferencji współlokalizacji (P5) nie premiuje PaaS z bazą.

**Notatki per platforma** (stan na 2026-11-19):

- **Mikrus VPS** — kontener LXC na wspólnym kernelu, sprzęt Hetzner w Helsinkach. CPU „niegwarantowane”, wspólny IPv4 (porty przydzielane, zalecany proxy Cloudflare), brak snapshotów, „Strych” 200 MB, brak SLA. REST API (`api.mikr.us`: info, stats, restart, logs, exec 60 s), bez snapshotów. Wzmianka o MCP na stronie bez dokumentacji (niezweryfikowane). CLI-first tylko przez SSH i własne skrypty (`scripts/deploy.sh`, `scripts/artisan.sh`). Dokumentacja agentowa to głównie nasze własne runbooki w README i `kostecki-dev-infra/docs`.
- **Hetzner Cloud** — `hcloud` CLI (create, snapshot, rebuild, firewall; `metrics` alpha). Brak oficjalnego MCP. Ceny po podwyżkach z kwietnia i czerwca 2026. Najmniejsze plany oznaczone „currently unavailable”, a od 26.06.2026 trwa „Limited availability of cloud instances”. Jako plan B dzisiaj niepewny.
- **Render** — Docker, region Frankfurt (GA). CLI v2.37 (`render deploys create --wait`, `render logs --tail`, `render ssh`, `render psql`), **bez komendy rollback** (panel/API). `llms.txt` i każda strona jako `.md`. MCP GA od 2025-08-21 (bez usuwania usług i skalowania). Postgres 18 GA (domyślny od 2025-11-13), PITR tylko płatnie (3 dni Hobby / 7 Pro). Dysk tylko na jednej instancji jednej usługi, wyłącza zero-downtime deploy, niedostępny dla pre-deploy i cronów. Darmowy plan bezużyteczny (usypianie, brak dysku, zablokowany SMTP). Plany z 2026-08-01: Hobby $0 (5 GB transferu), instancja 512 MB $7, Postgres od $6.
- **Railway** — Dockerfile (Compose nieobsługiwany), EU West = Amsterdam. CLI `railway up/logs/ssh/redeploy`, rollback z panelu i tylko póki obraz istnieje (72 h na Hobby). `llms.txt` + docs na GitHubie. Postgres 18 od 2026-03-20 (zgłoszenie 2026-11-17: obraz `:18` wciąż na 18.6). HA Postgres oznaczony jako eksperymentalny. Backupy wolumenów i PITR wg pracowników tylko na Pro. Wolumin 5 GB na Hobby, jeden na usługę. Buckets GA (2025-11-28). MCP `mcp.railway.com` „public testing” od 2026-04-17, bez ogłoszenia GA. **Incydent bezpieczeństwa 2026-10-21**, rotacja poświadczeń u wszystkich klientów.
- **Laravel Cloud** — PHP 8.5 GA i domyślny, Laravel 13 wspierany. CLI `laravel/cloud-cli` (od 2026-03-02) i API GA, bez rollbacku (redeploy starszego commita). `llms.txt` + `.md` + OpenAPI. Brak oficjalnego MCP (tylko community). Filesystem efemeryczny, kasowany przy każdym deployu, więc CSV i backupy Spatie musiałyby iść do bucketu. Stały worker kolejki z bazą trzyma serverless Postgres (Neon) cały czas aktywny (~$19/mies. za samą bazę). Tani wariant wymaga `QUEUE_CONNECTION=cloud` (SQS, `aws/aws-sdk-php`, limit 90 s na job). Serverless Postgres jest ograniczony regionalnie (Frankfurt, Londyn).
- **Fly.io** — region `waw` wycofany (Region Consolidation), zostaje `fra`. `fly deploy`, `release_command` bez dostępu do woluminu, rollback dwukrokowy (`fly releases --image` → `fly deploy --image`). `llms.txt`. Managed Postgres tylko 16/17 (od $38, HA). Unmanaged Fly Postgres „no longer maintained”. Brak darmowego planu. MCP: lokalny `fly mcp server` eksperymentalny, hostowany `mcp.fly.io` bez narzędzi do deployu. Brak PG 18 oznacza downgrade z produkcji.

### Shortlisted Platforms

#### 1. Mikrus VPS + Docker Compose (Recommended)

Koszt bliski zeru, bo serwer dzielą landing, root-keeper, menu-maker, Vaultwarden i Backrest. Produkcja działa. `scripts/deploy.sh` ma bramki, których żaden PaaS nie daje z pudełka: tryb serwisowy przed `up -d`, `user:backup` każdego użytkownika, liczby kontrolne przed i po migracji, kopię instancji Spatie przed `migrate`. Kopie off-site idą tygodniowo do R2 (EU, bucket lock), monitoring z Healthchecks.io i ntfy działa. Postgres 18 jest pod pełną kontrolą. Przegrywa na kryteriach agentowych: brak MCP, brak rollbacku obrazu jedną komendą, każda operacja przez SSH.

#### 2. Render

Najlepszy wynik agentowy: MCP GA, `llms.txt`, CLI GA, Postgres 18 we Frankfurcie, region najbliżej Polski. Luka: ~$14–21 miesięcznie wobec ~0, dysk przypięty do jednej usługi (worker i scheduler muszą zostać w tym samym kontenerze supervisord, czyli wprost obecny obraz), brak zero-downtime przy dysku, PITR tylko 3 dni na Hobby, rollback poza CLI. To docelowa ścieżka ewakuacji.

#### 3. Railway

Najtańszy managed (~$6–10), Postgres 18, Dockerfile wprost. Luki: rollback tylko z panelu i tylko 72 h, backupy wolumenów i PITR tylko na Pro, MCP w „public testing”, region Amsterdam, a przede wszystkim incydent bezpieczeństwa z 2026-10-21, który dla aplikacji z danymi finansowymi waży więcej niż różnica w cenie.

## Anti-Bias Cross-Check: Mikrus VPS + Docker Compose

### Devil's Advocate — Weaknesses

1. **Brak szybkiego rollbacku.** Obraz `household-treasurer-app:prod` jest nadpisywany przy każdym `docker compose build`. Wycofanie to `migrate:rollback` nowym kodem, `git revert`, `git pull` na serwerze i ponowne budowanie. To minuty ręcznych kroków na przyduszonym CPU, w czasie gdy aplikacja wisi w trybie serwisowym. *Zmitygowane przez ADR-0107: `scripts/rollback.sh <tag>` przełącza obraz w sekundy, bez budowania.*
2. **Budowanie na produkcji.** `composer install` i `npm run build` w `Dockerfile.prod` biegną na tym samym współdzielonym, niegwarantowanym CPU co Postgresy pięciu aplikacji i Vaultwarden. Ani `deploy.sh`, ani `kostecki-dev-infra` nie robi `docker image prune`, więc dysk (25–40 GB) zapełnia się warstwami. *Obrazy aplikacji zmitygowane przez ADR-0107: `deploy.sh` zostawia 3 najnowsze tagi i czyści wiszące obrazy tej aplikacji; cache budowania zostaje.*
3. **Jeden punkt awarii dla wszystkiego.** `/storage` to drugi dysk u tego samego dostawcy. Kopia off-site jest tygodniowa (niedziela 06:00), więc po utracie serwera RPO dla danych finansowych wynosi do 7 dni.
4. **Docker w nieuprzywilejowanym LXC na cudzym kernelu.** Aktualizacja runc lub AppArmor po stronie hosta (w 2025 zepsuła Dockera w LXC u innych dostawców) może zatrzymać wszystkie kontenery naraz, bez wpływu po naszej stronie.
5. **Operacje agenta tylko przez SSH.** Na hoście zsh (zmienne nie dzielą się na słowa), `compose exec` w heredocu zjada stdin, sekrety w `.env.prod` tylko na serwerze. Brak MCP i API wdrożeń, więc agent nie powinien wdrażać bez nadzoru.

### Pre-Mortem — How This Could Fail

Pół roku później Mikrus aktualizuje hosta LXC i Docker przestaje startować kontenery. Padają naraz budżet, root-keeper, menu-maker i Vaultwarden. Alarm z Healthchecks.io przychodzi po 10 minutach, ale nie ma z czego przywracać na miejscu, bo Mikrus nie robi snapshotów. Plan zapasowy „postawię to samo na Hetznerze” nie działa: najmniejsze instancje są „currently unavailable”, a Mikrus i tak stał na Hetznerze w Helsinkach. Odtworzenie z R2 wymaga hasła restic, które leży w Vaultwardenie, a ten jest w tej samej kopii. Ratuje tylko kopia papierowa. Ostatni tygodniowy snapshot ma 6 dni, więc importy CSV i ręczne operacje z tego tygodnia przepadają. Wcześniej był jeszcze drugi incydent: migracja z błędem w `down()` wymusiła `git revert`, a ponowne budowanie obrazu trwało kwadrans na throttlowanym CPU, przez co aplikacja wisiała w trybie serwisowym. Wszystkie założenia brzmiały rozsądnie: „to kilku użytkowników”, „mamy kopie”, „zawsze można przenieść się na Hetznera”. Zawiodła koncentracja wszystkiego w jednym miejscu i brak przećwiczonej ścieżki ewakuacji.

### Unknown Unknowns

- **Mikrus to Hetzner Helsinki.** Przeprowadzka na Hetzner Cloud nie dywersyfikuje dostawcy sprzętu, a Hetzner Cloud ma ograniczoną dostępność od czerwca 2026. Realny plan B to PaaS (Render) albo VPS u innego dostawcy.
- **Cloudflare trzyma wszystko poza serwerem:** DNS, proxy, Access, kopie w R2. Firewall wpuszcza 80/443 tylko z Cloudflare, więc blokada konta Cloudflare odcina jednocześnie aplikację i kopie.
- **Major upgrade Postgresa (18 → 19)** w Compose to dump i restore (obraz `postgres:18-alpine` nie przeniesie danych przez `pg_upgrade` między obrazami). Tag `18-alpine` jest ruchomy w obrębie wersji minor, więc `docker compose pull` po cichu zmienia wersję.
- **Dokumentacja infrastruktury jest rozjechana:** `tech-stack.md` mówi „Hetzner + Caddy”, `kostecki-dev-infra/docs/household-treasurer.md` mówi „deploy z `development`” i „artisan serve”. Kod używa Mikrusa, Traefika, `PRODUCTION_BRANCH=main` i php-fpm + nginx. Agent, który uwierzy dokumentom, wdroży złą gałąź albo poszuka nieistniejącego Caddy. *`tech-stack.md` poprawiony; `kostecki-dev-infra/docs/household-treasurer.md` nadal opisuje `artisan serve` i gałąź `development`.*
- **Plan Mikrusa (RAM, dysk) nie jest nigdzie zapisany.** Limity Compose to 1 GB (app) + 512 MB (pgsql) dla samej tej aplikacji, a do tego cztery inne stosy. Bez znanej puli RAM nie wiadomo, czy build obrazu zmieści się obok działających usług.

## Operational Story

- **Preview deploys**: brak środowisk preview na serwerze. Podgląd zmiany to worktree uruchomiony lokalnie według `context/foundation/agents/worktree-preview.md` (osobna baza i port na worktree). CI (`.github/workflows/tests.yml`) uruchamia testy na push i PR na PostgreSQL. Produkcja dostaje tylko `main`.
- **Secrets**: `.env.prod` na VPS w `/srv/apps/household-treasurer` (gitignored), montowany read-only jako `.env`. Kopia raz w tygodniu do R2 przez restic (szyfrowane, hasło w `/etc/restic-offsite/password`, poza serwerem w Vaultwardenie i na papierze). Czyta je tylko właściciel konta SSH. Rotacja: edycja `.env.prod`, potem `scripts/deploy.sh` (przebudowuje cache konfiguracji). Klucz Resend i `APP_KEY` rotuje człowiek.
- **Rollback**: według README „Wycofanie …” dla danej zmiany. Najpierw wycofanie obrazu bez budowania ([ADR-0107](adr/0107-image-tags-and-rollback-without-build.md)): `scripts/rollback.sh <tag>` (sekundy; gdy w bazie są migracje, których obraz docelowy nie zna, skrypt odmawia i każe najpierw zrobić `migrate:rollback`). Potem, żeby kod na serwerze nie wrócił do złego commita, `git revert` + `scripts/deploy.sh`. Pełna ścieżka według README: na serwerze `scripts/artisan.sh migrate:rollback --force` (jeszcze nowym kodem), potem `git revert` lokalnie, push na `main`, na serwerze `git pull --ff-only origin main` i `scripts/deploy.sh`. Czas pełnej ścieżki: kilka do kilkunastu minut, bo obraz budowany jest od nowa; sam `rollback.sh` to sekundy. Zastrzeżenia: część `down()` jest stratna (S-26), snapshot nowszej wersji nie wczyta się w starym kodzie, ostatecznością jest `backup:restore` kopii z kroku 6 `deploy.sh`.
- **Approval**: człowiek wykonuje wszystko, co dotyka halina175: `scripts/deploy.sh`, `migrate:rollback`, `backup:restore`, `user:restore`, `budget:load-sheet`, zmiany `.env.prod`, zmiany w `kostecki-dev-infra` na serwerze, operacje na buckecie R2 i w panelu Cloudflare. Agent może bez nadzoru: testy lokalne i w CI, commity i PR-y, `curl -I https://budget.kostecki.dev/up`, odczyt logów tylko do odczytu (jeśli człowiek udostępni SSH).
- **Logs**: runtime: `ssh halina175 'bash -s' <<'EOF'` z `cd /srv/apps/household-treasurer && docker compose --env-file .env.prod -f docker-compose.prod.yml logs --tail=200 app </dev/null` oraz `tail -n 200 storage/logs/laravel.log`. Stan procesów: `… exec -T app supervisorctl status </dev/null` (oczekiwane `php-fpm`, `nginx`, `queue`, `scheduler` = RUNNING). Pipeline: `gh run list` / `gh run view --log-failed`. Dostępność: historia checków na Healthchecks.io (`site-budget`), alerty na ntfy.
- **Errors**: Application error tracking (S-45): self-hosted Bugsink on halina175 with the Sentry SDKs, alerts to ntfy without error content — comparison, risks and plan input in [error-tracking.md](error-tracking.md).

## MCP endpoint

The public MCP endpoint (ADR-0110) lets Claude on the web, Desktop and the phone read the budget through OAuth.

- **Paths:** `/mcp` (Streamable HTTP), `/oauth/*` (authorize, token, register) and `/.well-known/oauth-*` (discovery). Everything else stays behind the normal session login.
- **Caller:** the connector request comes from Anthropic's servers, not from the user's device. Their outbound ranges are `160.79.104.0/21` and `153.61.208.0/20` ([Anthropic IP addresses](https://platform.claude.com/docs/en/api/ip-addresses)); they are shared by every Claude user, so they are not an authentication signal.
- **Cloudflare (human, panel):** on 2026-11-19 the connector worked with no Cloudflare change, so nothing is configured for these paths. They are not cached anyway (POST, JSON and `no-cache, private` are never cached by default; `cf-cache-status: DYNAMIC`). If the connector stops working, look in Security → Events for a challenge on `/mcp` or `/oauth`, then check in this order:
  - **Bot Fight Mode** (Security → Settings, filter "Bot traffic"). It is zone-wide for all apps on `kostecki.dev`, can challenge API traffic, and cannot be skipped by a WAF custom rule or a Page Rule. Two fixes: turn it off, or add IP Access "Allow" rules for both Anthropic ranges. Allow rules also bypass every custom rule, rate limit and WAF managed rule for those addresses across the zone.
  - **AI Crawler and Agent access policies** (same page). The "Agent" category must stay on Allow.
  - **Own WAF custom rules and rate limits**, especially rules by country: the connector comes from the US. If one matches, add a Skip rule above it for `/mcp`, `/oauth/*` and `/.well-known/oauth-*` on `budget.kostecki.dev`.
- **Secrets:** `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` live in `.env.prod` (multi-line PEM as one line with `\n`), never in `storage/`: a key file written by `deploy.sh` would be root-owned and unreadable by PHP-FPM, and `storage/` is outside the backup set. Without the keys every `/mcp` request answers 500, even without a token. **Rotation:** new keys invalidate every issued token, so each client signs in again; run `scripts/deploy.sh` afterwards (rebuilds the config cache).
- **Write switch:** `MCP_WRITE_ENABLED` in `.env.prod` (default `false`) registers the cash operation tool; the read tools never depend on it. `scripts/deploy.sh` caches the config, so every change of the switch (on, or off in an emergency) takes effect only after `scripts/artisan.sh config:cache`.
- **Logs:** one line per remote call in `storage/logs/mcp-*.log` (channel `mcp`, `info`, 14 days, independent of `LOG_LEVEL=error`): user id, JSON-RPC method, tool name, HTTP status, duration. Never arguments, amounts or descriptions.
- **Housekeeping:** the scheduler runs `passport:purge` hourly and `app:prune-oauth-clients` daily (clients older than 7 days with no live token; dynamic client registration is open, so unused clients are removed).
- **Rollback:** the Passport tables are new, so `scripts/artisan.sh migrate:rollback --force` for them comes before `scripts/rollback.sh <tag>` (see README "Wdrożenie i przywracanie").

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Wycofanie wadliwego wdrożenia trwa minuty (rebuild obrazu), aplikacja w trybie serwisowym | Devil's advocate | M | M | Tagować obraz SHA commita (`image: household-treasurer-app:${APP_IMAGE_TAG}`), zostawiać 3 ostatnie tagi. Rollback kodu = `up -d` z poprzednim tagiem, bez budowania. Zrobione jako `scripts/rollback.sh <tag>` (ADR-0107). |
| Build na produkcji dusi CPU/RAM innych aplikacji i Vaultwardena | Devil's advocate | M | M | Budować obraz w GitHub Actions i pchać do GHCR (prywatny). `deploy.sh` robi `docker compose pull` zamiast `build`. |
| Dysk VPS zapełnia się starymi obrazami i warstwami | Devil's advocate | M | H | Dodać do `deploy.sh` `docker image prune -f --filter "until=168h"` po udanym wdrożeniu. Check zajętości dysku w `uptime-check.sh`. Obrazy aplikacji czyści już `deploy.sh` (ADR-0107, filtr po etykiecie zamiast `until=168h`), cache budowania i check dysku zostają otwarte. |
| Utrata serwera, kopia off-site do 7 dni stara | Devil's advocate / Pre-mortem | L | H | Zwiększyć częstotliwość `offsite-backup` dla archiwów aplikacji do codziennej (archiwum Spatie jest małe, R2 free 10 GB). Utrzymać hasło restic poza Vaultwardenem. |
| Host LXC (runc/AppArmor/kernel) zatrzymuje Dockera dla wszystkich aplikacji | Unknown unknowns / Research finding | L | H | Spisany i raz przećwiczony runbook ewakuacji na Render (Dockerfile.prod + Postgres 18 + restore z R2). Zapisać czas odtworzenia (RTO). |
| Plan B „Hetzner” niedostępny i niedywersyfikujący | Unknown unknowns / Research finding | M | M | Planem B jest Render (Frankfurt, PG 18) albo VPS spoza Hetznera. Wpisać to do `kostecki-dev-infra/docs/ARCHITECTURE.md`. |
| Cloudflare jako pojedynczy punkt: DNS, proxy, Access i kopie | Unknown unknowns | L | H | 2FA na koncie (już wymagane). Drugi cel kopii poza Cloudflare (np. zaszyfrowany restic na dysk lokalny właściciela raz w miesiącu). |
| Rozjazd dokumentacji (Hetzner/Caddy, gałąź `development`, artisan serve) myli agentów | Unknown unknowns | H | M | Poprawić `tech-stack.md` §6 i `kostecki-dev-infra/docs/household-treasurer.md` w osobnej zmianie (`docs(...)`). Odsyłać do tego pliku. `tech-stack.md` zrobiony; w `kostecki-dev-infra` otwarte: procesy (php-fpm + nginx zamiast `artisan serve`) i gałąź `main`. |
| Nieznana pula RAM Mikrusa, build obrazu może wywołać OOM | Unknown unknowns | M | M | Zapisać plan Mikrusa (RAM, dysk) w `kostecki-dev-infra/docs/ARCHITECTURE.md`. Mitygację „build w CI” traktować priorytetowo. |
| Major upgrade Postgresa wymaga dump/restore, ruchomy tag minor | Unknown unknowns | L | M | Przypiąć pełną wersję minor w `docker-compose.prod.yml` i aktualizować świadomie. Upgrade major jako osobna zmiana z próbą na kopii (jak próby S-18). |
| Agent wdroży bez nadzoru przez SSH z pułapkami zsh/heredoc | Devil's advocate | L | M | Wdrożenie i operacje produkcyjne zostają po stronie człowieka (Approval). Skrypty uruchamiane przez `bash -s` z `</dev/null` przy `exec`. |
| Brak oficjalnego SLA i gwarancji CPU u Mikrusa | Research finding | M | L | Akceptowalne dla MVP z kilkoma użytkownikami. Healthchecks.io alarmuje przy przestoju. Przy wzroście wymagań przejść na Render. |

## Getting Started

Platforma już działa, więc pierwsze kroki to mitygacje z rejestru ryzyk, każda jako osobna zmiana 10x (`/10x-new`):

1. **Poprawić rozjazd dokumentacji:** w `context/foundation/tech-stack.md` §6 zamienić „Hetzner + Caddy” na „Mikrus (halina175) + Traefik (`kostecki-dev-infra`) za Cloudflare, kopie off-site w Cloudflare R2” i odesłać do tego pliku. W `kostecki-dev-infra/docs/household-treasurer.md` poprawić gałąź na `main` i opis procesów (php-fpm + nginx + queue + scheduler). *`tech-stack.md` zrobiony; `kostecki-dev-infra/docs/household-treasurer.md` (wiersze o `artisan serve` i `PRODUCTION_BRANCH`) czeka w tamtym repo.*
2. **Szybki rollback obrazu:** w `docker-compose.prod.yml` zmienić `image: household-treasurer-app:prod` na `household-treasurer-app:${APP_IMAGE_TAG:-prod}`, a w `scripts/deploy.sh` eksportować `APP_IMAGE_TAG=$(git rev-parse --short HEAD)` przed `build`. Zachowywać trzy ostatnie tagi, resztę usuwać `docker image prune -f --filter "until=168h"`. *Zrobione inaczej niż zapisano ([ADR-0107](adr/0107-image-tags-and-rollback-without-build.md)): `:prod` zostaje wskaźnikiem, tagi SHA leżą obok (zmienna `APP_IMAGE_TAG` w Compose podniosłaby po cichu przestarzały `:prod`), a sprzątanie filtruje po etykiecie `maintainer`, nie po wieku.*
3. **Build poza produkcją:** job w `.github/workflows` budujący `docker/8.5/Dockerfile.prod` po zielonych testach na `main` i pchający do `ghcr.io` z tagiem SHA. Na serwerze `docker compose pull app` zamiast `build` (bramki `deploy.sh` bez zmian).
4. **Krótsze RPO:** w `kostecki-dev-infra` przestawić `offsite-backup.timer` dla archiwów aplikacji na codziennie i dostosować próg `offsite-backup-check` (dziś 194 h).
5. **Przećwiczona ewakuacja:** runbook „Render z kopii R2” (Dockerfile.prod jako Docker service we Frankfurcie, Render Postgres 18, `backup:restore` archiwum), wykonany raz na próbę z zapisanym czasem.

## Out of Scope

The following were not evaluated in this research:
- Docker image configuration
- CI/CD pipeline setup
- Production-scale architecture (multi-region, HA, DR)
