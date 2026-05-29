<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All candidates | VoteSys</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --main-bg: #f5f0e8;
            --card-bg: #ffffff;
            --border-light: #e8e0d4;
            --burgundy: #722f37;
            --gold: #c9a227;
            --text-primary: #2d1810;
            --text-muted: #9a8b7e;
            --radius-md: 14px;
        }
        body { font-family: Inter, system-ui, sans-serif; background: var(--main-bg); color: var(--text-primary); margin: 0; min-height: 100vh; }
        .wrap { max-width: 920px; margin: 0 auto; padding: 28px 20px 48px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
        .top h1 { font-size: 1.35rem; margin: 0; color: var(--burgundy); }
        .top-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 50px; font-weight: 600; font-size: 0.9rem;
            text-decoration: none; border: none; cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #5a1e24, var(--burgundy));
            color: var(--gold);
        }
        .btn-ghost { background: var(--card-bg); color: var(--burgundy); border: 1px solid var(--border-light); }
        .btn-danger { background: #b91c1c; color: white; font-size: 0.85rem; padding: 8px 14px; }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            border: 1px solid rgba(90, 30, 36, 0.08);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border-light); }
        th { background: rgba(114, 47, 55, 0.06); font-weight: 600; color: var(--burgundy); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
        tr:last-child td { border-bottom: none; }
        .muted { color: var(--text-muted); font-size: 0.85rem; }
        .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .empty { padding: 40px; text-align: center; color: var(--text-muted); }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1><i class="fas fa-users-cog"></i> Manage candidates</h1>
        <div class="top-actions">
            <a href="{{ route('votesys', ['panel' => 'candidates']) }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> VoteSys</a>
            <a href="{{ route('candidates.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Add candidate</a>
        </div>
    </div>

    <div class="card">
        @if ($candidates->isEmpty())
            <div class="empty">No candidates yet. <a href="{{ route('candidates.create') }}" style="color:var(--burgundy); font-weight:600;">Create one</a>.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Party</th>
                        <th>Course</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($candidates as $c)
                        <tr>
                            <td><strong>{{ $c->name }}</strong></td>
                            <td>{{ $c->position->election->name ?? '—' }} — {{ $c->position->name }}</td>
                            <td class="muted">{{ $c->party ?? '—' }}</td>
                            <td class="muted">{{ $c->course ?? '—' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('candidates.edit', $c) }}" class="btn btn-ghost" style="padding:6px 12px; font-size:0.8rem;"><i class="fas fa-edit"></i> Edit</a>
                                    <form method="POST" action="{{ route('candidates.destroy', $c) }}" style="display:inline;" onsubmit="return confirm('Remove {{ addslashes($c->name) }} from the election?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
</body>
</html>
