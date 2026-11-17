# 🔍 Verification Guide & Walkthroughs

This document details step-by-step procedures to test and validate every feature developed with the Antigravity agent.

## Verification 1: Docker Environment (Sprint 0)
- [x] Start containers: `docker compose up -d`
- [x] Check status: `docker compose ps` (all 3 services `php`, `web`, `db` are `Up`)
- [x] Test console: `docker compose exec php php bin/console --version` (Symfony 7.4.23 dev mode)
- [x] Test MySQL connection: `docker compose exec php bin/console doctrine:database:create --if-not-exists` (Database `covoiturage` ready)

## Verification 2: Database & Fixtures (Sprint 1)
- [ ] Run migrations: `docker compose exec php bin/console doctrine:migrations:migrate --no-interaction`
- [ ] Load demo fixtures: `docker compose exec php bin/console doctrine:fixtures:load --no-interaction`
- [ ] Access EasyAdmin Back-Office: `http://localhost:8000/admin` (Sign in as `admin@covoit.ma`)

## Verification 3: Concurrency & Automated Tests (Sprint 2)
- [ ] Run test suite: `docker compose exec php bin/phpunit`
- [ ] Verify simultaneous overbooking protection test (`BookingConcurrencyTest`)
