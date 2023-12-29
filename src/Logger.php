<?php

namespace Pan\DocGpt;

interface Logger
{
    public function log(string $type, string|array $message): void;

}
