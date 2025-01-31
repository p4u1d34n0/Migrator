<?php

namespace DB;

use SQLite3;

class Migrator
{
    private $db;
    private $migrationPath;

    const DEFAULT_MIGRATION_PATH = '../migrations';
    const DEFAULT_CONFIG_PATH = 'database.php';

    // Constructor
    public function __construct()
    {
        $configPath = $this->getConfigPath();
        $config = require $configPath;
        $this->connectToDatabase($config);

        // Use SQLite if no DB is provided
        if ($this->db === null) {
            $this->db = new SQLite3('data/migrations.db');
        }

        // Default migration path
        $this->migrationPath = $this->getMigrationPath();

        // Ensure migration history table exists in the database
        $this->createMigrationHistoryTable();
    }

    // Connect to the database
    private function connectToDatabase($config)
    {
        switch ($config['driver']) {
            case 'sqlite':
                $this->db = new SQLite3($config['sqlite']['database']);
                break;
            case 'mysql':
                $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['database']}";
                $this->db = new \PDO($dsn, $config['mysql']['username'], $config['mysql']['password']);
                break;
            // Add other database drivers as needed
            default:
                throw new \Exception("Unsupported database driver: {$config['driver']}");
        }
    }

    // Get the database connection
    public function getDb()
    {
        return $this->db;
    }

    // Create the migration history table if it doesn't exist
    private function createMigrationHistoryTable()
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migration_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration_name TEXT NOT NULL,
            run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    // Determine the config path (from composer.json or default)
    private function getConfigPath()
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
    private function getComposerConfigPath()
    {
        $composerFile = 'composer.json';
        if (!file_exists($composerFile) || !is_readable($composerFile)) {
            return null;
        }

        $composerData = json_decode(file_get_contents($composerFile), true);
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
        if (!file_exists($composerFile) || !is_readable($composerFile)) {
            return null;
        }

        $composerData = json_decode(file_get_contents($composerFile), true);
        if (isset($composerData['extra']['migrations-path'])) {
            return $composerData['extra']['migrations-path'];
        }

        return null;
    }

    public function run()
    {
        // Get all migration files in the migration path
        $migrations = $this->getMigrationFiles();

        // Get all migrations that have already been run
        $ranMigrations = $this->getRanMigrations();

        foreach ($migrations as $migration) {
            // If the migration has already been run, skip it
            if (in_array($migration['name'], $ranMigrations)) {
                continue;
            }

            // Include the migration file and run the 'up' method
            require_once $migration['file'];
            $className = $migration['class'];
            $migrationInstance = new $className();

            // Pass the database connection to the migration
            $migrationInstance->setDatabase($this->db);

            // Run the 'up' method
            $migrationInstance->up();

            // Log the migration as run
            $this->logMigration($migration['name']);
        }
    }

    public function getMigrationFiles()
    {
        // Assuming migration files are in the path defined in composer.json or the default location
        $files = [];
        foreach (glob($this->migrationPath . '/*.php') as $file) {
            // Extract the migration name and class name from the file
            $name = basename($file, '.php');
            // Class should match the filename
            $className = ucfirst($name);
            $files[] = [
                'name' => $name,
                'file' => $file,
                'class' => $className
            ];
        }

        return $files;
    }

    public function getRanMigrations()
    {
        $result = $this->db->query("SELECT migration_name FROM migration_history");
        $ranMigrations = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ranMigrations[] = $row['migration_name'];
        }
        return $ranMigrations;
    }

    private function logMigration($migrationName)
    {
        $stmt = $this->db->prepare("INSERT INTO migration_history (migration_name) VALUES (:migration_name)");
        $stmt->bindValue(':migration_name', $migrationName, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function rollback(){
        // Get all migration files in the migration path
        $migrations = $this->getMigrationFiles();

        // Get all migrations that have already been run
        $ranMigrations = $this->getRanMigrations();

        // Reverse the order of the ran migrations
        $ranMigrations = array_reverse($ranMigrations);

        foreach ($migrations as $migration) {
            // If the migration has already been run, skip it
            if (!in_array($migration['name'], $ranMigrations)) {
                continue;
            }

            // Include the migration file and run the 'down' method
            require_once $migration['file'];
            $className = $migration['class'];
            $migrationInstance = new $className();

            // Pass the database connection to the migration
            $migrationInstance->setDatabase($this->db);

            // Run the 'down' method
            $migrationInstance->down();

            // Remove the migration from the history
            $this->removeMigration($migration['name']);
        }
    }

    private function removeMigration($migrationName)
    {
        $stmt = $this->db->prepare("DELETE FROM migration_history WHERE migration_name = :migration_name");
        $stmt->bindValue(':migration_name', $migrationName, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function createMigration(string $tableName, string $migrationName): void
    {

        if(!is_writable($this->migrationPath)){
            echo "Migration path is not writable\n";
            exit(1);
        }

        $migrationFile = $this->migrationPath . '/' . date('YmdHis') . '_' . $migrationName . '.php';
        $migrationClass = ucfirst($migrationName);

        $migrationContent = <<<PHP
<?php

use DB\Migration;
use DB\Schema;
use DB\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            // \$table->id();
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

        
        file_put_contents($migrationFile, $migrationContent);

        echo "Migration created successfully: {$migrationFile}\n";



    }
}
