<footer class="ahg-site-footer text-center py-3" role="contentinfo">
  <div class="container">
    @if(!empty($themeData['footerText'] ?? ''))
      <small>{{ $themeData['footerText'] }}</small>
    @else
      <small>&copy; {{ date('Y') }} {{ config('app.name', 'Heratio') }}. Powered by <strong>Heratio</strong>.</small>
    @endif
  </div>
</footer>
