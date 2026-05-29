<?php

namespace App\Enums;

enum ElectionStatus: string
{
    case Draft = 'draft';
    case CandidateRegistration = 'candidate_registration';
    case CandidateReview = 'candidate_review';
    case Approved = 'approved';
    case VotingOpen = 'voting_open';
    case VotingClosed = 'voting_closed';
    case ResultProcessing = 'result_processing';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::CandidateRegistration => 'Candidate Registration',
            self::CandidateReview => 'Candidate Review',
            self::Approved => 'Approved',
            self::VotingOpen => 'Voting Open',
            self::VotingClosed => 'Voting Closed',
            self::ResultProcessing => 'Result Processing',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function canVote(): bool
    {
        return $this === self::VotingOpen;
    }

    public function canRegisterCandidates(): bool
    {
        return in_array($this, [self::CandidateRegistration, self::CandidateReview], true);
    }
}
