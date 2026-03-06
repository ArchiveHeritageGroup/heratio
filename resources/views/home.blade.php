<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heratio - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <span class="navbar-brand">Heratio</span>
            <div class="d-flex align-items-center text-white">
                <span class="me-3">{{ $user->getDisplayName() }} ({{ $user->email }})</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
                </form>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <h2>Welcome to Heratio</h2>
        <p>Pure Laravel replacement for AtoM. Phase 1 Foundation is operational.</p>

        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>{{ \AhgCore\Models\QubitInformationObject::where('id', '!=', 1)->count() }}</h3>
                        <p class="text-muted">Descriptions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>{{ \AhgCore\Models\QubitRepository::where('id', '!=', 6)->count() }}</h3>
                        <p class="text-muted">Repositories</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>{{ \AhgCore\Models\QubitDigitalObject::count() }}</h3>
                        <p class="text-muted">Digital Objects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3>{{ \AhgCore\Models\QubitUser::count() }}</h3>
                        <p class="text-muted">Users</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5>Your Groups</h5>
            <ul>
                @foreach($user->groups as $group)
                    <li>{{ $group->getName('en') }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</body>
</html>
