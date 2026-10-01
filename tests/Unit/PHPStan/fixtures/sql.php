<?php

class wpdb
{
    public function query(?string $query): void {}
    public function get_results(?string $query): void {}
    public function prepare(string $query, mixed ...$args): ?string { return $query; }
    public function insert(string $table, array $values): void {}
}
class OtherDb { public function query(string $query): void {} public function prepare(string $query): string { return $query; } }
class ChildDb extends wpdb {}
class SqlFixture
{
    public function __construct(private wpdb $db, private wpdb $wpdb) {}
    private function getDatabase(): wpdb { return $this->db; }
    public function checks(wpdb $connection, ?wpdb $nullable, ChildDb $child, OtherDb $other, ChildDb|OtherDb $union, string $value): void
    {
        $alias = $connection;
        $this->db->query("SELECT $value");
        $this->wpdb->get_results('SELECT ' . $value);
        $connection->query(query: $value);
        $alias->query(sprintf('SELECT %s', $value));
        $this->getDatabase()->query($value);
        $child->query($value);
        $nullable?->query($value);
        $connection->query($other->prepare($value));
        $connection->query('SELECT 1');
        $constant = 'SELECT 1';
        $connection->query($constant);
        $connection->query($connection->prepare('SELECT %s', $value));
        $connection->query(query: $connection->prepare(query: 'SELECT %s', args: $value));
        $connection->insert('table', ['value' => $value]);
        $other->query($value);
        $wpdb = $connection;
        $wpdb->query($value);
        $assigned = $connection->prepare('SELECT %s', $value);
        $connection->query($assigned);
        $connection->query($connection->prepare("SELECT $value"));
        $connection->query($connection->prepare('SELECT %s', $value) . $value);
        $union->query($value);
    }
}
