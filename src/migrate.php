<?php

require_once __DIR__ . '/vendor/autoload.php';

// Check the command passed to the script
$command = $argv[1] ?? null;

// Ensure a valid command is passed
if (!in_array($command, ['create', 'migrate', 'rollback', 'status'])) {
    echo "Invalid command. Available commands are: create, migrate, rollback, status.\n";
    exit(1);
}

// Initialize the migration runner
$migrator = new DB\Migrator();

// Handle the migration command
switch ($command) {

    case 'create':
        $migrationName = $argv[2] ?? null; // Second argument is the migration name
        if (!$migrationName) {
            echo "Please provide a migration name.\n";
            exit(1);
        }

        $tableName = $argv[3] ?? null; // Third argument is the table name
        if (!$tableName) {
            echo "Please provide a table name.\n";
            exit(1);
        }

        $migrationFile = $migrator->createMigration($migrationName, $tableName);

        break;

    case 'migrate':
        echo "Running migrations...\n";
        $migrator->run();
        echo "Migrations completed.\n";
        break;

    case 'rollback':
        echo "Rolling back migrations...\n";
        $migrator->rollback();
        echo "Migrations rolled back.\n";
        break;

    case 'status':
        echo "Listing migrations status...\n";
        $ranMigrations = $migrator->getRanMigrations();
        $allMigrations = $migrator->getMigrationFiles();
        
        foreach ($allMigrations as $migration) {
            $status = in_array($migration['name'], $ranMigrations) ? 'Ran' : 'Pending';
            echo "{$migration['name']} - {$status}\n";
        }
        break;

    default:
        echo "Unknown command.\n";
        break;
}