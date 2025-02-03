<?php

namespace App;

use App\MigrationHandler\Migrator;

class Migrate
{

    private static $commands = ['create', 'migrate', 'rollback', 'status'];
    private static $command = null;
    private static $migrationName = null;
    private static $tableName = null;
    private static $migrator;


    public function __construct(Migrator $migrator, array $argv = [])
    {

        // Check the command passed to the script
        self::$migrator = $migrator ?? null;
        self::$command = $argv[1] ?? null;
        self::$migrationName = $argv[2] ?? null;
        self::$tableName = $argv[3] ?? null;

        if (!$this->validateCommand(command: self::$command)) {
            echo "Invalid command.\n";
            exit(1);
        }

        $this->run();
    }

    private static function validateCommand(string $command): bool
    {
        return in_array(needle: $command, haystack: self::$commands);
    }

    private static function run(): void
    {
        // Handle the migration command
        switch (self::$command) {

            case 'create':

                if (!self::$migrationName) {
                    echo "Please provide a migration name.\n";
                    exit(1);
                }

                if (!self::$tableName) {
                    echo "Please provide a table name.\n";
                    exit(1);
                }

                self::$migrator->createMigration(
                    tableName: self::$migrationName,
                    migrationName: self::$tableName
                );

                break;

            case 'migrate':
                echo "Running migrations...\n";
                self::$migrator->run();
                echo "Migrations completed.\n";
                break;

            case 'rollback':
                echo "Rolling back migrations...\n";
                self::$migrator->rollback();
                echo "Migrations rolled back.\n";
                break;

            case 'status':
                echo "Listing migrations status...\n";
                $allMigrations = self::$migrator->getMigrations();

                foreach ($allMigrations as $migration) {
                    $run_at = !is_null(value: $migration['run_at']) ? 'Run (' . $migration['run_at'] . ')' : 'Pending';
                    $sequence = $migration['run_sequence'];
                    echo "{$migration['migration_name']} - {$run_at} [SEQ: {$sequence}]\n";
                }
                break;

            default:
                echo "Unknown command.\n";
                break;
        }
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

$migrator = new Migrator();

$migrate = new Migrate(migrator: $migrator, argv: $argv);
