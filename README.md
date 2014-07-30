TogglToJira
===========

## Description ##
TogglToJira provides integration between Toggl[http://toggl.com] time tracking software and
Jira[https://www.atlassian.com/software/jira] project management software

## Pre-Requisites ##
* Composer

## Install ##
1. Download and unzip (or clone using git) the TogglToJira project. It can be stored in your home directory,
   a scripts folder or anywhere else on your drive.
2. Change into the directory and run `composer install`
3. Rename the following files and update them as appropriate:
  * jira.yaml.sample -> jira.yaml
  * toggl.yaml.sample -> toggl.yaml
    * If you intend to make changes, please change user_agent to your email address so that Toggl can let you
      know if you are doing anything really bad

## Usage ##
To run TogglToJira, execute `php bootstrap.php 2014-07-29` from the command line in the root directory of your
TogglToJira project

## License ##
TogglToJira by Joe Constant is licensed under the MIT[http://opensource.org/licenses/MIT] license
