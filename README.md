Timelog (formerly TogglToJira)
===========

## Description ##
Timelog provides integration between [Toggl](http://toggl.com) time tracking software, 
[Jira](https://www.atlassian.com/software/jira) project management software, and [Replicon](http://www.replicon.com/) timesheet reporting software

## Pre-Requisites ##
* Composer
* A [Toggl](https://toggl.com) Account
  * Have your Toggl account set to not use start/stop times (I have not tested what will happen if you use start/stop times, but I suspect it will mess up your Toggl data)
  * The description you use for your time entry in Toggl becomes the worklog description in Jira as well as the comment in Replicon
  * You have to set the Jira ticket number as a tag on the time entry in Toggl
  * The time entry will be updated with a Jira tag so that it is not logged in Jira again
* A Jira account with REST access
* A Replicon account with API access enabled

## Install ##
1. Download and unzip (or clone using git) the Timelog project. It can be stored in your home directory,
   a scripts folder or anywhere else on your drive.
2. Change into the directory and run `composer install`
3. Rename the following files and update them as appropriate:
  * jira.yaml.sample -> jira.yaml
  * toggl.yaml.sample -> toggl.yaml
    * If you intend to make changes, please change user_agent to your email address so that Toggl can let you
      know if you are doing anything really bad
  * replicon.yaml.sample -> replicon.yaml
    * hostname is an optional field to override which server you connect to.  It defaults to na8.replicon.com.  Remove the line if not changing it

## Usage ##
To run Timelog, from the command line in the root directory of your Timelog project execute 
```
./timelog --jira 2014-07-29 2014-07-30
```
or
```
./timelog --replicon 2014-07-29 2014-07-30
```
or
```
./timelog --all 2014-07-29 2014-07-30
```
End date is optional.  If you leave off end date, it will run for only start date.

## License ##
Timelog by Joe Constant is licensed under the [MIT license](http://opensource.org/licenses/MIT)
