<?php

namespace App\VoteSys;

use App\Enums\VoteSysRole;

readonly class VoteSysPrincipal
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public VoteSysRole $role,
    ) {}

    public static function guest(): self
    {
        return new self(
            id: 'guest',
            email: '',
            name: 'Guest',
            role: VoteSysRole::Student,
        );
    }

    /**
     * @param  array{id?: string, email?: string, name?: string, role?: string}|null  $user
     */
    public static function fromPortalUser(?array $user): self
    {
        if (! $user) {
            return self::guest();
        }

        return new self(
            id: (string) ($user['id'] ?? $user['email'] ?? 'unknown'),
            email: strtolower((string) ($user['email'] ?? '')),
            name: (string) ($user['name'] ?? 'User'),
            role: VoteSysRole::fromString((string) ($user['role'] ?? 'student')),
        );
    }

    /**
     * @return array{id: string, email: string, name: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role->canonical(),
        ];
    }

    /**
     * @param  array{id: string, email: string, name: string, role: string}  $data
     */
    public static function fromSessionArray(array $data): self
    {
        return new self(
            id: $data['id'],
            email: $data['email'],
            name: $data['name'],
            role: VoteSysRole::fromString($data['role']),
        );
    }
}
