<?php

namespace App\VoteSys;

use App\Enums\VoteSysRole;
use Illuminate\Http\Request;

class VoteSysAccess
{
    public function __construct(
        public VoteSysPrincipal $principal,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $principal = $request->attributes->get('votesys_principal');

        return new self($principal instanceof VoteSysPrincipal ? $principal : VoteSysPrincipal::guest());
    }

    public function role(): VoteSysRole
    {
        return $this->principal->role;
    }

    public function can(string $permission): bool
    {
        $matrix = config('votesys.permissions', []);
        $allowedRoles = $matrix[$permission] ?? null;

        if (! is_array($allowedRoles)) {
            return false;
        }

        $role = $this->principal->role->canonical();

        return in_array($role, $allowedRoles, true)
            || in_array($this->principal->role->value, $allowedRoles, true);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMap(): array
    {
        $map = [];

        foreach (array_keys(config('votesys.permissions', [])) as $permission) {
            $map[$permission] = $this->can($permission);
        }

        return $map;
    }

    public function authorize(string $permission): void
    {
        if (! $this->can($permission)) {
            abort(403, "You do not have permission: {$permission}");
        }
    }
}
