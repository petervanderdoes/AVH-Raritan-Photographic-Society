## Changelog

#### Version 2.0.17-dev.51
* Add flexibility to the image size for the client.
  We can now change the size of the image that's being used by the client rather quickly.
  It's a matter of adding the size to the image_size array and updating the constant that set the client image size.
  
#### Version 2.0.16
* Fix layput of Entries and Competitions.
* When removing an entrie the main file is not deleted.

#### Version 2.0.14
* Undefined index error.    
  Rearrange the conditions to see if buttons need to be displayed.
* Show message when Banquet Entries have been updated.

#### Version 2.0.13
* Adding Banquet Entries fails.  
  Instead of adding the buttons to the form when needed, it's better to add them to the entity and remove them when needed.

#### Version 2.0.12
* Fix the Banquet Entries Page.

#### Version 2.0.11
* Display message when there are no open competitions.

#### Version 2.0.10
* Caption for photo's in the gallery is wrong.
* RPS Client: Retrieving photos results in 404 error.
* RPS Client: Upload of scores does not work.

#### Version 2.0.9
* Make use of ImageMagick for better quality and less memory usage.

#### Version 2.0.7
* Fix Banquet Entries

#### Version 2.0.5
* Replace intervention/image library with imagine/imagine from my own Github account.
* The Sitemap filter for the frontpage only has to run when the sitemap is of the type post.

#### Version 2.0.4
* Split the sitemap for entries and winners per year.
* Send 404 response code for the old entries and winners sitemap.
* Use Symfony2 Forms
* Fix Fancybox popup

#### Version 2.0.3
* Add images to the sitemap.
* Enhance the entry in the sitemap of the front page.

#### Version 2.0.2
* Implement Social Network share buttons.
* Bugfix: Titles not displayed for photos in masonry gallery.
* Improve title display for the Dynamic Pages

#### Version 2.0.1
* Problem with .gitignore

#### Version 2.0.0
* Start using twig templates.
* Creation of common thumbnails fails
* Sitemap failure

#### Version 1.5.0
* Improve the page "Monthly Entries".

#### Version 1.4.12
* Ajax call fails.

#### Version 1.4.11
* Fix upload entry problem.
* Extend ability to edit entries in admin.  
  Have the ability to not only rename a entry but also change the classification and/or medium.

#### Version 1.4.10
* Fix display of error message.
* Fix checkbox problem.
* Fix bulk delete.
* Fix unneeded competitions when adding
* Fix for mixed classifications.  
  When a user is has a different class for B&W compared to Color the classification is wrong.

#### Version 1.4.9
* Improve masonry layout.

#### Version 1.4.3
* Allow more photos to displayed horizontally.

#### Version 1.4.0
* Improved check for uploaded files to the competitions.
* Use image library.
* Refactor RpsDb class

#### Version 1.3.11
* The stripslashes function is no longer needed
* Fix XML file. Make sure UTF-8 encoding is done.
* Use object for data retrieval instead of objects.

#### Version 1.3.10
* Use Request class

#### Version 1.3.6
* Reworked for update on AVH Framework

#### Version 1.3.5
* Updated for usage of the github-updater plugin.
* Fixed license

#### Version 1.3.4
* Update Shortcodes that display photos.  
  They now show as gallery.
* Fix double dash in thumbail URL at times.

#### Version 1.3.3
* Fix invalid method call
* Update to PSR-2 coding standards

#### Version 1.3.2
* Show closing date at competition list.
* Remove unnecessary CSS classes
* Removes unnecessary redirect.
* Clean up code to remove PHP notices.

#### Version 1.3.1
* Preparation for new development cycle.

#### Version 1.3.0
* Remove AVH Framework from plugin.  
  AVH Framework has been created as a seperate plugin.
* Bugfix: New users don't see any competitions.

#### Version 1.2.7
* Bugfix: Methods not found

#### Version 1.2.2
* Enhancement: Close competitions when needed.

#### Version 1.2.1
* Bugfix: Competitions show up in duplicates

#### Version 1.2.0
* When a member has two different classifications the submission form is wrong.

#### Version 1.0.0
* Initial version.
