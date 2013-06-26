namespace :nginx do
	desc "Restarts nginx"
	task :restart do
		run "sudo /etc/init.d/nginx reload"
	end
end

namespace :phpfpm do
	desc" Restarts PHP-FPM"
	task :restart do
		run "sudo /etc/init.d/php-fpm restart"
	end
end

namespace :apc do
	desc <<-DESC
		Create a temporary PHP file to clear APC cache, call it (using curl) and removes it
		This task must be triggered AFTER the deployment to clear APC cache
	DESC
	task :clear_cache, :roles => :app do
		apc_file = "#{current_release}#{apc_webroot}/apc_clear.php"
		curl_options = "-s"
		put "<?php apc_clear_cache(); apc_clear_cache('user'); apc_clear_cache('opcode'); ?>", apc_file, :mode => 0644
		run "curl #{curl_options} #{url_base}/apc_clear.php && rm -f #{apc_file}"
	end
end
