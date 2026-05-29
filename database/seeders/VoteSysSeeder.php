<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Position;
use Illuminate\Database\Seeder;

class VoteSysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Candidate::query()->exists()) {
            return;
        }

        $election = Election::query()->create([
            'name' => 'SSG Elections 2026',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $president = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'President',
            'max_selections' => 1,
        ]);

        $vp = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Vice President',
            'max_selections' => 1,
        ]);

        $secretary = Position::query()->create([
            'election_id' => $election->id,
            'name' => 'Secretary',
            'max_selections' => 1,
        ]);

        Candidate::query()->insert([
            [
                'position_id' => $president->id,
                'name' => 'Alex Rivera',
                'party' => 'Unity Party',
                'course' => 'BS Computer Science',
                'bio' => 'Dedicated to fostering unity and collaboration across all departments.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_id' => $president->id,
                'name' => 'Jordan Lee',
                'party' => 'Progressive Alliance',
                'course' => 'BS Engineering',
                'bio' => 'Advocating for innovative solutions and progressive change in student governance.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_id' => $vp->id,
                'name' => 'Taylor Brown',
                'party' => 'Unity Party',
                'course' => 'BA Psychology',
                'bio' => 'Committed to supporting student wellness and community building initiatives.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_id' => $vp->id,
                'name' => 'Morgan Davis',
                'party' => 'Progressive Alliance',
                'course' => 'BS Business Admin',
                'bio' => 'Focused on improving org operations and student services through transparent budgeting.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_id' => $secretary->id,
                'name' => 'Casey Wilson',
                'party' => 'Unity Party',
                'course' => 'BA Communication',
                'bio' => 'Strong communication skills and dedication to transparent governance.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'position_id' => $secretary->id,
                'name' => 'Jamie Martinez',
                'party' => 'Progressive Alliance',
                'course' => 'BS Accountancy',
                'bio' => 'Detail-oriented with expertise in record-keeping and organizational management.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
