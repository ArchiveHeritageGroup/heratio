{{--
    auth-res::_evidence-row - single evidence dimension row for a candidate (BS5).

    Args:
        $dimension : 'temporal' | 'geographic' | 'relational' | 'role'
                     | 'conflict' | 'hierarchical' | 'document_prior'
                     | 'co_occurring' | 'scale'
        $signal    : 'match' | 'conflict' | 'silent' | 'absent'
        $data      : array | null (per-dimension underlying values)
--}}
@php
    $signalConfig = [
        'match'    => ['cls' => 'success',   'icon' => 'bi-check-lg',     'label' => 'Match'],
        'conflict' => ['cls' => 'danger',    'icon' => 'bi-x-lg',         'label' => 'Conflict'],
        'silent'   => ['cls' => 'secondary', 'icon' => 'bi-dash',         'label' => 'Silent'],
        'absent'   => ['cls' => 'light',     'icon' => 'bi-circle',       'label' => 'Absent'],
    ];
    $cfg = $signalConfig[$signal] ?? $signalConfig['absent'];

    $dimensionLabel = match($dimension) {
        'temporal'       => 'Temporal',
        'geographic'     => 'Geographic',
        'relational'     => 'Relational',
        'role'           => 'Role',
        'conflict'       => 'Conflict',
        'hierarchical'   => 'Hierarchical',
        'document_prior' => 'Document prior',
        'co_occurring'   => 'Co-occurring',
        'scale'          => 'Scale',
        default          => ucfirst(str_replace('_', ' ', $dimension)),
    };

    // Best-effort one-line summary of the underlying data.
    $summary = '';
    if (is_array($data) && !empty($data)) {
        if (isset($data['reason'])) {
            $summary = (string) $data['reason'];
        } else {
            $summary = collect($data)->map(function ($v, $k) {
                if (is_array($v)) {
                    $v = implode(', ', array_map('strval', array_slice($v, 0, 3)));
                }
                return $k . ': ' . (string) $v;
            })->take(3)->implode('; ');
        }
        if (strlen($summary) > 160) {
            $summary = substr($summary, 0, 157) . '...';
        }
    }
@endphp
<tr>
    <td class="align-top" style="width: 28%;">
        <small>{{ $dimensionLabel }}</small>
    </td>
    <td class="align-top" style="width: 24%;">
        <span class="badge bg-{{ $cfg['cls'] }}{{ $cfg['cls'] === 'light' ? ' text-dark border' : '' }}">
            <i class="bi {{ $cfg['icon'] }} me-1"></i>{{ $cfg['label'] }}
        </span>
    </td>
    <td class="text-muted small align-top">{{ $summary }}</td>
</tr>
