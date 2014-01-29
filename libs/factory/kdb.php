<?php
import('swoole.database.SwooleKDB');
global $php;
$kdb = new SwooleKDB($php->db,KDB_CACHE);
$kdb->roots = KDB_ROOT;
$kdb->db_prefix = TABLE_PREFIX;
