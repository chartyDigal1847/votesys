<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add candidate | VoteSys</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --main-bg: #f5f0e8;
            --card-bg: #ffffff;
            --border-light: #e8e0d4;
            --burgundy: #722f37;
            --gold: #c9a227;
            --text-primary: #2d1810;
            --text-secondary: #6b5b50;
            --radius-md: 14px;
        }
        body { font-family: Inter, system-ui, sans-serif; background: var(--main-bg); color: var(--text-primary); margin: 0; min-height: 100vh; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 28px 20px 48px; }
        .top { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; flex-wrap: wrap; }
        .top h1 { font-size: 1.35rem; margin: 0; color: var(--burgundy); }
        .top a { color: var(--burgundy); font-weight: 600; text-decoration: none; }
        .top a:hover { text-decoration: underline; }
        .card {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            padding: 28px;
            border: 1px solid rgba(90, 30, 36, 0.08);
            box-shadow: 0 2px 12px rgba(90, 30, 36, 0.06);
        }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; }
        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .form-input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.15); }
        .field-help { margin: 8px 0 0; color: var(--text-secondary); font-size: 0.82rem; }
        .empty-position-state { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border: 1px solid rgba(114,47,55,.16); background: #fff7df; color: #5f3b06; border-radius: 8px; font-size: 0.9rem; font-weight: 600; }
        .btn-submit {
            margin-top: 8px;
            background: linear-gradient(135deg, #5a1e24, var(--burgundy));
            color: var(--gold);
            border: none;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            width: 100%;
        }
        .btn-submit:hover { opacity: 0.95; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1><i class="fas fa-user-plus"></i> Add candidate</h1>
        <a href="{{ route('votesys', ['panel' => 'candidates']) }}"><i class="fas fa-arrow-left"></i> Back to VoteSys</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data">
            @csrf
            @include('candidates._form', ['candidate' => null])
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save candidate</button>
        </form>
    </div>
</div>
<script>
window.previewCandidatePhoto = function (input) {
    var img = document.getElementById('candidatePhotoPreview');
    if (!img || !input.files || !input.files[0]) return;
    img.src = URL.createObjectURL(input.files[0]);
};
</script>
</body>
</html>
