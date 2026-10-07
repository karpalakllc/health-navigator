<?php

namespace App\Services\Triage\V2;

final class LintReport
{
    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public function error(string $where, string $message): void
    {
        $this->errors[] = "{$where}: {$message}";
    }

    public function warn(string $where, string $message): void
    {
        $this->warnings[] = "{$where}: {$message}";
    }

    public function ok(): bool
    {
        return $this->errors === [];
    }

    public function merge(self $other): void
    {
        array_push($this->errors, ...$other->errors);
        array_push($this->warnings, ...$other->warnings);
    }

    /** @return array{errors: list<string>, warnings: list<string>} */
    public function toArray(): array
    {
        return ['errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
