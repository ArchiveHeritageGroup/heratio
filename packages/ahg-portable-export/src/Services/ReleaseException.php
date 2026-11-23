<?php

namespace AhgPortableExport\Services;

/** A release step was refused; $failures says why, in words an archivist can act on. */
class ReleaseException extends \RuntimeException
{
    /** @param string[] $failures */
    public function __construct(string $message, public array $failures = [])
    {
        parent::__construct($message);
    }
}
