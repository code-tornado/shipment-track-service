<?php

namespace App\Tenancy;

use App\Models\Company;

/**
 * Holds the company the current request (or console command) acts for.
 * Set by the SetCurrentCompany middleware from the authenticated token, or
 * explicitly by commands and jobs. Every company-owned model is scoped to it.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function isSet(): bool
    {
        return $this->company !== null;
    }

    /**
     * Run a callback as the given company and restore the previous one after.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
