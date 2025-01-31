<?php

namespace DB;

abstract class Migration
{
    protected $db;

    // Constructor is now handled in the Migration Runner, not needed in individual migrations
    public function setDatabase($db)
    {
        $this->db = $db;
    }

    // Abstract methods to be implemented by each migration
    abstract public function up();
    abstract public function down();
}
