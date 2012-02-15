module Compass::Clk
    module Functions
        module Files
            def clk_file_exisits(file_name)
                if !File.exist?(file_name.value)
                    Sass::Script::Bool.new(true)
                else
                    Sass::Script::Bool.new(false)
                end
            end
        end
    end
end

