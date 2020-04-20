# No support

This project was made in 2018 by me. This project served as an example to me about the importance of making clean code. I have made better practices since then.

However, this code works, so you can install it using xampp.

# Filamp

Filamp is an Open Source project and script written in PHP, HTML and Javascript. It has only one file \*.php which could be renamed and its functionality should be the same. Like a File Manager, you could add files, delete files, create directories, remove directories, download files, preview files, edit files, upload files, rename files, copy and paste files, and also manage users and see the log in your server.

Currently, there are no stable releases, just development releases.

## Development release

Filamp is still in development, so the latest release is a development release. It is only recommended for test purposes because, although this script can't be edited by itself, there are other ways to be edited by the users. I know this issue, and I am currently fixing this by adding functionality.

If you are the only one user, then it should not be a problem for you. But remember, this is a development release, not a stable release.

## Requirements

Recommended: Latest releases of PHP and MySQL.

It should work under PHP 7.0 or newer and MySQL 5.0.3 or newer. This project was tested under PHP 7.2.4 and MySQL 5.5.5-10.1.31-MariaDB.

## How to set up Filamp (basic installation)

1. Install latest release of [XAMPP](https://www.apachefriends.org/download.html) (Recommended, but you could use Wampserver for example).
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Go to /path/to/htdocs/ (in Windows it is usually {Drive}:/xampp/htdocs/), then extract the file filamp.php in that folder.
4. Open filamp.php with a notepad, then edit the values of the variables $host, $user, $password and $database for your host, user, etc of your MySQL database. NOTE: You must create the $database in your [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
5. Go to [http://localhost/filamp.php?new](http://localhost/filamp.php?new) to register your user.
6. In order to avoid the registration of new users as owners in ?new, you must remove the variable $GLOBALS["admin"], or set it to false.
7. It is done! Go to [http://localhost/filamp.php](http://localhost/filamp.php) and login with your user!

Tip 1: You could rename the file filamp.php without losing functionality.

Tip 2: If you want to move the file filamp.php to another folder inside /htdocs/, you must set the main parent directory in $parent. For example, if you are in /htdocs/filamp.php, then 0, if /htdocs/path/filamp.php, then 1, if /htdocs/path/to/filamp.php, then 2.

Tip 3: You could set your own prefix for the database in the variable $prefix instead of filamp_. Uncomment and edit it. This will create a new database when the script is executed, so you must import the data from the previous database if you want to have the previous users and logs.

## Settings

```php
$parent = 0;
```
Limit of parent directories that you want to be modified by other users in Filamp. For example, if you want that nobody can edit the parent directory /htdocs/ when Filamp.php is in /htdocs/path/to/filamp.php (Limit: /path/to/filamp.php), set this variable to 2.

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "filamp";
```

Host, user, password and database of your MySQL database. You must create the database before executing the script, otherwise the script will not work.

```php
$GLOBALS["admin"] = true;
```

Set this variable to true in order to register as owner in ?new. You should use this if you have not registered previously or if you forgot your password (in this case you must select a new username). Remove this variable or set it to false when you have finished your registration.

```php
$prefix = "filamp_";
```

This is the prefix for the tables in the database. The prefix is by default "filamp_" so if you do not need this you could remove it successfully. It is a comment by default.

## Actions and privileges

Action|Member|Administrator|Owner
---|---|---|---
Upload files|Yes|Yes|Yes
Edit files|Yes|Yes|Yes
Rename files|Yes|Yes|Yes
Copy and paste files|Yes|Yes|Yes
Download files|Yes|Yes|Yes
Delete files|Yes|Yes|Yes
Create directories|Yes|Yes|Yes
Remove directories|Yes|Yes|Yes
See the log|Yes|Yes|Yes
See the users|Yes|Yes|Yes
Change their own passwords|Yes|Yes|Yes
See the Filamp file in the folder|Yes|Yes|Yes
Add users|No|Yes|Yes
Remove users|No|Members only|Yes
Set privileges|No|No|Yes
Do something with the Filamp file|No|No|No
Remove themselves as users|No|No|No
Set their own privileges|No|No|No
Add an existing user|No|No|No
Remove the main parent folder|No|No|No
Change other user's passwords|No|No|No

## Old releases

Release|Last Patch|Supported since|Supported until|Download
---|---|---|---|---
0.1|1|May 5, 2018|April 20, 2020|[Link](https://github.com/Edison2ST/Filamp/archive/v0.1.1.zip)

## Upcoming features for the next releases

1. New user: Collaborator.
2. Customization of the interface design.

## Security implementations

The passwords are strongly encrypted in the MySQL database. Also, we do not store the passwords in the cookies or sessions, we store tokens in sessions, and these ones expire within 24 hours since they were created. It takes two seconds to login as a security implementation.

The script is also designed to work against SQL injection, XSS and CSRF and other ways of exploit. Some of them are still possible, so make sure that you have logged out from Filamp.

You can't edit this script using this script to do that, but there are other ways to do it. Coming soon more security implementations for the next releases.

## File size

The script may not work properly if there are files larger than 2GB. This is because [int filesize (string $filename)](http://php.net/manual/en/function.filesize.php) works with integers and the value of [PHP_INT_MAX](http://php.net/manual/en/reserved.constants.php) is usually int(2147483647). Also, I want the script to be simple and compatible with current operating systems.

## Github pages

Don't forget to go to [our page](https://edison2st.github.io/Filamp/) in Github Pages to see more information.

## License

This project is released under the MIT License.
