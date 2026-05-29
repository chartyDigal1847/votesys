<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->after('name');
            $table->text('description')->nullable()->after('status');
            $table->string('created_by_external_id', 64)->nullable()->after('description');
            $table->timestamp('voting_starts_at')->nullable()->after('ends_at');
            $table->timestamp('voting_ends_at')->nullable()->after('voting_starts_at');
            $table->timestamp('results_released_at')->nullable()->after('voting_ends_at');
            $table->softDeletes();
            $table->index('status');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->string('status', 24)->default('approved')->after('position_id');
            $table->string('applicant_external_id', 64)->nullable()->after('status');
            $table->string('approved_by_external_id', 64)->nullable()->after('applicant_external_id');
            $table->timestamp('approved_at')->nullable()->after('approved_by_external_id');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->softDeletes();
            $table->index('status');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->string('voter_external_id', 64)->nullable()->after('student_id');
            $table->string('vote_hash', 64)->nullable()->after('candidate_id');
            $table->boolean('is_locked')->default(true)->after('vote_hash');
            $table->index('voter_external_id');
        });

        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('tagline')->nullable();
            $table->text('platform')->nullable();
            $table->json('campaign_links')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('student_voters', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 64)->unique();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('course')->nullable();
            $table->boolean('is_eligible')->default(true);
            $table->timestamps();
        });

        Schema::create('vote_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voter_external_id', 64);
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();
            $table->index(['election_id', 'voter_external_id']);
        });

        Schema::create('election_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('vote_count')->default(0);
            $table->decimal('vote_percentage', 5, 2)->default(0);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
            $table->unique(['election_id', 'position_id', 'candidate_id']);
        });

        Schema::create('election_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('changed_by_external_id', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('election_officers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->unique(['election_id', 'external_id']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('action', 64)->nullable()->after('id');
            $table->foreignId('election_id')->nullable()->after('action')->constrained()->nullOnDelete();
            $table->string('actor_external_id', 64)->nullable()->after('election_id');
            $table->string('subject_type', 64)->nullable()->after('actor_external_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
        });

        Schema::create('event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name');
            $table->string('source_service', 64);
            $table->string('schema_version', 16)->default('1.0');
            $table->uuid('correlation_id')->nullable();
            $table->json('payload');
            $table->string('signature', 128)->nullable();
            $table->string('nonce', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_external_id', 64)->index();
            $table->string('type', 64);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('event_outbox');
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('election_id');
            $table->dropColumn(['action', 'actor_external_id', 'subject_type', 'subject_id']);
        });
        Schema::dropIfExists('election_officers');
        Schema::dropIfExists('election_status_history');
        Schema::dropIfExists('election_results');
        Schema::dropIfExists('vote_logs');
        Schema::dropIfExists('student_voters');
        Schema::dropIfExists('candidate_requirements');
        Schema::dropIfExists('candidate_profiles');
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['voter_external_id', 'vote_hash', 'is_locked']);
        });
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'applicant_external_id', 'approved_by_external_id', 'approved_at', 'rejection_reason']);
        });
        Schema::table('elections', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'description', 'created_by_external_id', 'voting_starts_at', 'voting_ends_at', 'results_released_at']);
        });
    }
};
