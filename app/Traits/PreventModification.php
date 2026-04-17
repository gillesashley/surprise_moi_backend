<?php

namespace App\Traits;

use RuntimeException;

trait PreventModification
{
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Audit log entries are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Audit log entries cannot be deleted.');
    }

    public function forceDelete(): ?bool
    {
        throw new RuntimeException('Audit log entries cannot be deleted.');
    }
}
