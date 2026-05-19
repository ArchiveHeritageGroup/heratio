{{--
    auth-res::_evidence-row - single evidence dimension row for a candidate

    Args:
        $dimension : 'temporal' | 'geographic' | 'relational' | 'role'
                     | 'conflict' | 'hierarchical' | 'document_prior'
                     | 'co_occurring' | 'scale'
        $signal    : 'match' | 'conflict' | 'silent' | 'absent'
        $data      : array | null (per-dimension underlying values)
--}}
@php
    $signalColours = [
        'match' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
        'conflict' => 'bg-rose-100 text-rose-800 border-rose-300',
        'silent' => 'bg-slate-100 text-slate-600 border-slate-300',
        'absent' => 'bg-slate-50 text-slate-400 border-dashed border-slate-300',
    ];
    $signalLabel = match($signal) {
        'match' => 'Match',
        'conflict' => 'Conflict',
        'silent' => 'Silent',
        'absent' => 'Absent',
        default => $signal,
    };
    $signalCls = $signalColours[$signal] ?? $signalColours['absent'];

    $dimensionLabel = match($dimension) {
        'temporal' => 'Temporal',
        'geographic' => 'Geographic',
        'relational' => 'Relational',
        'role' => 'Role',
        'conflict' => 'Conflict',
        'hierarchical' => 'Hierarchical',
        'document_prior' => 'Document prior',
        'co_occurring' => 'Co-occurring',
        'scale' => 'Scale',
        default => $dimension,
    };

    // Best-effort one-line summary of the underlying data.
    $summary = '';
    if (is_array($data) && !empty($data)) {
        $summary = collect($data)->map(function ($v, $k) {
            if (is_array($v)) {
                $v = implode(', ', array_map('strval', array_slice($v, 0, 3)));
            }
            return $k . ': ' . (string) $v;
        })->take(3)->implode('; ');
    }
@endphp
<tr class="text-sm">
    <td class="px-3 py-1.5 text-slate-700 align-top w-40">{{ $dimensionLabel }}</td>
    <td class="px-3 py-1.5 align-top w-28">
        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium {{ $signalCls }}">
            {{ $signalLabel }}
        </span>
    </td>
    <td class="px-3 py-1.5 text-slate-500 align-top text-xs">{{ $summary }}</td>
</tr>
