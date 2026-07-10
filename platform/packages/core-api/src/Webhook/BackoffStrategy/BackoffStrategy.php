<?php

namespace GridX\Webhook\BackoffStrategy;

interface BackoffStrategy
{
    public function waitInSecondsAfterAttempt(int $attempt): int;
}
