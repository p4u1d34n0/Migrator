<?php

namespace DB;

class Schema
{
    public static function create(string $tableName, callable $callback): void
    {
        // Instantiate a new blueprint for the table
        $blueprint = new Blueprint($tableName);
        
        // Call the callback to define columns
        $callback($blueprint);

        // Create the table using the blueprint
        $blueprint->create();
    }

    public static function update(string $tableName, callable $callback): void
    {
        // Instantiate a new blueprint for the table
        $blueprint = new Blueprint($tableName);
        
        // Call the callback to define columns
        $callback($blueprint);

        // Update the table using the blueprint
        $blueprint->update();
    }

    public static function drop(string $tableName): void
    {
        // Drop the table if it exists
        $sql = "DROP TABLE IF EXISTS {$tableName}";
        (new \DB\Migrator())->getDb()->exec($sql);
    }
}
