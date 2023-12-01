<?php

namespace Sfinktah\Shopify;

interface IInstanceLock
{
    public function acquire($waitSeconds = 0);

    public function release();
}