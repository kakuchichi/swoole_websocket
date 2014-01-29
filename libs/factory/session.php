<?php
$cache = \Swoole\Factory::getCache('session');
$session = new Swoole\Session($cache);