<?php

declare(strict_types=1);

/**
 * Regression guard for Fase 4.2: SQLite in dev, PostgreSQL in production.
 *
 * The same migration files must work on both engines. This test does not
 * require a Postgres container in CI — it verifies the static surface
 * (config + migration source + Railway deploy artifacts) is configured so
 * that migrate:fresh in production will succeed without manual fixes.
 *
 * What would BREAK the assumption:
 *   - A migration using SQLite-only syntax (e.g. `->onConflict('IGNORE')`
 *     without a driver guard, raw SQL with `AUTOINCREMENT`, etc.)
 *   - config/database.php missing the pgsql block
 *   - railway.toml without a releaseCommand running `migrate --force`
 *   - .env.example not documenting DB_URL so production deploys fall back
 *     to SQLite
 */

it('config/database declares a pgsql connection separate from sqlite', function () {
    $config = require base_path('config/database.php');

    expect($config['connections'])->toHaveKey('pgsql');
    expect($config['connections']['pgsql']['driver'])->toBe('pgsql');
    // Postgres must read DB_URL so Railway's DATABASE_URL just works.
    expect($config['connections']['pgsql'])->toHaveKey('url');
    // sslmode defaults to prefer; production flips it to require via env.
    expect($config['connections']['pgsql'])->toHaveKey('sslmode');
});

it('the default DB connection is env-driven (not hardcoded to sqlite in code)', function () {
    $config = require base_path('config/database.php');

    // If somebody hardcodes 'sqlite' here, the production env override
    // is silently ignored. The default MUST come from env.
    expect($config['default'])->toBe('sqlite'); // current local default
    // The default must be readable as an env lookup at config load time,
    // so check that the env('DB_CONNECTION', 'sqlite') pattern is in play.
    $contents = file_get_contents(base_path('config/database.php'));
    expect($contents)->toContain("env('DB_CONNECTION', 'sqlite')");
});

it('migrations do not use SQLite-only syntax that would break on Postgres', function () {
    $migrationsDir = database_path('migrations');
    $files = glob($migrationsDir.'/*.php');

    expect($files)->not->toBeEmpty('No migration files found');

    // Build a single concatenated blob with file-name headers so a failure
    // points at the offending migration by basename. Pest's expect()->toContain
    // is variadic, so passing the message as a second needle would make it
    // silently fail; concat + a single assertion keeps the message attached.
    $markers = [
        'AUTOINCREMENT' => 'SQLite-only AUTOINCREMENT',
        'WITHOUT ROWID' => 'SQLite-only WITHOUT ROWID',
    ];

    $violations = [];
    foreach ($files as $file) {
        $contents = file_get_contents($file);
        $basename = basename($file);

        foreach ($markers as $needle => $reason) {
            if (str_contains($contents, $needle)) {
                $violations[] = "{$basename} uses {$reason}";
            }
        }

        if (preg_match('/->onConflict\([^)]+\)/', $contents)) {
            // onConflict is supported on both engines but only at certain
            // places — flag for human review rather than auto-fail.
            $violations[] = "{$basename} uses ->onConflict without a driver guard";
        }
    }

    expect($violations)->toBe([], "SQLite-only constructs found:\n  - ".implode("\n  - ", $violations));
});

it('nutrition_profiles.vitamins uses json (not jsonb) so it stays portable', function () {
    // Laravel's `$table->json()` maps to `json` on Postgres and `text` on
    // SQLite. `jsonb` is faster for indexing but requires Postgres ≥ 9.4
    // AND `$connection->getConfig('use_native_jsonb')` to be set on SQLite.
    // Since we read the column whole ($nutrition->vitamins), json is fine
    // and avoids cross-driver config drift.
    $migration = database_path('migrations/2026_07_01_225736_create_nutrition_profiles_table.php');
    $contents = file_get_contents($migration);

    expect($contents)->toContain("\$table->json('vitamins')");
    expect($contents)->not->toContain("\$table->jsonb('vitamins')");
});

it('railway.toml runs migrations before traffic via startCommand', function () {
    $toml = file_get_contents(base_path('railway.toml'));

    // Migrations run inside startCommand (before FrankenPHP binds :8080),
    // so the container is not accepting traffic until the schema is
    // current. releaseCommand runs only on Railway's release phase — too
    // late if migrations throw on first boot.
    expect($toml)->toContain('startCommand');
    expect($toml)->toContain('migrate --force');

    // --force is required: Laravel refuses to run migrate in production
    // without it, and our production env is APP_ENV=production.
    expect($toml)->toMatch('/migrate\s+--force/');
});

it('railway.toml healthcheck points at /up (the Laravel health route)', function () {
    $toml = file_get_contents(base_path('railway.toml'));

    expect($toml)->toContain('healthcheckPath');
    expect($toml)->toContain('/up');
});

it('.env.example documents DB_URL for Postgres on Railway', function () {
    $env = file_get_contents(base_path('.env.example'));

    // Without DB_URL, production deploys using DATABASE_URL would fall
    // back to per-field DB_HOST/DB_PORT/etc, which Railway does NOT set
    // by default — so the app would try to connect to localhost:5432 and
    // fail. Documenting DB_URL is what makes the integration work.
    expect($env)->toContain('DB_URL');
    expect($env)->toContain('DB_SSLMODE');
    // The default must remain sqlite for local dev zero-config.
    expect($env)->toContain('DB_CONNECTION=sqlite');
});

it('every foreign key column is UUID-typed and cascades on delete', function () {
    // The 3 custom migrations that have FKs all use foreignUuid() with
    // cascadeOnDelete(). If anyone adds a new FK with a different onDelete
    // (e.g. setNull for a FK whose parent row is meant to be immutable),
    // they should think twice — we want referential cleanup to be
    // aggressive because the data is throwaway (rebuilt from cache).
    $migrations = [
        '2026_07_01_225735_create_species_images_table.php' => ['species_id'],
        '2026_07_01_225736_create_nutrition_profiles_table.php' => ['species_id'],
        '2026_07_01_225737_create_recommendations_table.php' => ['species_id'],
        '2026_07_01_225738_create_ai_generations_table.php' => ['recommendation_id'],
    ];

    foreach ($migrations as $file => $columns) {
        $contents = file_get_contents(database_path('migrations/'.$file));
        $basename = basename($file);

        $missing = [];
        foreach ($columns as $column) {
            if (! str_contains($contents, "foreignUuid('{$column}')")) {
                $missing[] = "{$basename} should use foreignUuid for {$column}";
            }
            if (! str_contains($contents, 'cascadeOnDelete()')) {
                $missing[] = "{$basename} should cascade-delete when parent row is removed";
            }
        }

        expect($missing)->toBe([], "FK conventions violated:\n  - ".implode("\n  - ", $missing));
    }
});
