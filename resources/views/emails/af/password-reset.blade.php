@extends('emails._layout', ['subject' => 'Wagwoord-herstel versoek'])

@section('content')
    <h2 style="margin-top:0;">Wagwoord-herstel versoek</h2>

    <p>Hallo {{ $username }},</p>

    <p>U het versoek om u wagwoord te herstel. Klik op die knoppie hieronder om 'n nuwe wagwoord te stel:</p>

    <p style="text-align: center; margin: 30px 0;">
        <a href="{{ $resetUrl }}" class="btn">Herstel wagwoord</a>
    </p>

    <p>As die knoppie hierbo nie werk nie, kopieer en plak die volgende URL in u blaaier:</p>
    <p style="word-break: break-all;"><a href="{{ $resetUrl }}">{{ $resetUrl }}</a></p>

    <p class="muted">Hierdie skakel verval oor 1 uur. As u nie 'n wagwoord-herstel aangevra het nie, kan u hierdie e-pos veilig ignoreer.</p>
@endsection
