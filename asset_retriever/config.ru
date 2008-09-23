
require 'asset_retriever'

app = proc do |env|
 asset_retriever(env)
end

run app