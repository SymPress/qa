<?php

function sqlGlobals(wpdb $typed, string $value, int $id): void
{
    global $wpdb;
    $wpdb->query("SELECT $value");
    $GLOBALS['wpdb']->get_results('SELECT ' . $value);
    $wpdb->query($wpdb->prepare('SELECT * FROM t WHERE id = %d', $id));
    $GLOBALS['wpdb']->query($GLOBALS['wpdb']->prepare('SELECT %s', $value));
    $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}things WHERE id = %d", $id));
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE 1 = 0");
    $typed->query($typed->prepare('SELECT * FROM ' . $typed->base_prefix . 'things WHERE id = %d', $id));
    $wpdb->query("SELECT * FROM {$wpdb->prefix}$value");
    $wpdb->query($wpdb->prepare("SELECT * FROM {$wpdb->custom_table} WHERE id = %d", $id));
    $typed->query($typed->prepare("SELECT * FROM {$typed->prefix}" . $value));
}

function sqlShadowedGlobal(OtherDb $wpdb, string $value): void
{
    $wpdb->query($value);
}
