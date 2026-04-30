@extends('theme::layouts.1col')

@section('title', 'Provenance Timeline — ' . ($io->title ?? ''))

@section('content')
<div class="container py-3">
  <!-- Breadcrumb -->
  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $io->slug) }}">{{ $io->title ?? $io->slug }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('io.provenance', $io->slug) }}">Provenance</a></li>
      <li class="breadcrumb-item active">Timeline</li>
    </ol>
  </nav>

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-bar-chart-steps me-2"></i>Provenance Timeline</h4>
      <p class="text-muted mb-0">{{ $io->title ?? $io->slug }}</p>
    </div>
    <div>
      <a href="{{ route('io.provenance', $io->slug) }}" class="btn atom-btn-white me-2">
        <i class="bi bi-arrow-left me-1"></i>Back to Provenance
      </a>
      @auth
      <a href="{{ route('io.provenance', $io->slug) }}" class="btn atom-btn-white">
        <i class="bi bi-pencil me-1"></i> Edit Provenance
      </a>
      @endauth
    </div>
  </div>

  <!-- Timeline Visualization -->
  <div class="card shadow-sm mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0">
        <i class="bi bi-calendar-range me-2"></i>
        Visual Timeline
      </h5>
    </div>
    <div class="card-body">
      <div id="timeline-container" style="width: 100%; min-height: 400px; overflow-x: auto;">
        <svg id="provenance-timeline"></svg>
      </div>
      <div class="mt-3">
        <div class="d-flex flex-wrap gap-2">
          <span class="badge" style="background-color: #4caf50;">Creation</span>
          <span class="badge" style="background-color: #2196f3;">Sale/Purchase</span>
          <span class="badge" style="background-color: #9c27b0;">Gift/Donation</span>
          <span class="badge" style="background-color: #ff9800;">Inheritance</span>
          <span class="badge" style="background-color: #f44336;">Auction</span>
          <span class="badge" style="background-color: #607d8b;">Transfer</span>
          <span class="badge" style="background-color: #795548;">Loan</span>
          <span class="badge" style="background-color: #9e9e9e;">Other</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Events Table -->
  @if($events->isNotEmpty())
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0">
        <i class="bi bi-list-ul me-2"></i>
        Provenance Events
      </h5>
      <span class="badge bg-secondary">{{ $events->count() }}</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Date') }}</th>
              <th>{{ __('From') }}</th>
              <th>{{ __('To') }}</th>
              <th>{{ __('Location') }}</th>
              <th>{{ __('Certainty') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($events as $event)
            <tr>
              <td><span class="badge bg-primary">{{ $event->type_label ?? $event->event_type ?? '' }}</span></td>
              <td>{{ $event->date_display ?? $event->event_date ?? '' }}</td>
              <td>{{ $event->from_agent_name ?? $event->from ?? '-' }}</td>
              <td>{{ $event->to_agent_name ?? $event->to ?? '-' }}</td>
              <td>{{ $event->event_location ?? $event->location ?? '-' }}</td>
              <td>
                @php
                  $cert = $event->certainty ?? 'unknown';
                  $certClass = $cert === 'certain' ? 'success' : ($cert === 'uncertain' ? 'warning' : 'secondary');
                @endphp
                <span class="badge bg-{{ $certClass }}">{{ ucfirst($cert) }}</span>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  @else
  <div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    No provenance events have been recorded.
    @auth
    <a href="{{ route('io.provenance', $io->slug) }}" class="alert-link">Add events</a>
    @endauth
  </div>
  @endif
</div>

<!-- D3.js Timeline -->
<script src="https://d3js.org/d3.v7.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const timelineData = {!! $timelineData ?? '[]' !!};

  const categoryColors = {
    'creation': '#4caf50',
    'sale': '#2196f3',
    'gift': '#9c27b0',
    'inheritance': '#ff9800',
    'auction': '#f44336',
    'transfer': '#607d8b',
    'loan': '#795548',
    'theft': '#e91e63',
    'recovery': '#00bcd4',
    'event': '#9e9e9e'
  };

  if (timelineData.length === 0) {
    document.getElementById('timeline-container').innerHTML =
      '<div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>No events to display in timeline.</div>';
    return;
  }

  // Parse dates
  const parseDate = d3.timeParse('%Y-%m-%d');
  timelineData.forEach(d => {
    d.startDateParsed = d.startDate ? parseDate(d.startDate) : null;
  });

  const validData = timelineData.filter(d => d.startDateParsed);
  const container = document.getElementById('timeline-container');
  const margin = { top: 40, right: 200, bottom: 60, left: 60 };
  const width = Math.max(900, container.clientWidth) - margin.left - margin.right;

  if (validData.length === 0) {
    // Non-dated events - horizontal bars
    const barHeight = 45;
    const height = timelineData.length * barHeight + margin.top + margin.bottom;

    const svg = d3.select('#provenance-timeline')
      .attr('width', width + margin.left + margin.right)
      .attr('height', height);

    const g = svg.append('g')
      .attr('transform', `translate(${margin.left},${margin.top})`);

    const y = d3.scaleBand()
      .domain(timelineData.map((d, i) => i))
      .range([0, timelineData.length * barHeight])
      .padding(0.2);

    // Bars
    g.selectAll('.event-bar')
      .data(timelineData)
      .enter()
      .append('rect')
      .attr('class', 'event-bar')
      .attr('x', 0)
      .attr('y', (d, i) => y(i))
      .attr('width', width)
      .attr('height', y.bandwidth())
      .attr('fill', d => categoryColors[d.category] || categoryColors.event)
      .attr('opacity', 0.85)
      .attr('rx', 6);

    // Type labels (left)
    g.selectAll('.type-label')
      .data(timelineData)
      .enter()
      .append('text')
      .attr('x', 15)
      .attr('y', (d, i) => y(i) + y.bandwidth() / 2 + 5)
      .text(d => d.type)
      .attr('fill', '#fff')
      .attr('font-size', '14px')
      .attr('font-weight', 'bold');

    // Agent/details labels (right)
    g.selectAll('.detail-label')
      .data(timelineData)
      .enter()
      .append('text')
      .attr('x', width - 15)
      .attr('y', (d, i) => y(i) + y.bandwidth() / 2 + 5)
      .attr('text-anchor', 'end')
      .text(d => {
        let parts = [];
        if (d.from) parts.push(d.from);
        if (d.to) parts.push('\u2192 ' + d.to);
        if (d.location) parts.push('@ ' + d.location);
        return parts.join(' ') || d.label;
      })
      .attr('fill', '#fff')
      .attr('font-size', '12px');

    return;
  }

  // Timeline with dates
  const height = Math.max(350, validData.length * 60);

  const svg = d3.select('#provenance-timeline')
    .attr('width', width + margin.left + margin.right)
    .attr('height', height + margin.top + margin.bottom);

  const g = svg.append('g')
    .attr('transform', `translate(${margin.left},${margin.top})`);

  // X scale (time)
  const xExtent = d3.extent(validData, d => d.startDateParsed);
  const x = d3.scaleTime()
    .domain([d3.timeYear.offset(xExtent[0], -2), d3.timeYear.offset(xExtent[1] || new Date(), 2)])
    .range([0, width]);

  // Y scale
  const y = d3.scaleBand()
    .domain(validData.map((d, i) => i))
    .range([0, height - margin.top - margin.bottom])
    .padding(0.4);

  // Grid lines
  g.append('g')
    .attr('class', 'grid')
    .selectAll('line')
    .data(x.ticks(10))
    .enter()
    .append('line')
    .attr('x1', d => x(d))
    .attr('x2', d => x(d))
    .attr('y1', 0)
    .attr('y2', height - margin.top - margin.bottom)
    .attr('stroke', '#e0e0e0')
    .attr('stroke-dasharray', '3,3');

  // X axis
  g.append('g')
    .attr('transform', `translate(0,${height - margin.top - margin.bottom})`)
    .call(d3.axisBottom(x).ticks(10).tickFormat(d3.timeFormat('%Y')))
    .selectAll('text')
    .attr('font-size', '11px');

  // Event bars
  g.selectAll('.event-bar')
    .data(validData)
    .enter()
    .append('rect')
    .attr('class', 'event-bar')
    .attr('x', d => x(d.startDateParsed) - 3)
    .attr('y', (d, i) => y(i))
    .attr('width', 6)
    .attr('height', y.bandwidth())
    .attr('fill', d => categoryColors[d.category] || categoryColors.event)
    .attr('rx', 3);

  // Event circles
  g.selectAll('.event-marker')
    .data(validData)
    .enter()
    .append('circle')
    .attr('class', 'event-marker')
    .attr('cx', d => x(d.startDateParsed))
    .attr('cy', (d, i) => y(i) + y.bandwidth() / 2)
    .attr('r', 12)
    .attr('fill', d => categoryColors[d.category] || categoryColors.event)
    .attr('stroke', '#fff')
    .attr('stroke-width', 3);

  // Labels
  g.selectAll('.event-label')
    .data(validData)
    .enter()
    .append('text')
    .attr('x', d => x(d.startDateParsed) + 20)
    .attr('y', (d, i) => y(i) + y.bandwidth() / 2 + 5)
    .text(d => {
      let text = d.type;
      if (d.from || d.to) {
        text += ': ';
        if (d.from) text += d.from;
        if (d.from && d.to) text += ' \u2192 ';
        if (d.to) text += d.to;
      }
      return text;
    })
    .attr('font-size', '12px')
    .attr('fill', '#333');

  // Date labels
  g.selectAll('.date-label')
    .data(validData)
    .enter()
    .append('text')
    .attr('x', d => x(d.startDateParsed))
    .attr('y', (d, i) => y(i) - 5)
    .attr('text-anchor', 'middle')
    .text(d => d.startDate)
    .attr('font-size', '10px')
    .attr('fill', '#666');
});
</script>

<style>
.event-bar {
  transition: opacity 0.2s;
}
.event-bar:hover {
  opacity: 1 !important;
}
.event-marker {
  cursor: pointer;
  transition: r 0.2s;
}
.event-marker:hover {
  r: 15;
}
</style>
@endsection
