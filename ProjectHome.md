This is a simple php script that will show a statistic of usage based on Squid log files.

SLA supports:
  * Recent history log per ip
  * Show usage by today, this month, last month... per ip divided by peak and off-peak hours
  * Data counted by monthly billing cycle (not calendar month)

## Instruction ##

SLA needs only one php script file to run. All you need to do is to upload the file csla.php onto a web server. All configurations are very straight forward and can be found in that file.

A cached database file will be created to speed up parsing process. Therefore, you must make sure this file is available and writable if you want to use this feature (recommended).

Good luck!