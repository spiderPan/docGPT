<?php

namespace Pan\DocGpt\Logger;

interface Logger
{
    public function log(string $type, string|array $message): void;

}
