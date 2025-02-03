<?php

namespace App\MigrationHandler;

class Blueprint
{
    protected $tableName;
    protected $columns = [];

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    public function id(): void
    {
        // Adds an auto-incrementing primary key column 'id'
        $this->columns[] = "id INTEGER PRIMARY KEY AUTOINCREMENT";
    }

    public function string(string $columnName): void
    {
        // Adds a string column
        $this->columns[] = "{$columnName} TEXT";
    }

    public function timestamps(): void
    {
        // Adds created_at and updated_at columns
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    }

    public function create(): void
    {

        if (empty($this->columns)) {
            throw new \Exception(message: "Error: Cannot create table '{$this->tableName}' because no columns have been defined. Please add at least one column.");
        }

        // Create the table by joining column definitions
        $columns = implode(", ", $this->columns);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} ({$columns})";

        (new Migrator())->getDb()->exec($sql);
    }

    public function update(): void
    {
        // Add columns to the table
        $columns = implode(", ", $this->columns);
        $sql = "ALTER TABLE {$this->tableName} ADD COLUMN {$columns}";

        (new Migrator())->getDb()->exec($sql);
    }
}
