@extends('emails._layout', ['subject' => 'U verifikasie-kode'])

@section('content')
    <h2 style="margin-top:0;">U verifikasie-kode</h2>

    <p>Hallo,</p>

    <p>Gebruik die kode hieronder om u aanmelding by {{ config('app.name', 'Heratio') }} te voltooi:</p>

    <p style="text-align: center; margin: 30px 0;">
        <span style="display:inline-block; font-size: 28px; letter-spacing: 0.4em; font-weight: 700; padding: 12px 20px; border: 1px solid #ddd; border-radius: 4px; background: #f8f8f8;">{{ $code }}</span>
    </p>

    <p>Bestemming: <strong>{{ $label }}</strong></p>

    <p class="muted">Hierdie kode verval oor {{ $ttlMinutes }} minute. As u dit nie aangevra het nie, kan u hierdie boodskap veilig ignoreer - u rekening is steeds veilig.</p>
@endsection
