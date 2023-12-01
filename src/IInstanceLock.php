<?php

namespace Sfinktah\RemoteLock;

interface IInstanceLock
{
    public function acquire($waitSeconds = 0);

    public function release();
}