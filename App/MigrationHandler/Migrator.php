<?php

namespace App\MigrationHandler;

use SQLite3;
use PDO;
use Exception;

class Migrator
{
    private $db;
    private $migrationPath;
    private $dbtype = 'pdo';

    const DEFAULT_MIGRATION_PATH = '../migrations';
    const DEFAULT_CONFIG_PATH = 'config.php';

    // Constructor
    public function __construct()
    {
        $configPath = $this->getConfigPath();
        $config = require $configPath;
        $this->connectToDatabase(config: $config);

        // Use SQLite if no DB is provided to store migration history
        if ($this->db === null) {
            $this->db = new SQLite3(filename: 'Data/migrations.db');
            $this->dbtype = 'sqlite';
        }

        // Default migration path
        $this->migrationPath = $this->getMigrationPath();

        // Ensure migration history table exists in the database
        $this->createMigrationHistoryTable();
    }

    // Connect to the database
    private function connectToDatabase($config): void
    {
        switch ($config['driver']) {
            case 'sqlite':
                $this->db = new SQLite3(filename: $config['sqlite']['database']);
                break;
            case 'mysql':
                $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['database']}";
                $this->db = new PDO(dsn: $dsn, username: $config['mysql']['username'], password: $config['mysql']['password']);
                break;
                // Add other database drivers as needed
            default:
                throw new Exception(message: "Unsupported database driver: {$config['driver']}");
        }
    }

    // Get the database connection
    public function getDb(): PDO|SQLite3
    {
        return $this->db;
    }

    // Create the migration history table if it doesn't exist
    private function createMigrationHistoryTable()
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migration_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL,
            run_sequence INTEGER DEFAULT 0,
            run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Determine the config path (from composer.json or default)
    private function getConfigPath(): mixed
    {
        // Check composer.json for migrations-path configuration
        $composerPath = $this->getComposerConfigPath();
        if ($composerPath) {
            return $composerPath;
        }

        // Fallback to default migration path
        return self::DEFAULT_CONFIG_PATH;
    }

    // Read migrations path from composer.json
    private function getComposerConfigPath(): mixed
    {
        $composerFile = 'composer.json';
        if (!file_exists(filename: $composerFile) || !is_readable(filename: $composerFile)) {
            return null;
        }

        $composerData = json_decode(json: file_get_contents(filename: $composerFile), associative: true);
        if (isset($composerData['extra']['config-path'])) {
            return $composerData['extra']['config-path'];
        }

        return null;
    }

    // Determine the migration path (from composer.json or default)
    private function getMigrationPath()
    {
        // Check composer.json for migrations-path configuration
        $composerPath = $this->getComposerMigrationPath();
        if ($composerPath) {
            return $composerPath;
        }

        // Fallback to default migration path
        return self::DEFAULT_MIGRATION_PATH;
    }

    // Read migrations path from composer.json
    private function getComposerMigrationPath()
    {
        $composerFile = 'composer.json';
        if (!file_exists(filename: $composerFile) || !is_readable(filename: $composerFile)) {
            return null;
        }

        $composerData = json_decode(json: file_get_contents(filename: $composerFile), associative: true);
        if (isset($composerData['extra']['migrations-path'])) {
            return $composerData['extra']['migrations-path'];
        }

        return null;
    }

    private function getRunSequence(): int
    {
        $result = $this->db->query(query: "SELECT MAX(run_sequence) as max_sequence FROM migration_history WHERE run_at IS NULL");
        $row = $result->fetchArray();
        return $row['max_sequence'];
    }

    public function run(): void
    {

        // Get all migration files in the migration path
        $migrations = $this->getMigrationFiles();

        // Get the current sequence number
        $sequence = $this->getRunSequence();

        // Get all migrations that have already been run
        $ranMigrationArray = $this->getMigrations(run: true);
        $ranMigrations = array_column(array: $ranMigrationArray, column_key: 'migration_name');

        foreach ($migrations as $migration) {
            // If the migration has already been run, skip it
            if (in_array(needle: $migration['name'], haystack: $ranMigrations)) {
                continue;
            }

            // Include the migration file and run the 'up' method
            $migrationInstance = require_once $migration['file'];

            // Pass the database connection to the migration
            $migrationInstance->setDatabase($this->db);

            // Run the 'up' method
            $migrationInstance->up();

            // Update Run At
            $fileName = str_replace(search: '.php', replace: '', subject: basename(path: $migration['file']));
            $this->updateMigrationHistory(migrationFile: $fileName, up: true, sequence: $sequence);
        }
    }

    public function rollback(): void
    {
        // Get all migration files in the migration path
        $migrations = $this->getMigrationFiles();

        // Get all migrations that have already been run
        $ranMigrationArray = $this->getMigrations(run: true);
        $ranMigrations = array_column(array: $ranMigrationArray, column_key: 'migration_name');

        // Reverse the order of the ran migrations
        $ranMigrations = array_reverse(array: $ranMigrations);

        foreach ($migrations as $migration) {
            // If the migration has already been run, skip it
            if (!in_array(needle: $migration['name'], haystack: $ranMigrations)) {
                continue;
            }

            // Include the migration file and run the 'up' method
            $migrationInstance = require_once $migration['file'];

            // Pass the database connection to the migration
            $migrationInstance->setDatabase($this->db);

            // Run the 'down' method
            $migrationInstance->down();

            // Update Run At
            $fileName = str_replace(search: '.php', replace: '', subject: basename(path: $migration['file']));
            $this->updateMigrationHistory(migrationFile: $fileName, up: true, sequence: $sequence);

            // Remove the migration from the history
            //$this->removeMigration(migrationName: $migration['name']);
        }
    }

    public function getMigrationFiles(): array
    {
        // Assuming migration files are in the path defined in composer.json or the default location
        $files = [];
        foreach (glob(pattern: $this->migrationPath . '/*.php') as $file) {
            // Extract the migration name and class name from the file
            $name = basename(path: $file, suffix: '.php');
            // Class should match the filename
            $className = ucfirst(string: $name); // dont need this !?
            $files[] = [
                'name' => $name,
                'file' => $file,
                'class' => $className
            ];
        }

        return $files;
    }

    public function getMigrations($run = null): array
    {
        $result = $this->db->query(query: "SELECT * FROM migration_history");

        $ranMigrations = [];
        while ($row = $result->fetchArray()) {

            $run_at = $row['run_at'];
            if (is_null(value: $run_at) || empty($run_at)) {
                $run_at = null;
            }

            if ($run === null) {
                $ranMigrations[] = [
                    'migration_name' => $row['migration_name'],
                    'run_sequence' => $row['run_sequence'],
                    'run_at' => $run_at
                ];
            } else if ($run === true && !is_null(value: $run_at) && !empty($run_at)) {
                $ranMigrations[] = [
                    'migration_name' => $row['migration_name'],
                    'run_sequence' => $row['run_sequence'],
                    'run_at' => $run_at
                ];
            } else if ($run === false && (is_null(value: $run_at) || empty($run_at))) {
                $ranMigrations[] = [
                    'migration_name' => $row['migration_name'],
                    'run_sequence' => $row['run_sequence'],
                    'run_at' => $run_at
                ];
            }
        }
        return $ranMigrations;
    }

    private function removeMigration($migrationName): void
    {
        $stmt = $this->db->prepare(query: "DELETE FROM migration_history WHERE migration_name = :migration_name");
        $stmt->bindValue(param: ':migration_name', value: $migrationName, type: SQLITE3_TEXT);
        $stmt->execute();
    }

    public function createMigration(string $tableName, string $migrationName): void
    {

        if (file_exists(filename: $this->migrationPath . '/' . date(format: 'YmdHis') . '_' . $migrationName . '.php')) {
            echo "Migration already exists\n";
            exit(1);
        }

        if (!is_writable(filename: $this->migrationPath)) {
            chmod(filename: $this->migrationPath, permissions: 0755);
        }

        if (!is_writable(filename: $this->migrationPath)) {
            echo "Migration path is not writable\n";
            exit(1);
        }

        $migrationFile = $this->migrationPath . '/' . date(format: 'YmdHis') . '_' . $migrationName . '.php';
        $migrationClass = ucfirst(string: $migrationName);

        $migrationContent = <<<PHP
<?php

use App\MigrationHandler\Schema;
use App\MigrationHandler\Blueprint;
use App\MigrationHandler\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            // \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('{$tableName}');
    }
};
PHP;


        $ok = file_put_contents(filename: $migrationFile, data: $migrationContent);

        if ($ok === false) {
            echo "Error creating migration file\n";
            exit(1);
        }

        $fileName = str_replace(search: '.php', replace: '', subject: basename(path: $migrationFile));

        $this->addMigrationToHistory(migrationFile: $fileName);

        echo "Migration created successfully: {$migrationFile}\n";
    }

    private function addMigrationToHistory(string $migrationFile): void
    {
        // add the migration to the migration database with run_at set to null
        $stmt = $this->db->prepare(query: "INSERT INTO migration_history (migration_name,run_at) VALUES (:migration_name, null)");
        $stmt->bindValue(param: ':migration_name', value: basename(path: $migrationFile));
        $stmt->execute();
    }

    private function updateMigrationHistory(string $migrationFile, bool $up, int $sequence): void
    {

        if ($up) {
            $sequence++;
            $stmt = $this->db->prepare(query: "UPDATE migration_history SET run_at = :run_at_value, run_sequence = :sequence WHERE migration_name = :migration_name");
        } else {
            $stmt = $this->db->prepare(query: "UPDATE migration_history SET run_at = null WHERE migration_name = :migration_name and run_sequence = :sequence");
        }

        // update the migration in the migration database with the current timestamp

        $run_at_value = true;
        if (!$up) {
            $run_at_value = false;
        } else {
            $sequence + 1;
        }

        $stmt->bindValue(param: ':migration_name', value: $migrationFile);
        $stmt->bindValue(param: ':sequence', value: $sequence);
        $stmt->bindValue(param: ':run_at_value', value: $run_at_value ? date(format: 'Y-m-d H:i:s') : null);

        $stmt->execute();
    }
}
