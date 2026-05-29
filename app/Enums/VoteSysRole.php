<?php

namespace App\Enums;

enum VoteSysRole: string
{
    case Admin           = 'admin';
    case ElectionOfficer = 'election_officer';
    case Student         = 'student';
    case Candidate       = 'candidate';

    public static function tryFromString(?string $role): ?self
    {
        if ($role === null || $role === '') {
            return null;
        }

        $canonical = strtolower($role);
        if ($canonical === 'hr') {
            return self::ElectionOfficer;
        }

        return self::tryFrom($canonical);
    }

    public static function fromString(string $role): self
    {
        return self::tryFromString($role) ?? self::Student;
    }

    public function canonical(): string
    {
        return $this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::Admin->value,
            self::ElectionOfficer->value,
            self::Student->value,
            self::Candidate->value,
        ];
    }

    public function label(): string
    {
        return config('votesys.role_labels.'.$this->value, ucfirst(str_replace('_', ' ', $this->value)));
    }
}
