#
# This is an asset retriever for to be used as a companion to the PHP hot_cache script.
#
# Dependencies:
# * Thin web server (and all its dependencies: ruby, rubygems etc.)
#

current_path = File.dirname(__FILE__)
$:.unshift(current_path)

require 'rubygems'
require 'open-uri'
require 'yaml'

$settings = YAML.load(File.open('../settings.yml', 'r'))

def asset_retriever(env)
  asset_url = env['PATH_INFO'].gsub(/^\/*/, '')
  response_hash = { "Content-Type" => "text/plain", "Content-Length" => '0' }
  unless asset_url == 'favicon.ico'
    dump(asset_url)
  end
  [200, response_hash, [""]]
end 

def dump(asset_url)
  asset_file = asset_url.gsub("http://", '').gsub("https://", '').gsub('/', '__')
  dump_dir = File.expand_path("#{File.dirname(__FILE__)}/../#{$settings['dump_dir']}")
  dump_file = "#{dump_dir}/#{asset_file}"
  download_command = "#{$settings['asset_retriever']['download_command']}" % [
    asset_url, dump_file
  ]
  `#{download_command}`
end

# TODO prune!
def prune
  
end

