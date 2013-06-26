set :application, "rps_competition_plugin"
set :repository,  "git@bitbucket.org:petervanderdoes/rps-competitions.git"
set :scm, :git
set :apc_webroot,  ""
set :url_base, "http://rps.avirtualhome.com/content/themes/suffu-rps/"

role :web, "rps.avirtualhome.com"   # Your HTTP server, Apache/etc
role :app, "rps.avirtualhome.com"   # This may be the same as your `Web` server
role :db,  "rps.avirtualhome.com", :primary => true # This is where Rails migrations will run

set :deploy_to, "/home/pdoes/capistrano/rps/plugin"
set :use_sudo, false
set :deploy_via, :remote_cache
set :copy_exclude, [".git", ".gitmodules", ".DS_Store", ".gitignore", "sass", "Capfile", "config"]
set :keep_releases, 5

set :branch, fetch(:branch, "develop")

# if you want to clean up old releases on each deploy uncomment this:
after "deploy:restart", "deploy:cleanup"
after "deploy", "apc:clear_cache"
