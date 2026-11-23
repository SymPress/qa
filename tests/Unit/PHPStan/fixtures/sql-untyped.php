<?php

final class LegacyRepository
{
    private $wpdb;

    public function __construct()
    {
        $this->wpdb = $GLOBALS['wpdb'];
    }

    public function find(string $value, int $id, $other): void
    {
        $this->wpdb->get_results("SELECT $value");
        $this->wpdb->query('DELETE FROM t WHERE x = ' . $value);
        $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}things WHERE id = %d", $id));
        $db = $GLOBALS['wpdb'];
        $db->get_var('SELECT ' . $value);
        $db->get_col($db->prepare('SELECT id FROM t WHERE x = %s', $value));
        $db->get_results("SELECT * FROM {$db->posts}");
        $db->get_results($other->prepare('SELECT %s', $value));
        $db->get_row("SELECT * FROM {$other->prefix}things");
        $query = $GLOBALS['wpdb']->query(...);
        $query("SELECT $value");
        $db->query("SELECT $value");
        $other->execute("SELECT $value");
    }
}
