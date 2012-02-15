module Compass::Clk
  # Configuration methods to allow plugins to register new configurable
  # properties.
  module Configuration
    extend Compass::Configuration::Inheritance::ClassMethods
    extend self
  end
end
