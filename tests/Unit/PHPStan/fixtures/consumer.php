<?php

declare(strict_types=1);

function consumerSql(wpdb $connection, string $value): void
{
    $connection->query($value);
    $connection->query('SELECT 1');
}
