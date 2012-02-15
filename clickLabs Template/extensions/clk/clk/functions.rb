require 'clk/functions/files'

module Compass::Clk
    module Functions
        include Functions::Files
    end
end

# Functions defined in this module are exported for usage in stylesheets
# (.sass and .scss documents).
#
module Sass::Script::Functions
    include Compass::Clk::Functions
end
