<?php
namespace Swoole\IFace;

interface Queue
{
    function put($data);
    function get();
}