<?php

abstract class BaseMiddleware
{
    abstract public function handle($request);
}
