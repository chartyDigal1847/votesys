# Voting System Setup Guide

## Quick Start

This app uses **MySQL** (for example MariaDB/MySQL in XAMPP). Create empty databases before migrating, for example:

```sql
CREATE DATABASE myapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE myapp_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Copy `.env.example` to `.env`, set `DB_DATABASE` / credentials, then run migrations.

Follow these steps to get the voting system running:

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Reset and Seed Database

```bash
php artisan migrate:fresh --seed
```

This will:
- Drop all tables and recreate them with fixed migrations
- Seed sample election data (SSG Elections 2026)
- Create 3 positions: President, Vice President, Secretary
- Create 6 candidate candidates with full details

### 3. Start Development Server

**Terminal 1 - Start Laravel Development Server:**
```bash
php artisan serve
```
(Runs on http://localhost:8000)

**Terminal 2 - Start Vite Asset Server:**
```bash
npm run dev
```
(Runs on http://localhost:5173)

## Access the Voting System

- **Main Voting Page:** http://localhost:8000/votesys
- **View All Candidates:** http://localhost:8000/vote

## Database Schema

### Elections Table
- `id` - Primary key
- `name` - Election name
- `is_active` - Boolean flag for active elections
- `starts_at` - Election start time
- `ends_at` - Election end time

### Positions Table
- `id` - Primary key
- `election_id` - Foreign key to elections
- `name` - Position name (President, VP, Secretary)
- `max_selections` - Max candidates per student

### Candidates Table
- `id` - Primary key
- `position_id` - Foreign key to positions
- `name` - Candidate name
- `party` - Political party/slate
- `course` - Academic course
- `bio` - Candidate biography

### Votes Table
- `id` - Primary key
- `election_id` - Foreign key to elections
- `position_id` - Foreign key to positions
- `candidate_id` - Foreign key to candidates
- `student_id` - Student ID (string)
- Unique constraint: (election_id, position_id, student_id)

## Key Features

✅ **Active Election Display** - Shows most recent active election, or latest if none active
✅ **Vote Recording** - Students vote per position with validation
✅ **Duplicate Prevention** - One vote per student per position per election
✅ **Vote Counting** - Real-time vote tallies by position
✅ **Transaction Safety** - All votes processed atomically

## Routes

| Method | Route | Controller | Purpose |
|--------|-------|-----------|---------|
| GET | `/votesys` | VoteSysController@index | Display voting dashboard |
| POST | `/votesys/vote` | VoteSysController@submit | Submit votes |
| GET | `/vote` | VoteController@index | View all candidates |

## Troubleshooting

### Database doesn't exist
Create the MySQL database (see Quick Start), set `DB_*` in `.env`, then:

```bash
php artisan migrate:fresh --seed
```

### Migrations fail
- Ensure you're using PHP 8.1+
- Check database permissions
- Run `php artisan migrate:fresh --seed` to rebuild

### Voting page shows no elections
- Ensure seeder ran successfully
- Check: `php artisan tinker` then `Election::all()`

### Port 8000 already in use
```bash
php artisan serve --port=8001
```

## Testing Votes

To vote as a student:
1. Go to http://localhost:8000/votesys
2. Enter a Student ID (any 5+ character string, e.g., "STU001")
3. Select one candidate per position
4. Click "Submit Vote"
5. Try voting again with same Student ID - should prevent duplicate

## Notes

- Student IDs are strings (not database user IDs)
- Students can change their vote by submitting again (updateOrCreate)
- Active elections are prioritized; falls back to latest if none active
- All fields are properly validated server-side
