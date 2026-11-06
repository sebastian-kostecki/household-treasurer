# Preview from a worktree

Manual checks of a phase need a running app that serves the worktree code. The Sail stack serves the main checkout, so before asking the user to confirm manual checks, build and start a preview and give the address and login.

1. In the worktree `.env` set `DB_DATABASE` to a separate database (`create database <name>` in the `household-treasurer-pgsql-1` container). Never point it at `household_treasurer`: migrating it changes the main checkout's schema.
2. Run `php artisan migrate --seed --force` with the Sail image on the `household-treasurer_sail` network (the `docker run … sail-8.5/app` command from AGENTS.md "Commands"). `DemoHouseholdSeeder` creates `test@example.com` / `password` with a budget in execution.
3. Run `npm run build` again after every Vue or route change. The preview serves the built assets, and the Wayfinder step makes the build take a few minutes.
4. Start `php artisan serve --host=0.0.0.0 --port=8000` in the Sail image with `-d -p 8090:8000 --network household-treasurer_sail` and the worktree mounted at `/var/www/html`, then open http://localhost:8090.
5. Stop it with `docker rm -f <container>` and drop the database after the change is merged.
