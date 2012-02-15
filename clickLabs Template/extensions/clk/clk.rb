# Local development requires we have the current directory on $LOAD_PATH.
$: << File.dirname(__FILE__)

module Compass::Clk

end

require 'clk/functions'
require 'clk/configuration'
# Register Compass Magick as a Compass framework.
#
# @see http://compass-style.org/docs/tutorials/extensions/
Compass::Frameworks.register('clk',
                             :stylesheets_directory => File.join(File.dirname(__FILE__), 'stylesheets'),
                             :templates_directory   => File.join(File.dirname(__FILE__), 'templates')
                             )

# Extend Compass configuration with new properties (defined by Compass Magick
# and plugins).
Compass::Configuration::Data.send(:include, Compass::Clk::Configuration)
