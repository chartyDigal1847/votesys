<?php

namespace Tests\Feature;

use App\Models\Candidate;
use Database\Seeders\VoteSysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteSysRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VoteSysSeeder::class);
        \App\Models\Election::query()->update(['status' => 'voting_open']);
    }

    /**
     * @return array{id: string, email: string, name: string, role: string}
     */
    private function principal(string $role): array
    {
        return [
            'id' => "{$role}-user",
            'email' => "{$role}@votesys.test",
            'name' => ucfirst($role),
            'role' => $role,
        ];
    }

    private function actingAsVoteSysRole(string $role): static
    {
        $principal = $this->principal($role);

        return $this->withHeaders([
            'X-VoteSys-Role' => $principal['role'],
            'X-VoteSys-User-Id' => $principal['id'],
            'X-VoteSys-User-Email' => $principal['email'],
            'X-VoteSys-User-Name' => $principal['name'],
        ]);
    }

    public function test_home_page_returns_successful_response(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_bootstrap_returns_permissions_for_admin(): void
    {
        $response = $this->actingAsVoteSysRole('admin')
            ->getJson('/votesys/api/bootstrap');

        $response->assertOk()
            ->assertJsonPath('role', 'admin');

        $permissions = $response->json('permissions');
        $this->assertTrue($permissions['candidates.delete'] ?? false);
        $this->assertTrue($permissions['candidates.create'] ?? false);
    }

    public function test_student_cannot_access_candidate_management_routes(): void
    {
        $candidate = Candidate::query()->first();
        $this->assertNotNull($candidate);

        $this->actingAsVoteSysRole('student')
            ->get('/votesys/candidates')
            ->assertForbidden();

        $this->actingAsVoteSysRole('student')
            ->deleteJson("/votesys/api/candidates/{$candidate->id}")
            ->assertForbidden();
    }

    public function test_hr_can_create_but_not_delete_candidates_via_api(): void
    {
        $candidate = Candidate::query()->first();
        $this->assertNotNull($candidate);

        $this->actingAsVoteSysRole('hr')
            ->get('/votesys/candidates/create')
            ->assertOk();

        $this->actingAsVoteSysRole('hr')
            ->deleteJson("/votesys/api/candidates/{$candidate->id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_candidate_via_api(): void
    {
        $candidate = Candidate::query()->first();
        $this->assertNotNull($candidate);

        $this->actingAsVoteSysRole('admin')
            ->deleteJson("/votesys/api/candidates/{$candidate->id}")
            ->assertOk();
    }

    public function test_student_can_cast_vote(): void
    {
        $electionId = 1;

        $response = $this->actingAsVoteSysRole('student')
            ->postJson('/votesys/api/vote', [
                'election_id' => $electionId,
                'selections' => [
                    1 => 1,
                    2 => 3,
                    3 => 5,
                ],
            ]);

        $response->assertOk();
    }
}
