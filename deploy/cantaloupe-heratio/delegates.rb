# Heratio's own Cantaloupe (22 Nov 2026, Dr Johan Pieterse: nothing is shared
# with PSIS except the MySQL server). Serves Heratio hosts only.
#
# An identifier resolves to one file under the host's roots or to nothing:
# no absolute paths, no other host's folders. Access is decided in
# pre_authorize, the only hook that also runs for info.json. A failed check
# refuses the request.
require 'digest'
require 'json'
require 'net/http'
require 'openssl'
require 'uri'

class CustomDelegate
  attr_accessor :context

  INSTANCES = {
    'heratio.org' => {
      base: '/mnt/nas/heratio/',
      # ponytail: archive/ stays only until the cut-over swaps uploads/r for
      # Heratio's copy; remove it then.
      roots: ['/mnt/nas/heratio/uploads/', '/mnt/nas/heratio/archive/'],
      check: 'https://127.0.0.1/privacy/iiif-check'
    },
    'atom.theahg.co.za' => {
      base: '/usr/share/nginx/atom.atom-old/',
      roots: ['/usr/share/nginx/atom.atom-old/uploads/', '/mnt/nas/heratio/atom/'],
      check: 'https://127.0.0.1/privacy/iiif-check'
    },
    '192.168.0.112' => {
      base: '/mnt/nas/heratio-dev/',
      roots: ['/mnt/nas/heratio-dev/'],
      check: 'http://127.0.0.1:8090/privacy/iiif-check'
    }
  }.freeze
  INSTANCES_WITH_ALIASES = INSTANCES.merge('www.heratio.org' => INSTANCES['heratio.org']).freeze

  AUTH_CACHE_TTL = 60 # seconds

  def self.auth_cache
    @auth_cache ||= {}
  end

  def source(options = {})
    'FilesystemSource'
  end

  def filesystemsource_pathname(options = {})
    instance = current_instance
    return nil unless instance

    relative = context['identifier'].to_s.gsub('_SL_', '/')
    return nil if relative.start_with?('/') || relative.split('/').any? { |s| s == '.' || s == '..' }
    return nil unless relative.start_with?('uploads/')

    path = instance[:base] + relative
    return nil unless File.file?(path)

    real = File.realpath(path)
    instance[:roots].any? { |root| real.start_with?(root) } ? real : nil
  rescue SystemCallError
    nil
  end

  def pre_authorize(options = {})
    instance = current_instance
    return false unless instance

    identifier = context['identifier'].to_s
    cookies = (headers['Cookie'] || '').to_s
    key = "#{host}:#{identifier}:#{Digest::SHA256.hexdigest(cookies)}"
    cached = self.class.auth_cache[key]
    return cached[:result] if cached && cached[:expires] > Time.now

    uri = URI.parse(instance[:check])
    uri.query = URI.encode_www_form(identifier: identifier)
    http = Net::HTTP.new(uri.host, uri.port)
    http.use_ssl = (uri.scheme == 'https')
    http.verify_mode = OpenSSL::SSL::VERIFY_NONE # loopback; the certificate names the site
    http.open_timeout = 2
    http.read_timeout = 3
    request = Net::HTTP::Get.new(uri)
    request['Host'] = host
    request['Cookie'] = cookies unless cookies.empty?
    response = http.request(request)
    return false unless response.code.to_i == 200

    result = JSON.parse(response.body)['allowed'] == true
    self.class.auth_cache.delete_if { |_, v| v[:expires] < Time.now } if self.class.auth_cache.size > 1000
    self.class.auth_cache[key] = { result: result, expires: Time.now + AUTH_CACHE_TTL }
    result
  rescue StandardError => e
    STDERR.puts "[Cantaloupe] check failed for #{identifier}: #{e.message} - refusing"
    false
  end

  def authorize(options = {})
    true # decided in pre_authorize
  end

  def extra_iiif_information_response_keys(options = {}); {}; end
  def extra_iiif2_information_response_keys(options = {}); {}; end
  def extra_iiif3_information_response_keys(options = {}); {}; end
  def azurestoragesource_blob_key(options = {}); nil; end
  def httpsource_resource_info(options = {}); nil; end
  def jdbcsource_database_identifier(options = {}); nil; end
  def jdbcsource_media_type(options = {}); nil; end
  def jdbcsource_lookup_sql(options = {}); nil; end
  def s3source_object_info(options = {}); nil; end
  def overlay(options = {}); nil; end
  def redactions(options = {}); []; end
  def metadata(options = {}); nil; end

  private

  def headers
    context['request_headers'] || {}
  end

  # Host only: nginx sets it, and a client can send X-Forwarded-Host.
  def host
    (headers['Host'] || headers['host'] || '').to_s.split(':').first.to_s.downcase
  end

  def current_instance
    INSTANCES_WITH_ALIASES[host]
  end
end
